<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
$appConfig = require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/mail_config.php';
require __DIR__ . '/includes/whatsapp_sender.php';
require __DIR__ . '/includes/reminder_helpers.php';

date_default_timezone_set('Asia/Jakarta');

requireAdmin();

$errors = [];
$success = false;
$smtpSuccess = false;
$waSuccess = false;
$githubSuccess = false;
$githubErrors = [];
$smtpErrors = [];
$waErrors = [];
$users = [];
$config = loadMailConfig($pdo);
$waConfig = loadWhatsAppConfig($pdo);
$githubConfig = getGithubConfig($pdo);
$values = [
    'host' => $config['host'],
    'port' => (string) $config['port'],
    'encryption' => $config['encryption'],
    'username' => strpos($config['username'], 'ISI_EMAIL') !== false ? '' : $config['username'],
    'password' => strpos($config['password'], 'ISI_APP_PASSWORD') !== false ? '' : $config['password'],
    'from_email' => $config['from_email'],
    'from_name' => $config['from_name'],
    'timeout' => (string) ($config['timeout'] ?? 20),
    'wa_api_url' => $waConfig['api_url'],
    'wa_api_token' => $waConfig['api_token'],
    'wa_file_base_url' => $waConfig['file_base_url'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $formType = $_POST['form_type'] ?? '';

        if ($formType === 'smtp') {
            $values['host'] = trim($_POST['host'] ?? '');
            $values['port'] = trim($_POST['port'] ?? '');
            $values['encryption'] = trim($_POST['encryption'] ?? 'tls');
            $values['from_name'] = trim($_POST['from_name'] ?? '');
            $values['username'] = trim($_POST['username'] ?? '');
            $values['password'] = trim($_POST['password'] ?? '');
            $values['from_email'] = trim($_POST['from_email'] ?? '');
            $values['timeout'] = trim($_POST['timeout'] ?? '20');
            $values['wa_api_url'] = $waConfig['api_url'];
            $values['wa_api_token'] = $waConfig['api_token'];

            if ($values['host'] === '') {
                $smtpErrors[] = 'Host SMTP wajib diisi.';
            }
            if ((int) $values['port'] <= 0) {
                $smtpErrors[] = 'Port SMTP tidak valid.';
            }
            if (!in_array($values['encryption'], ['tls', 'ssl', 'none'], true)) {
                $smtpErrors[] = 'Encryption tidak valid.';
            }
            if (!filter_var($values['username'], FILTER_VALIDATE_EMAIL)) {
                $smtpErrors[] = 'Username SMTP harus berupa email valid.';
            }
            if ($values['password'] === '') {
                $smtpErrors[] = 'Password SMTP wajib diisi.';
            }
            if ($values['from_email'] !== '' && !filter_var($values['from_email'], FILTER_VALIDATE_EMAIL)) {
                $smtpErrors[] = 'From email tidak valid.';
            }

            if (!$smtpErrors) {
                $stmt = $pdo->prepare(
                    'INSERT INTO smtp_settings (id, host, port, encryption, username, password, from_email, from_name, timeout, wa_api_url, wa_api_token)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        host = VALUES(host),
                        port = VALUES(port),
                        encryption = VALUES(encryption),
                        username = VALUES(username),
                        password = VALUES(password),
                        from_email = VALUES(from_email),
                        from_name = VALUES(from_name),
                        timeout = VALUES(timeout),
                        wa_api_url = VALUES(wa_api_url),
                        wa_api_token = VALUES(wa_api_token)'
                );
                $stmt->execute([
                    $values['host'],
                    (int) $values['port'],
                    $values['encryption'],
                    $values['username'],
                    $values['password'],
                    $values['from_email'],
                    $values['from_name'] ?: 'Email Reminder',
                    (int) ($values['timeout'] ?: 20),
                    $values['wa_api_url'],
                    $values['wa_api_token'],
                ]);

                $smtpSuccess = true;
            }
        } elseif ($formType === 'whatsapp') {
            $values['host'] = $config['host'];
            $values['port'] = (string) $config['port'];
            $values['encryption'] = $config['encryption'];
            $values['from_name'] = $config['from_name'];
            $values['username'] = $config['username'];
            $values['password'] = $config['password'];
            $values['from_email'] = $config['from_email'];
            $values['timeout'] = (string) ($config['timeout'] ?? 20);
            $values['wa_api_url'] = trim($_POST['wa_api_url'] ?? '');
            $values['wa_api_token'] = trim($_POST['wa_api_token'] ?? '');
            $values['wa_file_base_url'] = trim($_POST['wa_file_base_url'] ?? '');

            if ($values['wa_api_url'] === '') {
                $waErrors[] = 'API URL WhatsApp wajib diisi.';
            }
            if ($values['wa_api_token'] === '') {
                $waErrors[] = 'API Token WhatsApp wajib diisi.';
            }

            if (!$waErrors) {
                $stmt = $pdo->prepare(
                    'INSERT INTO smtp_settings (id, host, port, encryption, username, password, from_email, from_name, timeout, wa_api_url, wa_api_token, wa_file_base_url)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        host = VALUES(host),
                        port = VALUES(port),
                        encryption = VALUES(encryption),
                        username = VALUES(username),
                        password = VALUES(password),
                        from_email = VALUES(from_email),
                        from_name = VALUES(from_name),
                        timeout = VALUES(timeout),
                        wa_api_url = VALUES(wa_api_url),
                        wa_api_token = VALUES(wa_api_token),
                        wa_file_base_url = VALUES(wa_file_base_url)'
                );
                $stmt->execute([
                    $values['host'],
                    (int) $values['port'],
                    $values['encryption'],
                    $values['username'],
                    $values['password'],
                    $values['from_email'],
                    $values['from_name'] ?: 'Email Reminder',
                    (int) ($values['timeout'] ?: 20),
                    $values['wa_api_url'],
                    $values['wa_api_token'],
                    $values['wa_file_base_url'] ?: null,
                ]);

                $waSuccess = true;
            }
        } elseif ($formType === 'master_data') {
            $mAction = $_POST['m_action'] ?? '';
            $mType = $_POST['m_type'] ?? '';
            $allowedTypes = ['department', 'sub_department', 'pic', 'category'];

            if (!in_array($mType, $allowedTypes, true)) {
                $errors[] = 'Tipe master data tidak valid.';
            } else {
                if ($mAction === 'add') {
                    $mValue = trim($_POST['m_value'] ?? '');
                    if ($mValue === '') {
                        $errors[] = 'Nilai master data wajib diisi.';
                    } else {
                        $stmt = $pdo->prepare('INSERT IGNORE INTO master_data (type, value) VALUES (?, ?)');
                        $stmt->execute([$mType, $mValue]);
                        $success = true;
                    }
                } elseif ($mAction === 'delete') {
                    $mId = (int) ($_POST['m_id'] ?? 0);
                    $stmt = $pdo->prepare('DELETE FROM master_data WHERE id = ? AND type = ?');
                    $stmt->execute([$mId, $mType]);
                    $success = true;
                } elseif ($mAction === 'edit') {
                    $mId = (int) ($_POST['m_id'] ?? 0);
                    $mValue = trim($_POST['m_value'] ?? '');
                    if ($mValue === '') {
                        $errors[] = 'Nilai master data wajib diisi.';
                    } else {
                        $dup = $pdo->prepare('SELECT id FROM master_data WHERE type = ? AND value = ? AND id <> ?');
                        $dup->execute([$mType, $mValue, $mId]);
                        if ($dup->fetch()) {
                            $errors[] = 'Nilai master data sudah ada.';
                        } else {
                            $stmt = $pdo->prepare('UPDATE master_data SET value = ? WHERE id = ? AND type = ?');
                            $stmt->execute([$mValue, $mId, $mType]);
                            $success = true;
                        }
                    }
                }
            }
        } elseif ($formType === 'github') {
            $githubConfig = [
                'github_repository'  => trim($_POST['github_repository'] ?? ''),
                'github_token'       => trim($_POST['github_token'] ?? ''),
                'github_workflow_id' => trim($_POST['github_workflow_id'] ?? 'reminder.yml'),
            ];

            $githubErrors = [];
            if ($githubConfig['github_repository'] === '') {
                $githubErrors[] = 'Repository GitHub wajib diisi (format: owner/repo).';
            }
            if ($githubConfig['github_token'] === '') {
                $githubErrors[] = 'Token GitHub wajib diisi.';
            }

            if (!$githubErrors) {
                if (saveGithubConfig($pdo, $githubConfig)) {
                    $githubSuccess = true;
                } else {
                    $githubErrors[] = 'Gagal menyimpan konfigurasi GitHub.';
                }
            }
        } else {
            foreach ($values as $key => $_) {
                $values[$key] = trim($_POST[$key] ?? '');
            }

            if ($values['host'] === '') {
                $errors[] = 'Host SMTP wajib diisi.';
            }
            if ((int) $values['port'] <= 0) {
                $errors[] = 'Port SMTP tidak valid.';
            }
            if (!in_array($values['encryption'], ['tls', 'ssl', 'none'], true)) {
                $errors[] = 'Encryption tidak valid.';
            }
            if (!filter_var($values['username'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Username SMTP harus berupa email valid.';
            }
            if ($values['password'] === '') {
                $errors[] = 'Password SMTP wajib diisi.';
            }
            if ($values['from_email'] !== '' && !filter_var($values['from_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'From email tidak valid.';
            }

            if (!$errors) {
                $stmt = $pdo->prepare(
                    'INSERT INTO smtp_settings (id, host, port, encryption, username, password, from_email, from_name, timeout, wa_api_url, wa_api_token)
                     VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        host = VALUES(host),
                        port = VALUES(port),
                        encryption = VALUES(encryption),
                        username = VALUES(username),
                        password = VALUES(password),
                        from_email = VALUES(from_email),
                        from_name = VALUES(from_name),
                        timeout = VALUES(timeout),
                        wa_api_url = VALUES(wa_api_url),
                        wa_api_token = VALUES(wa_api_token)'
                );
                $stmt->execute([
                    $values['host'],
                    (int) $values['port'],
                    $values['encryption'],
                    $values['username'],
                    $values['password'],
                    $values['from_email'],
                    $values['from_name'] ?: 'Email Reminder',
                    (int) ($values['timeout'] ?: 20),
                    $values['wa_api_url'],
                    $values['wa_api_token'],
                ]);

                $success = true;
            }
        }

    }

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'change_password') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($targetUserId <= 0) {
            $errors[] = 'User tidak valid.';
        } elseif ($targetUserId !== (int) (currentUser()['id'] ?? 0)) {
            $targetRole = $pdo->query('SELECT role FROM users WHERE id = ' . (int) $targetUserId)->fetchColumn();
            if ($targetRole === 'admin') {
                $errors[] = 'Tidak bisa mengubah password akun admin lain.';
            }
        }

        if (!$errors) {
            if ($newPassword === '' || $confirmPassword === '') {
                $errors[] = 'Password baru dan konfirmasi password wajib diisi.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Password baru dan konfirmasi password tidak cocok.';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Password minimal 6 karakter.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$newHash, $targetUserId]);
                $success = true;
            }
        }
    }
}

