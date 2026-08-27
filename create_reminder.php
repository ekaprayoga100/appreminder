<?php
declare(strict_types=1);

require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/auth.php';

date_default_timezone_set('Asia/Jakarta');
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();

$currentUser = currentUser();
$stmt = $pdo->prepare('SELECT 1 FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$currentUser['id'] ?? 0]);
if (!$stmt->fetchColumn()) {
    session_destroy();
    header('Location: login.php?session_expired=1');
    exit;
}

$errors = [];

$values = [
    'subject' => '',
    'department' => '',
    'sub_department' => '',
    'pic' => '',
    'category' => '',
    'priority' => 'medium',
    'message' => '',
    'start_date' => '',
    'end_date' => '',
    'reminder_time' => '',
    'sender_email' => currentUser()['email'] ?? '',
    'to_emails' => '',
    'cc_emails' => '',
    'send_channel' => 'email',
    'whatsapp_numbers' => '',
];

$emailStmt = $pdo->query('SELECT id, full_name, email, department, whatsapp FROM email_data ORDER BY full_name ASC');
$emailList = $emailStmt->fetchAll();

$masterData = [
    'department' => getMasterData($pdo, 'department'),
    'sub_department' => getMasterData($pdo, 'sub_department'),
    'pic' => getMasterData($pdo, 'pic'),
    'category' => getMasterData($pdo, 'category'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {

        foreach ($values as $key => $_) {

        if ($key === 'sender_email') {
            continue;
        }

        $values[$key] = trim($_POST[$key] ?? '');
    }

    $values['sender_email'] = currentUser()['email'] ?? '';
    $values['send_channel'] = in_array($_POST['send_channel'] ?? 'email', ['email', 'whatsapp', 'both'], true) ? $_POST['send_channel'] : 'email';
    $values['whatsapp_numbers'] = trim($_POST['whatsapp_numbers'] ?? '');

    if ($values['reminder_time'] !== '') {
        $values['reminder_time'] .= ':00';
    }

    // ==========================
    // VALIDASI
    // ==========================

    if ($values['subject'] === '') {
        $errors[] = 'Perihal wajib diisi.';
    }

    if ($values['department'] === '') {
        $errors[] = 'Department wajib diisi.';
    }

    $allowedPriority = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($values['priority'], $allowedPriority, true)) {
        $values['priority'] = 'medium';
    }
    if ($values['start_date'] === '') {
        $errors[] = 'Tanggal mulai wajib diisi.';
    }

    if ($values['end_date'] === '') {
        $errors[] = 'Tanggal akhir wajib diisi.';
    }

    if ($values['start_date'] !== '' && $values['end_date'] !== '' && $values['end_date'] < $values['start_date']) {
        $errors[] = 'Tanggal akhir tidak boleh lebih awal dari tanggal mulai.';
    }

    if ($values['reminder_time'] === '') {
        $errors[] = 'Jam reminder wajib diisi.';
    }

    if ($values['send_channel'] === 'whatsapp' || $values['send_channel'] === 'both') {
        $rawWa = preg_replace('/[\s,;]+/', ',', $values['whatsapp_numbers']);
        $waParts = array_filter(array_map('trim', explode(',', (string) $rawWa)));

        if (empty($waParts)) {
            $emails = preg_split('/[\s,;]+/', $values['to_emails'], -1, PREG_SPLIT_NO_EMPTY);
            $waParts = [];

            foreach ($emails as $email) {
                $email = trim($email);
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $stmt = $pdo->prepare('SELECT whatsapp FROM email_data WHERE email = ? AND created_by = ? LIMIT 1');
                $stmt->execute([$email, $currentUser['id']]);
                $row = $stmt->fetch();

                if ($row && !empty($row['whatsapp'])) {
                    $waParts[] = $row['whatsapp'];
                }
            }

            $values['whatsapp_numbers'] = implode(',', $waParts);
        }

        if (empty($waParts)) {
            $errors[] = 'Nomor WhatsApp tujuan wajib diisi untuk channel WhatsApp.';
        } else {
            foreach ($waParts as $wa) {
                if (!preg_match('/^[+]?[0-9\s\-]{8,15}$/', $wa)) {
                    $errors[] = "Format nomor WhatsApp tidak valid: {$wa}";
                }
            }
        }
    } elseif ($values['send_channel'] === 'email') {
        $values['whatsapp_numbers'] = '';
    }

    if ($values['send_channel'] === 'whatsapp' || $values['send_channel'] === 'both') {
        $waConfig = loadWhatsAppConfig($pdo);
        if (!isWhatsAppConfigComplete($waConfig)) {
            $errors[] = 'Konfigurasi WhatsApp API belum lengkap. Silakan atur di menu Pengaturan → WhatsApp.';
        }
    }

    $toEmails = parseEmails(
        $values['to_emails'],
        true,
        'To',
        $errors
    );

    $ccEmails = parseEmails(
        $values['cc_emails'],
        false,
        'Cc',
        $errors
    );

    [$attachmentPath, $attachmentSize] = uploadReminderAttachment($errors);

    // ==========================
    // SIMPAN DATA
    // ==========================

    if (!$errors) {

        $stmt = $pdo->prepare("
            INSERT INTO reminders
            (
                subject,
                department,
                sub_department,
                pic,
                category,
                priority,
                message,
                reminder_date,
                start_date,
                end_date,
                reminder_time,
                sender_email,
                to_emails,
                cc_emails,
                attachment_path,
                attachment_size,
                status,
                created_by,
                send_channel,
                whatsapp_numbers
            )
            VALUES
            (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");

        $stmt->execute([
            $values['subject'],
            $values['department'],
            $values['sub_department'] ?: null,
            $values['pic'] ?: null,
            $values['category'] ?: null,
            $values['priority'],
            $values['message'] ?: null,
            $values['start_date'],
            $values['start_date'],
            $values['end_date'],
            $values['reminder_time'],
            $values['sender_email'],
            $toEmails,
            $ccEmails,
            $attachmentPath,
            $attachmentSize,
            'pending',
            currentUser()['id'],
            $values['send_channel'],
            $values['whatsapp_numbers'] ?: null,
        ]);

        $newId = (int) $pdo->lastInsertId();
        setFlash('auto_new_id', $newId);
        setFlash('scheduled', $newId);
        header('Location: dashboard.php?scheduled=1');
        exit;
    }
    }
}

pageHeader('Buat Reminder');
?>

<section class="page-title">
    <div>
         <h1><i class="bi bi-send me-2"></i>Buat Reminder</h1>
        <p class="muted">
            Masukkan detail reminder beserta tanggal dan jam pengiriman email & whatsapp.
        </p>
    </div>
</section>

<form class="panel form-grid" method="post" enctype="multipart/form-data">
    <?= csrfField() ?>

    <?php if ($errors): ?>
        <div class="alert alert-error full">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <label class="full">
        Perihal
        <input
            type="text"
            name="subject"
            value="<?= e($values['subject']) ?>"
            required>
    </label>

    <?= renderMasterField('department', 'department', 'Departemen', $masterData['department'], $values['department'], true, 'Pilih atau isi manual', false) ?>

    <?= renderMasterField('sub_department', 'sub_department', 'Sub Departemen', $masterData['sub_department'], $values['sub_department'], false, 'Pilih atau isi manual', false) ?>

    <?= renderMasterField('pic', 'pic', 'PIC', $masterData['pic'], $values['pic'], false, 'Nama penanggung jawab', false) ?>

    <?= renderMasterField('category', 'category', 'Klasifikasi Kategori', $masterData['category'], $values['category'], false, 'contoh: Keuangan, Operasional', false) ?>

    <label>
        Prioritas
        <select name="priority">
            <option value="low" <?= $values['priority'] === 'low' ? 'selected' : '' ?>>Rendah</option>
            <option value="medium" <?= $values['priority'] === 'medium' ? 'selected' : '' ?>>Sedang</option>
            <option value="high" <?= $values['priority'] === 'high' ? 'selected' : '' ?>>Tinggi</option>
            <option value="urgent" <?= $values['priority'] === 'urgent' ? 'selected' : '' ?>>Mendesak</option>
        </select>
    </label>


    <label class="full">
        Isi Pesan
        <textarea
            name="message"
            rows="5"
            placeholder="Tuliskan isi pesan reminder..."><?= e($values['message']) ?></textarea>
    </label>

    <label class="full">
        Email Pengirim
        <input
            type="email"
            value="<?= e($values['sender_email']) ?>"
            readonly>

        <span class="field-note">
            Otomatis mengikuti email akun yang sedang login.
        </span>
    </label>

    <label class="full">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span>To</span>
            <button type="button" class="btn btn-small btn-success" data-bs-toggle="modal" data-bs-target="#toEmailModal">
                <i class="fa-solid fa-address-book"></i> Kontak
            </button>
        </div>
        <textarea
            name="to_emails"
            id="toEmails"
            rows="2"
            placeholder="contoh: user1@email.com, user2@email.com"
            required><?= e($values['to_emails']) ?></textarea>
        <div class="selected-emails mt-2" id="toSelected" data-target="toEmails"></div>
    </label>

    <label class="full">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span>Cc</span>
            <button type="button" class="btn btn-small btn-success" data-bs-toggle="modal" data-bs-target="#ccEmailModal">
                <i class="fa-solid fa-address-book"></i> Kontak
            </button>
        </div>
        <textarea
            name="cc_emails"
            id="ccEmails"
            rows="2"
            placeholder="pisahkan dengan koma, titik koma, atau enter"><?= e($values['cc_emails']) ?></textarea>
        <div class="selected-emails mt-2" id="ccSelected" data-target="ccEmails"></div>
    </label>

    <label class="full">
        <i class="fa-solid fa-paper-plane"></i> Channel Pengiriman
        <div class="d-flex gap-3 mt-2">
            <label class="check-row">
                <input type="radio" name="send_channel" value="email" <?= $values['send_channel'] === 'email' ? 'checked' : '' ?>>
                <span>Email</span>
            </label>
            <label class="check-row">
                <input type="radio" name="send_channel" value="whatsapp" <?= $values['send_channel'] === 'whatsapp' ? 'checked' : '' ?>>
                <span>WhatsApp</span>
            </label>
            <label class="check-row">
                <input type="radio" name="send_channel" value="both" <?= $values['send_channel'] === 'both' ? 'checked' : '' ?>>
                <span>Keduanya</span>
            </label>
        </div>
    </label>

    <label class="full wa-field" id="waNumbersField" style="display: <?= in_array($values['send_channel'], ['whatsapp', 'both'], true) ? 'grid' : 'none' ?>;">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span><i class="fa-brands fa-whatsapp"></i> Nomor WhatsApp</span>
            <button type="button" class="btn btn-small btn-success" data-bs-toggle="modal" data-bs-target="#waEmailModal">
                <i class="fa-solid fa-address-book"></i> Kontak
            </button>
        </div>
        <textarea
            name="whatsapp_numbers"
            id="whatsappNumbers"
            rows="2"
            placeholder="contoh: +6281234567890, 081234567890"><?= e($values['whatsapp_numbers']) ?></textarea>
        <div class="selected-emails mt-2" id="waSelected" data-target="whatsappNumbers"></div>
        <span class="field-note">Pisahkan dengan koma. Jika kosong, sistem akan mengambil nomor WhatsApp dari Data Email berdasarkan alamat To.</span>
    </label>

    <label class="full">
        Upload Lampiran File
        <input
            type="file"
            name="attachment"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">

        <span class="field-note">
            Maksimal 5 MB. Format PDF, Word, Excel, Gambar dan ZIP.
        </span>
    </label>

    <div class="full" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px;">Tanggal Mulai</label>
            <input
                type="date"
                name="start_date"
                value="<?= e($values['start_date']) ?>"
                required
                style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
            <span class="field-note" style="margin-top: 6px; display: block;">Tanggal mulai reminder akan dikirim.</span>
        </div>

        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <label style="font-weight: 600; color: #374151; display: block; margin-bottom: 8px;">Tanggal Akhir</label>
            <input
                type="date"
                name="end_date"
                value="<?= e($values['end_date']) ?>"
                required
                style="width: 100%; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
            <span class="field-note" style="margin-top: 6px; display: block;">Tanggal akhir reminder akan dikirim (rentang dengan tanggal mulai).</span>
        </div>
    </div>

    <label class="full" style="margin-top: 4px;">
        Jam Pengiriman (24 Jam)
        <input
            type="time"
            name="reminder_time"
            value="<?= e(substr($values['reminder_time'], 0, 5)) ?>"
            required
            style="max-width: 240px; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px;">
        <span class="field-note">Format 24 jam, contoh: 14:30</span>
    </label>

    <div class="modal fade" id="toEmailModal" tabindex="-1" aria-labelledby="toEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toEmailModalLabel"><i class="fa-solid fa-address-book me-2"></i>Pilih Email Untuk To</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="toSearch" class="form-control mb-3" placeholder="Cari nama, email, atau departemen...">
                    <div class="list-group" id="toEmailList" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($emailList as $email): ?>
                            <label class="list-group-item d-flex align-items-center gap-3 email-item" data-name="<?= e(strtolower($email['full_name'])) ?>" data-email="<?= e(strtolower($email['email'])) ?>" data-dept="<?= e(strtolower($email['department'])) ?>">
                                <input class="form-check-input email-check me-auto" type="checkbox" value="<?= e($email['email']) ?>">
                                <div class="ms-2">
                                    <strong><?= e($email['full_name']) ?></strong>
                                    <small class="text-muted d-block"><?= e($email['email']) ?></small>
                                    <span class="badge status-upcoming"><?= e($email['department']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if (!$emailList): ?>
                            <div class="text-center text-muted py-4">Belum ada data email. Tambahkan di halaman Data Email.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="addSelectedEmails('to')">
                        <i class="fa-solid fa-plus"></i> Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="ccEmailModal" tabindex="-1" aria-labelledby="ccEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="ccEmailModalLabel"><i class="fa-solid fa-address-book me-2"></i>Pilih Email Untuk Cc</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="ccSearch" class="form-control mb-3" placeholder="Cari nama, email, atau departemen...">
                    <div class="list-group" id="ccEmailList" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($emailList as $email): ?>
                            <label class="list-group-item d-flex align-items-center gap-3 email-item" data-name="<?= e(strtolower($email['full_name'])) ?>" data-email="<?= e(strtolower($email['email'])) ?>" data-dept="<?= e(strtolower($email['department'])) ?>">
                                <input class="form-check-input email-check me-auto" type="checkbox" value="<?= e($email['email']) ?>">
                                <div class="ms-2">
                                    <strong><?= e($email['full_name']) ?></strong>
                                    <small class="text-muted d-block"><?= e($email['email']) ?></small>
                                    <span class="badge status-upcoming"><?= e($email['department']) ?></span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                        <?php if (!$emailList): ?>
                            <div class="text-center text-muted py-4">Belum ada data email. Tambahkan di halaman Data Email.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="addSelectedEmails('cc')">
                        <i class="fa-solid fa-plus"></i> Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="waEmailModal" tabindex="-1" aria-labelledby="waEmailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="waEmailModalLabel"><i class="fa-solid fa-address-book me-2"></i>Pilih Nomor WhatsApp</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="waSearch" class="form-control mb-3" placeholder="Cari nama, email, departemen, atau nomor WhatsApp...">
                    <div class="list-group" id="waEmailList" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($emailList as $email): ?>
                            <?php if (!empty($email['whatsapp'])): ?>
                                <label class="list-group-item d-flex align-items-center gap-3 email-item" data-name="<?= e(strtolower($email['full_name'])) ?>" data-email="<?= e(strtolower($email['email'])) ?>" data-dept="<?= e(strtolower($email['department'])) ?>" data-wa="<?= e($email['whatsapp']) ?>">
                                    <input class="form-check-input email-check me-auto" type="checkbox" value="<?= e($email['whatsapp']) ?>">
                                    <div class="ms-2">
                                        <strong><?= e($email['full_name']) ?></strong>
                                        <small class="text-muted d-block"><?= e($email['email']) ?></small>
                                        <span class="badge status-upcoming"><?= e($email['department']) ?></span>
                                        <span class="badge bg-success"><?= e($email['whatsapp']) ?></span>
                                    </div>
                                </label>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php
                            $hasWa = false;
                            foreach ($emailList as $email) {
                                if (!empty($email['whatsapp'])) {
                                    $hasWa = true;
                                    break;
                                }
                            }
                            if (!$hasWa):
                        ?>
                            <div class="text-center text-muted py-4">Belum ada data nomor WhatsApp. Tambahkan di halaman Data Email.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" onclick="addSelectedWa()">
                        <i class="fa-solid fa-plus"></i> Tambahkan
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="actions full">
        <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-xmark"></i> Batal</a>

        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Simpan Reminder</button>
    </div>

</form>

<script src="assets/js/email-modal.js"></script>

<script>
function syncMasterSelect(selectEl, name) {
    var input = document.getElementById(name);
    if (!input) return;
    if (selectEl.value === '__other__') {
        input.style.display = '';
        input.focus();
    } else {
        input.value = selectEl.value;
        input.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var channelInputs = document.querySelectorAll('input[name="send_channel"]');
    var waField = document.getElementById('waNumbersField');

    function toggleWaField() {
        var selected = document.querySelector('input[name="send_channel"]:checked');
        if (!selected || !waField) return;
        waField.style.display = (selected.value === 'whatsapp' || selected.value === 'both') ? 'grid' : 'none';
    }

    channelInputs.forEach(function(input) {
        input.addEventListener('change', toggleWaField);
    });

    toggleWaField();

    var waSearch = document.getElementById('waSearch');
    var waList = document.getElementById('waEmailList');

    if (waSearch && waList) {
        waSearch.addEventListener('input', function() {
            var query = this.value.toLowerCase();
            var items = waList.querySelectorAll('.email-item');
            items.forEach(function(item) {
                var name = item.getAttribute('data-name');
                var email = item.getAttribute('data-email');
                var dept = item.getAttribute('data-dept');
                var wa = item.getAttribute('data-wa');
                var visible = name.includes(query) || email.includes(query) || dept.includes(query) || (wa && wa.includes(query));
                item.style.display = visible ? '' : 'none';
            });
        });
    }

    renderWaTags();
});

function renderWaTags() {
    var textarea = document.getElementById('whatsappNumbers');
    var container = document.getElementById('waSelected');
    if (!textarea || !container) return;
    container.innerHTML = '';
    var raw = textarea.value.trim();
    if (!raw) return;
    var numbers = raw.split(/[,\n;]+/).map(function(s) { return s.trim(); }).filter(Boolean);
    numbers.forEach(function(number) {
        var tag = document.createElement('span');
        tag.className = 'selected-email-tag';
        tag.innerHTML = escapeHtml(number) + ' <button type="button" class="tag-remove" aria-label="Remove">&times;</button>';
        tag.setAttribute('data-number', number);
        tag.querySelector('.tag-remove').addEventListener('click', function() {
            removeWaTag(number);
        });
        container.appendChild(tag);
    });
}

function removeWaTag(number) {
    var textarea = document.getElementById('whatsappNumbers');
    if (!textarea) return;
    var raw = textarea.value.trim();
    var numbers = raw.split(/[,\n;]+/).map(function(s) { return s.trim(); }).filter(function(s) { return s !== number; });
    textarea.value = numbers.join(', ');
    renderWaTags();
}

function addSelectedWa() {
    var textarea = document.getElementById('whatsappNumbers');
    var modal = document.getElementById('waEmailModal');
    var checks = modal.querySelectorAll('.email-check:checked');
    var values = [];
    checks.forEach(function(check) { values.push(check.value); });
    var current = textarea.value.trim();
    var selected = values.join(', ');
    if (current !== '' && selected !== '') {
        textarea.value = current + ', ' + selected;
    } else if (selected !== '') {
        textarea.value = selected;
    }
    renderWaTags();
    bootstrap.Modal.getInstance(modal).hide();
    checks.forEach(function(check) { check.checked = false; });
}

function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>

<?php pageFooter(); ?>