$userStmt = $pdo->query(
    'SELECT u.id, u.name, u.email, u.username, u.role, u.created_at, COUNT(r.id) AS reminder_count
     FROM users u
     LEFT JOIN reminders r ON r.created_by = u.id
     GROUP BY u.id, u.name, u.email, u.username, u.role, u.created_at
     ORDER BY u.role ASC, u.name ASC'
);
$users = $userStmt->fetchAll();

$masterTypes = [
    'department' => 'Departemen',
    'sub_department' => 'Sub Departemen',
    'pic' => 'PIC',
    'category' => 'Klasifikasi Kategori',
];
$masterData = [];
foreach (array_keys($masterTypes) as $type) {
    $masterData[$type] = getMasterData($pdo, $type);
}

$editId = (int) ($_GET['edit_id'] ?? 0);

$activeTab = $_GET['tab'] ?? 'smtp';
if (!in_array($activeTab, ['smtp', 'whatsapp', 'users', 'master', 'backup', 'contacts', 'cron', 'github'], true)) {
    $activeTab = 'smtp';
}

if ($activeTab === 'cron' && ($_GET['clear'] ?? '') !== '') {
    $clearFile = basename($_GET['clear']);
    $allowedLogFiles = [
        'cron.log',
        'cron_trigger_access.log',
        'cron_output.log',
    ];
    if (in_array($clearFile, $allowedLogFiles, true)) {
        clearLogFile($clearFile);
        header('Location: settings.php?tab=cron&cleared=1');
        exit;
    }
}

pageHeader('Pengaturan');
?>

<section class="page-title">
    <div>
        <h1><i class="bi bi-gear me-2"></i>Pengaturan</h1>
        <p class="muted">Atur SMTP, API Whatsapp, Kelola daftar akun user dan Master Data.</p>
    </div>
    <a class="btn btn-primary" href="register.php"><i class="fa-solid fa-user-plus"></i> Tambah Akun</a>
</section>

<?php if (($_GET['self'] ?? '') === '1'): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Admin tidak bisa menghapus akun sendiri.</div>
<?php elseif (($_GET['used'] ?? '') === '1'): ?>
    <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> Akun tidak bisa dihapus karena masih memiliki reminder.</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4" id="settingsTab" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'smtp' ? 'active' : '' ?>" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp-panel" type="button" role="tab">
            <i class="fa-solid fa-envelope me-1"></i> SMTP
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'whatsapp' ? 'active' : '' ?>" id="whatsapp-tab" data-bs-toggle="tab" data-bs-target="#whatsapp-panel" type="button" role="tab">
            <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-panel" type="button" role="tab">
            <i class="fa-solid fa-users me-1"></i> Akun
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'master' ? 'active' : '' ?>" id="master-tab" data-bs-toggle="tab" data-bs-target="#master-panel" type="button" role="tab">
            <i class="fa-solid fa-database me-1"></i> Master Data
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'backup' ? 'active' : '' ?>" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup-panel" type="button" role="tab">
            <i class="fa-solid fa-download me-1"></i> Backup
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'contacts' ? 'active' : '' ?>" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts-panel" type="button" role="tab">
            <i class="fa-solid fa-address-book me-1"></i> Kontak
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'cron' ? 'active' : '' ?>" id="cron-tab" data-bs-toggle="tab" data-bs-target="#cron-panel" type="button" role="tab">
            <i class="fa-solid fa-clock me-1"></i> Cron Log
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'github' ? 'active' : '' ?>" id="github-tab" data-bs-toggle="tab" data-bs-target="#github-panel" type="button" role="tab">
            <i class="fa-brands fa-github me-1"></i> GitHub Actions
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabContent">
    <div class="tab-pane fade <?= $activeTab === 'smtp' ? 'show active' : '' ?>" id="smtp-panel" role="tabpanel">

<form class="panel form-grid" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="form_type" value="smtp">
    <?php if ($smtpSuccess): ?>
        <div class="alert alert-success full"><i class="fa-solid fa-circle-check"></i> Pengaturan SMTP berhasil disimpan.</div>
    <?php endif; ?>

    <?php if ($errors || $smtpErrors): ?>
        <div class="alert alert-error full">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
                <?php foreach ($smtpErrors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <label>
        Host SMTP
        <input type="text" name="host" value="<?= e($values['host']) ?>" required>
    </label>

    <label>
        Port
        <input type="number" name="port" value="<?= e($values['port']) ?>" required>
    </label>

    <label>
        Encryption
        <select name="encryption">
            <option value="tls" <?= $values['encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
            <option value="ssl" <?= $values['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
            <option value="none" <?= $values['encryption'] === 'none' ? 'selected' : '' ?>>None</option>
        </select>
    </label>

    <label>
        Nama Pengirim
        <input type="text" name="from_name" value="<?= e($values['from_name']) ?>">
    </label>

    <label class="full">
        Username SMTP
        <input type="email" name="username" value="<?= e($values['username']) ?>" required>
    </label>

    <label class="full">
        Password SMTP
        <input type="password" name="password" value="<?= e($values['password']) ?>" required>
        <span class="field-note">Untuk Gmail gunakan App Password 16 karakter, bukan password login Gmail biasa.</span>
    </label>

    <label class="full">
        From Email
        <input type="email" name="from_email" value="<?= e($values['from_email']) ?>">
        <span class="field-note">Boleh dikosongkan agar otomatis memakai Username SMTP.</span>
    </label>

    <label>
        Timeout (detik)
        <input type="number" name="timeout" value="<?= e($values['timeout']) ?>" min="10" max="60">
        <span class="field-note">Waktu tunggu koneksi SMTP (10-60 detik).</span>
    </label>

<div class="actions full">
    <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-xmark"></i> Batal</a>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Simpan Pengaturan</button>
    </div>
</form>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'whatsapp' ? 'show active' : '' ?>" id="whatsapp-panel" role="tabpanel">

<form class="panel form-grid" method="post">
    <?= csrfField() ?>
    <input type="hidden" name="form_type" value="whatsapp">
    <?php if ($waSuccess): ?>
        <div class="alert alert-success full"><i class="fa-solid fa-circle-check"></i> Pengaturan WhatsApp berhasil disimpan.</div>
    <?php endif; ?>

    <?php if ($errors || $waErrors): ?>
        <div class="alert alert-error full">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
                <?php foreach ($waErrors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <label class="full">
        <i class="fa-brands fa-whatsapp"></i> API URL WhatsApp
        <input type="text" name="wa_api_url" value="<?= e($values['wa_api_url']) ?>" placeholder="Contoh: https://api.fonnte.com/send">
        <span class="field-note">URL endpoint API WhatsApp yang digunakan (misal Fonnte, Wablas, dll).</span>
    </label>

    <label class="full">
        <i class="fa-solid fa-key"></i> API Token / Authorization
        <input type="text" name="wa_api_token" value="<?= e($values['wa_api_token']) ?>" placeholder="Token API dari provider WhatsApp">
        <span class="field-note">Token atau kunci API untuk autentikasi ke layanan WhatsApp.</span>
    </label>

    <label class="full">
        <i class="fa-solid fa-link"></i> Base URL Lampiran (untuk kirim file via WhatsApp)
        <input type="text" name="wa_file_base_url" value="<?= e($values['wa_file_base_url']) ?>" placeholder="Contoh: https://reminder.domain.com">
        <span class="field-note">URL publik tempat folder <code>uploads/</code> dapat diakses Fonnte. Diperlukan agar lampiran terkirim (parameter <code>url</code>) pada paket basic. Kosongkan = kirim file binary (butuh paket super/advanced/ultra).</span>
    </label>

<div class="actions full">
    <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-xmark"></i> Batal</a>
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Simpan Pengaturan WhatsApp</button>
    </div>
</form>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'users' ? 'show active' : '' ?>" id="users-panel" role="tabpanel">

<div class="section-title">
    <h2><i class="fa-solid fa-users"></i> Daftar User</h2>
    <p class="muted">Daftar akun yang dapat login ke aplikasi reminder.</p>
</div>

<section class="panel">
    <div class="table-wrap accounts-table-wrap">
        <table class="accounts-table">
            <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Username</th>
                <th>Role</th>
                <th>Reminder</th>
                <th>Dibuat</th>
                <th>Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td data-label="Nama"><?= e($user['name']) ?></td>
                    <td data-label="Email" class="text-muted"><?= e($user['email']) ?></td>
                    <td data-label="Username" class="text-muted"><?= e($user['username']) ?></td>
                    <td data-label="Role"><span class="badge status-upcoming"><?= e($user['role']) ?></span></td>
                    <td data-label="Reminder" class="text-center"><?= (int) $user['reminder_count'] ?></td>
                    <td data-label="Dibuat" class="text-muted"><?= e(date('d M Y', strtotime($user['created_at']))) ?></td>
                    <td data-label="Aksi">
                        <?php if ((int) $user['id'] === (int) currentUser()['id']): ?>
                            <span class="muted">Akun aktif</span>
                        <?php else: ?>
                            <form method="post" action="settings.php?tab=users" class="change-password-form" onsubmit="return handlePasswordChange(this);">
                                <?= csrfField() ?>
                                <input type="hidden" name="form_type" value="change_password">
                                <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                <div class="account-card-actions">
                                    <input type="password" name="new_password" placeholder="Password baru" required minlength="6">
                                    <input type="password" name="confirm_password" placeholder="Konfirmasi" required minlength="6">
                                    <button class="btn btn-small btn-primary" type="submit"><i class="fa-solid fa-key"></i></button>
                                </div>
                            </form>
                            <form method="post" action="delete_account.php" class="inline-form" onsubmit="return confirm('Hapus akun ini?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Hapus</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'master' ? 'show active' : '' ?>" id="master-panel" role="tabpanel">

        <div class="section-title">
            <h2><i class="fa-solid fa-database"></i> Master Data</h2>
            <p class="muted">Kelola daftar pilihan yang tersedia pada halaman Buat Reminder (Departemen, Sub Departemen, PIC, Klasifikasi Kategori).</p>
        </div>

        <?php if ($success && $activeTab === 'master'): ?>
            <div class="alert alert-success full"><i class="fa-solid fa-circle-check"></i> Master data berhasil diperbarui.</div>
        <?php endif; ?>

        <?php if ($errors && $activeTab === 'master'): ?>
            <div class="alert alert-error full">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <?php foreach ($errors as $error): ?>
                        <div><?= e($error) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($masterTypes as $mType => $mLabel): ?>
<section class="panel mb-4">
                <div class="section-title">
                    <h3><i class="fa-solid fa-list-ul"></i> <?= e($mLabel) ?></h3>
                </div>

                <form class="d-flex gap-2 mb-3" method="post" action="settings.php?tab=master" style="flex-wrap: wrap;">
                    <?= csrfField() ?>
                    <input type="hidden" name="form_type" value="master_data">
                    <input type="hidden" name="m_action" value="add">
                    <input type="hidden" name="m_type" value="<?= e($mType) ?>">
                    <input type="text" class="form-control" name="m_value" placeholder="Tambah <?= e($mLabel) ?>" style="max-width: 320px;" required>
                    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus"></i> Tambah</button>
                </form>

                <?php if (!empty($masterData[$mType])): ?>
                    <div class="table-wrap master-data-table-wrap">
                        <table class="master-data-table">
                            <thead>
                            <tr>
                                <th>Nilai</th>
                                <th style="width: 100px;">Aksi</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($masterData[$mType] as $row): ?>
                                <tr>
                                    <td data-label="Nilai">
                                        <?php if ((int) $row['id'] === $editId): ?>
                                            <form method="post" action="settings.php?tab=master" class="master-edit-form">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="form_type" value="master_data">
                                                <input type="hidden" name="m_action" value="edit">
                                                <input type="hidden" name="m_type" value="<?= e($mType) ?>">
                                                <input type="hidden" name="m_id" value="<?= (int) $row['id'] ?>">
                                                <input type="text" class="form-control" name="m_value" value="<?= e($row['value']) ?>" required>
                                                <div class="master-card-actions">
                                                    <button class="btn btn-small btn-primary" type="submit"><i class="fa-solid fa-check"></i> Simpan</button>
                                                    <a class="btn btn-small btn-secondary" href="settings.php?tab=master"><i class="fa-solid fa-xmark"></i> Batal</a>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <?= e($row['value']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Aksi">
                                        <?php if ((int) $row['id'] !== $editId): ?>
                                            <div class="master-card-actions">
                                                <a class="btn btn-small btn-secondary" href="settings.php?tab=master&edit_id=<?= (int) $row['id'] ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                                                <form method="post" action="settings.php?tab=master" class="inline-form" onsubmit="return confirm('Hapus master data ini?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="form_type" value="master_data">
                                                    <input type="hidden" name="m_action" value="delete">
                                                    <input type="hidden" name="m_type" value="<?= e($mType) ?>">
                                                    <input type="hidden" name="m_id" value="<?= (int) $row['id'] ?>">
                                                    <button class="btn btn-small btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Hapus</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'backup' ? 'show active' : '' ?>" id="backup-panel" role="tabpanel">
        <div class="section-title">
            <h2><i class="fa-solid fa-download"></i> Backup Database</h2>
            <p class="muted">Download salinan database dalam format SQL untuk keperluan backup atau migrasi.</p>
        </div>

        <div class="panel" style="padding: 24px;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 240px;">
                    <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">Download Backup Database</div>
                    <div style="font-size: 13px; color: #64748b; line-height: 1.6;">
                        File backup berisi seluruh struktur dan data dari database <strong>appreminder</strong>.
                        File akan berformat <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;">.sql</code> dan siap untuk di-import kembali.
                    </div>
                </div>
                <a class="btn btn-primary" href="backup.php" style="min-width: 180px;">
                    <i class="fa-solid fa-download"></i> Download Backup
                </a>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'cron' ? 'show active' : '' ?>" id="cron-panel" role="tabpanel">
        <?php if (($_GET['cleared'] ?? '') === '1'): ?>
            <div class="alert alert-success full"><i class="fa-solid fa-circle-check"></i> Log berhasil dibersihkan.</div>
        <?php endif; ?>

        <?php
        $appUrl = rtrim($appConfig['app_url'] ?? 'https://appreminder.zya.me', '/');
        $cronSecret = $appConfig['cron_secret'] ?? 'YOUR_CRON_SECRET';
        $cronUrl = $appUrl . '/cron_trigger.php?key=' . $cronSecret;
        ?>

        <section class="panel mb-4">
            <div class="section-title">
                <h3><i class="fa-solid fa-rocket me-2"></i> URL Cron Scheduler</h3>
                <div class="d-flex gap-2">
                    <a href="<?= e($cronUrl) ?>" target="_blank" class="btn btn-small btn-primary">
                        <i class="fa-solid fa-play me-1"></i> Buka URL Cron
                    </a>
                </div>
            </div>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
                <div style="font-size:12px;font-weight:600;color:#6366f1;margin-bottom:8px;">URL Cron Trigger</div>
                <code style="background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;display:block;white-space:pre-wrap;word-break:break-all;">
<?= e($cronUrl) ?>
                </code>
                <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.6;">
                    URL ini dipanggil otomatis oleh <strong>GitHub Actions Scheduler</strong> setiap 5 menit via workflow <code>.github/workflows/reminder.yml</code>.
                    Simpan URL ini sebagai <strong>GitHub Secret</strong> bernama <code>CRON_TRIGGER_URL</code> di repository GitHub Anda.
                    Cron ini otomatis mengirim reminder yang jadwalnya sudah terlewati.
                </div>
            </div>
        </section>

        <?php
        $scheduledStmt = $pdo->prepare("
            SELECT id, subject, start_date, end_date, reminder_time, status, sent_at, send_channel
            FROM reminders
            WHERE start_date <= CURDATE()
              AND end_date >= CURDATE()
              AND CONCAT(CURDATE(),' ',reminder_time) > ?
            ORDER BY start_date ASC, end_date ASC, reminder_time ASC, id ASC
            LIMIT 50
        ");
        $scheduledStmt->execute([date('Y-m-d H:i:s')]);
        $scheduledReminders = $scheduledStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>

        <?php if (count($scheduledReminders) > 0): ?>
        <section class="panel mb-4">
            <div class="section-title">
                <h3><i class="fa-solid fa-clock me-2"></i> Reminder Terjadwal Hari Ini</h3>
            </div>
            <div class="table-wrap">
                <table class="accounts-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Subject</th>
                            <th>Tgl Mulai</th>
                            <th>Tgl Akhir</th>
                            <th>Jam</th>
                            <th>Channel</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduledReminders as $r): ?>
                        <tr>
                            <td data-label="ID"><?= e((string)$r['id']) ?></td>
                            <td data-label="Subject"><?= e($r['subject'] ?? '-') ?></td>
                            <td data-label="Tgl Mulai"><?= e($r['start_date'] ?? '-') ?></td>
                            <td data-label="Tgl Akhir"><?= e($r['end_date'] ?? '-') ?></td>
                            <td data-label="Jam"><?= e(substr($r['reminder_time'], 0, 5) ?? '-') ?></td>
                            <td data-label="Channel"><?php $ch = $r['send_channel'] ?? 'email'; echo e($ch === 'both' ? 'Email & WA' : $ch); ?></td>
                            <td data-label="Status"><span class="badge status-today"><?= e($r['status'] ?? '-') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php else: ?>
        <section class="panel mb-4">
            <div class="section-title">
                <h3><i class="fa-solid fa-clock me-2"></i> Reminder Terjadwal Hari Ini</h3>
            </div>
            <div class="alert alert-info" style="margin-bottom:0;">
                <i class="fa-solid fa-info-circle me-2"></i> Tidak ada reminder yang terjadwal untuk dikirim hari ini.
            </div>
        </section>
        <?php endif; ?>

    <?php
    $logFiles = [
        'cron.log' => 'Cron Activity Log',
        'cron_trigger_access.log' => 'Cron Trigger Access Log',
        'cron_output.log' => 'CLI Output Log',
    ];
    ?>

        <?php foreach ($logFiles as $file => $label): ?>
        <section class="panel mb-4">
            <div class="section-title">
                <h3><i class="fa-solid fa-file-text me-2"></i> <?= e($label) ?> <code><?= e($file) ?></code></h3>
                <div class="d-flex gap-2">
                    <form method="get" action="settings.php" class="inline-form" onsubmit="return confirm('Hapus semua isi log ini?');">
                        <input type="hidden" name="tab" value="cron">
                        <input type="hidden" name="clear" value="<?= e($file) ?>">
                        <button class="btn btn-small btn-danger" type="submit">
                            <i class="fa-solid fa-trash"></i> Bersihkan
                        </button>
                    </form>
                </div>
            </div>

            <div style="background:#0f172a;color:#e2e8f0;font-family:'Courier New',monospace;font-size:12px;line-height:1.5;max-height:400px;overflow-y:auto;padding:16px;border-radius:8px;white-space:pre-wrap;overflow-wrap:anywhere;">
                <?= e(readLogFile($file)) ?>
            </div>
        </section>
        <?php endforeach; ?>
    </div>

    <div class="tab-pane fade <?= $activeTab === 'contacts' ? 'show active' : '' ?>" id="contacts-panel" role="tabpanel">
        <div class="section-title">
            <h2><i class="fa-solid fa-address-book"></i> Import / Export Kontak</h2>
            <p class="muted">Download data kontak sebagai CSV, atau import kontak dari file CSV.</p>
        </div>

        <?php if ($_GET['import_result'] ?? '' === '1'): ?>
            <div class="alert alert-success full">
                <i class="fa-solid fa-circle-check"></i>
                Import selesai. <?= (int) ($_GET['inserted'] ?? 0) ?> kontak berhasil ditambahkan,
                <?= (int) ($_GET['skipped'] ?? 0) ?> dilewati (duplikat atau format tidak valid).
            </div>
        <?php elseif (($_GET['import_error'] ?? '') !== ''): ?>
            <?php
                $importErrorLabels = [
                    'csrf' => 'Permintaan tidak valid. Silakan coba lagi.',
                    'upload' => 'Gagal mengunggah file.',
                    'format' => 'Format file tidak didukung. Gunakan file .csv.',
                    'size' => 'Ukuran file terlalu besar. Maksimal 5MB.',
                    'read' => 'Gagal membaca file.',
                    'header' => 'Format CSV tidak valid. Pastikan baris pertama adalah header: Nama,Email,WhatsApp',
                ];
                $importError = $importErrorLabels[$_GET['import_error']] ?? 'Terjadi kesalahan saat import.';
            ?>
            <div class="alert alert-error full">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= e($importError) ?>
            </div>
        <?php endif; ?>

        <div class="panel" style="padding: 24px;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 240px;">
                    <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">Export Kontak</div>
                    <div style="font-size: 13px; color: #64748b; line-height: 1.6;">
                        Download seluruh data kontak dalam format Excel (.xls).
                    </div>
                </div>
                <a class="btn btn-primary" href="export_contacts_xls.php" style="min-width: 180px;">
                    <i class="fa-solid fa-file-excel"></i> Export XLS
                </a>
            </div>
        </div>

        <div class="panel" style="padding: 24px; margin-top: 16px;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 240px;">
                    <div style="font-size: 14px; font-weight: 600; color: #0f172a; margin-bottom: 6px;">Import Kontak</div>
                    <div style="font-size: 13px; color: #64748b; line-height: 1.6;">
                        Upload file CSV untuk menambahkan kontak secara massal. Pastikan kolom: Nama, Email, WhatsApp.
                    </div>
                </div>
                <form method="post" action="import_contacts.php" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <?= csrfField() ?>
                    <input type="file" name="csv_file" accept=".csv" required style="font-size: 13px;">
                    <button class="btn btn-secondary" type="submit" style="min-width: 180px;">
                        <i class="fa-solid fa-upload"></i> Import CSV
                    </button>
                </form>
            </div>
            <div class="field-note" style="margin-top: 10px;">
                File CSV dengan kolom: Nama, Email, WhatsApp. Baris pertama sebagai header. Maksimal ukuran file 5MB. Duplikat akan dilewati.
            </div>
        </div>
    </div>
    <div class="tab-pane fade <?= $activeTab === 'github' ? 'show active' : '' ?>" id="github-panel" role="tabpanel">

        <?php if ($githubSuccess): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check me-2"></i>Konfigurasi GitHub Actions berhasil disimpan.
            </div>
        <?php endif; ?>

        <?php if ($githubErrors): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation me-2"></i>
                <ul class="mb-0">
                    <?php foreach ($githubErrors as $ge): ?>
                        <li><?= e($ge) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        $appUrl = rtrim($appConfig['app_url'] ?? 'https://appreminder.zya.me', '/');
        $cronSecret = $appConfig['cron_secret'] ?? '';
        $fullCronUrl = $appUrl . '/cron_trigger.php?key=' . $cronSecret;
        ?>

        <div class="section-title">
            <h2><i class="fa-brands fa-github"></i> GitHub Actions Scheduler</h2>
            <p class="muted">Konfigurasi repository GitHub untuk scheduler otomatis yang memanggil cron_trigger.php setiap 5 menit.</p>
        </div>

        <form method="post" class="form-card">
            <?= csrfField() ?>
            <input type="hidden" name="form_type" value="github">
            <div class="form-grid">
                <div>
                    <label>Repository GitHub <i class="fa-solid fa-info" title="Format: owner/repo (contoh: mayora-jayanti/appreminder)"></i></label>
                    <input type="text" name="github_repository" class="form-control"
                           placeholder="owner/repo"
                           value="<?= e($githubConfig['github_repository'] ?? '') ?>" required>
                    <div class="field-note">Repository tempat workflow GitHub Actions berada.</div>
                </div>
                <div>
                    <label>GitHub Token (PAT)</label>
                    <input type="password" name="github_token" class="form-control"
                           placeholder="ghp_xxxxxxxxxxxxxxxxxxxx"
                           value="<?= e($githubConfig['github_token'] ?? '') ?>" required>
                    <div class="field-note">Personal Access Token dengan scope <code>workflow</code>. Token akan tersimpan ter-enkripsi. <strong>Simpan sebagai GitHub Secret</strong> juga: <code>CRON_TRIGGER_URL</code>.</div>
                </div>
                <div class="full">
                    <label>Workflow File ID</label>
                    <input type="text" name="github_workflow_id" class="form-control"
                           placeholder="reminder.yml"
                           value="<?= e($githubConfig['github_workflow_id'] ?? 'reminder.yml') ?>">
                    <div class="field-note">Nama file workflow di <code>.github/workflows/</code>. Default: <code>reminder.yml</code></div>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-save me-1"></i> Simpan Konfigurasi
                </button>
            </div>
        </form>

        <div class="section-title" style="margin-top: 28px;">
            <h2><i class="fa-solid fa-link"></i> Cron Trigger URL</h2>
            <p class="muted">URL ini akan dipanggil oleh GitHub Actions setiap 5 menit.</p>
        </div>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;">
            <code style="background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;display:block;white-space:pre-wrap;word-break:break-all;">
<?= e($fullCronUrl) ?>
            </code>
            <div class="field-note" style="margin-top:8px;">
                Salin URL ini dan simpan sebagai GitHub Secret bernama <code>CRON_TRIGGER_URL</code> di Settings → Secrets and variables → Actions di repository GitHub Anda.
            </div>
        </div>

        <div class="section-title" style="margin-top: 28px;">
            <h2><i class="fa-solid fa-plug-curve"></i> Status Koneksi</h2>
            <p class="muted">Repository dan token telah dikonfigurasi:</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap:14px; margin-bottom: 16px;">
            <div class="stat" style="--stat-accent:#2563eb;">
                <span>Repository</span>
                <strong style="font-size:13px;"><?= e($githubConfig['github_repository'] ?: 'Belum diisi') ?></strong>
            </div>
            <div class="stat" style="--stat-accent:#059669;">
                <span>Workflow ID</span>
                <strong style="font-size:13px;"><?= e($githubConfig['github_workflow_id'] ?: 'reminder.yml') ?></strong>
            </div>
            <div class="stat" style="--stat-accent:#dc2626;">
                <span>Schedule</span>
                <strong>5 menit</strong>
            </div>
        </div>

        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 18px;margin-top:16px;">
            <div style="font-size:12px;font-weight:600;color:#1d4ed8;margin-bottom:6px;">
                <i class="fa-solid fa-clipboard-check me-1"></i> Panduan Setup GitHub Actions
            </div>
            <ol style="margin:6px 0 0 20px; padding:0; font-size:12px; color:#374151; line-height:1.6;">
                <li>Commit file <code>.github/workflows/reminder.yml</code> ke repository Anda.</li>
                <li>Buka Settings → Secrets and variables → Actions → New repository secret.</li>
                <li>Buat secret <code>CRON_TRIGGER_URL</code> dengan nilai di atas.</li>
                <li>Buka tab Actions di GitHub — lihat workflow berjalan otomatis setiap 5 menit.</li>
                <li>Buka halaman <a href="scheduler_monitor.php" class="text-blue-700">Monitor Scheduler</a> untuk melihat status real-time.</li>
            </ol>
        </div>

    </div>
</div>

    <style>
        .master-data-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            box-sizing: border-box;
        }

        .master-data-table {
            width: 100%;
            max-width: 100%;
            min-width: 0 !important;
            table-layout: fixed;
            border-collapse: collapse;
        }

        .master-data-table th,
        .master-data-table td {
            padding: 8px 12px;
            font-size: 13px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            box-sizing: border-box;
        }

        .master-data-table th {
            background: #f8fafc;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            white-space: nowrap;
        }

        /* Nilai mengambil seluruh ruang yang tersedia. */
        .master-data-table th:first-child,
        .master-data-table td:first-child {
            width: auto;
            min-width: 0;
        }

        /* Kolom aksi tetap terlihat dan tidak mendorong tabel melebar. */
        .master-data-table th:nth-child(2),
        .master-data-table td:nth-child(2) {
            width: 100px;
            min-width: 100px;
            max-width: 100px;
            text-align: right;
        }

        .master-data-table td:first-child {
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .master-data-table .master-action-stack {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 6px;
            justify-content: flex-end;
            align-items: center;
            width: 100%;
        }

        .master-data-table .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-sizing: border-box;
        }

        /* Form edit di dalam cell tidak boleh memperlebar tabel. */
        .master-data-table td:first-child > form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            width: 100%;
            max-width: 100%;
        }

        .master-data-table td:first-child > form input[name="m_value"] {
            flex: 1 1 220px;
            width: auto !important;
            max-width: 320px !important;
            min-width: 0;
        }

        @media (max-width: 768px) {
            /* ===== MASTER DATA TABLE MOBILE — CARD MODEL ===== */
            .master-data-table-wrap {
                width: 100%;
                max-width: 100%;
                overflow: visible;
                box-sizing: border-box;
            }

            .master-data-table {
                min-width: 0 !important;
                table-layout: auto;
            }

            .master-data-table thead {
                display: none;
            }

            .master-data-table tbody {
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }

            .master-data-table tbody tr {
                display: flex;
                flex-direction: column;
                gap: 10px;
                width: 100%;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 14px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }

            .master-data-table tbody tr td {
                display: flex;
                flex-direction: column;
                gap: 2px;
                padding: 0 !important;
                border: none !important;
                text-align: left !important;
                width: 100%;
                min-width: 0;
            }

            .master-data-table tbody tr td::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                color: #64748b;
                margin-bottom: 0px;
            }

            .master-data-table tbody tr td[data-label="Nilai"] {
                font-size: 15px;
                font-weight: 700;
                color: #0f172a;
            }

            .master-data-table tbody tr td[data-label="Nilai"]::before {
                display: none;
            }

            .master-data-table tbody tr td[data-label="Aksi"] {
                align-items: stretch;
                margin-top: 4px;
                padding-top: 10px !important;
                border-top: 1px solid #e2e8f0 !important;
            }

            .master-data-table tbody tr td[data-label="Aksi"]::before {
                display: none;
            }

            .master-card-actions {
                display: flex;
                flex-direction: column;
                gap: 6px;
                width: 100%;
            }

            .master-data-table .master-edit-form {
                display: flex;
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .master-data-table .master-edit-form input[name="m_value"] {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0;
                padding: 8px 10px;
                font-size: 13px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-sizing: border-box;
            }

            .master-data-table .master-edit-form .btn-small {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                height: 36px;
                font-size: 13px;
                padding: 0 12px;
            }

            .master-data-table td[data-label="Aksi"] .inline-form {
                width: 100%;
            }

            .master-data-table td[data-label="Aksi"] .btn-danger {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                height: 36px;
                font-size: 13px;
                padding: 0 12px;
                white-space: nowrap;
            }

            .master-data-table td[data-label="Aksi"] .btn-danger i {
                font-size: 12px;
                margin-right: 4px;
            }

            .master-data-table td[data-label="Aksi"] .btn-secondary {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                height: 36px;
                font-size: 13px;
                padding: 0 12px;
            }

            .master-data-table td[data-label="Aksi"] .btn-secondary i {
                font-size: 12px;
                margin-right: 4px;
            }

            /* Empty state */
            .master-data-table tbody tr td[colspan] {
                display: block;
                width: 100%;
                text-align: center;
                padding: 40px 20px;
                color: #94a3b8;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
            }

            /* ===== ACCOUNTS TABLE MOBILE — CARD MODEL ===== */
            .accounts-table-wrap {
                width: 100%;
                max-width: 100%;
                overflow: visible;
                box-sizing: border-box;
            }

            .accounts-table {
                min-width: 0 !important;
                table-layout: auto;
            }

            .accounts-table thead {
                display: none;
            }

            .accounts-table tbody {
                display: flex;
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }

            .accounts-table tbody tr {
                display: flex;
                flex-direction: column;
                gap: 8px;
                width: 100%;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                padding: 16px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }

            .accounts-table tbody tr td {
                display: flex;
                flex-direction: column;
                gap: 2px;
                padding: 0 !important;
                border: none !important;
                text-align: left !important;
                width: 100%;
                min-width: 0;
            }

            .accounts-table tbody tr td::before {
                content: attr(data-label);
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.4px;
                color: #64748b;
                margin-bottom: 0px;
            }

            .accounts-table tbody tr td[data-label="Nama"] {
                font-size: 16px;
                font-weight: 700;
                color: #0f172a;
            }

            .accounts-table tbody tr td[data-label="Nama"]::before {
                display: none;
            }

            .accounts-table tbody tr td[data-label="Aksi"] {
                align-items: stretch;
                margin-top: 6px;
                padding-top: 12px !important;
                border-top: 1px solid #e2e8f0 !important;
            }

            .accounts-table tbody tr td[data-label="Aksi"]::before {
                display: none;
            }

            .account-card-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                width: 100%;
            }

            .account-card-actions input[name="new_password"],
            .account-card-actions input[name="confirm_password"] {
                width: 100% !important;
                max-width: 100% !important;
                min-width: 0;
                padding: 10px 12px;
                font-size: 14px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-sizing: border-box;
            }

            .accounts-table td[data-label="Aksi"] .change-password-form .btn-small {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                height: 40px;
                font-size: 14px;
                padding: 0 12px;
            }

            .accounts-table td[data-label="Aksi"] .inline-form {
                width: 100%;
            }

            .accounts-table td[data-label="Aksi"] .btn-danger {
                width: 100%;
                min-width: 0;
                max-width: 100%;
                height: 40px;
                font-size: 14px;
                padding: 0 12px;
                white-space: nowrap;
            }

            .accounts-table td[data-label="Aksi"] .btn-danger i {
                font-size: 12px;
                margin-right: 4px;
            }

            /* Empty row */
            .accounts-table tbody tr:not([data-id]):not(.detail-row) {
                display: block;
                width: 100%;
            }
            .accounts-table tbody tr:not([data-id]):not(.detail-row) td {
                display: block;
                width: 100%;
            }
        }
    </style>
    <script>
        function handlePasswordChange(form) {
            var newPass = form.querySelector('input[name="new_password"]').value;
            var confirmPass = form.querySelector('input[name="confirm_password"]').value;
            if (newPass !== confirmPass) {
                alert('Password baru dan konfirmasi password tidak cocok.');
                return false;
            }
            if (newPass.length < 6) {
                alert('Password minimal 6 karakter.');
                return false;
            }
            return confirm('Ubah password untuk user ini?');
        }
    </script>
<?php pageFooter(); ?>
