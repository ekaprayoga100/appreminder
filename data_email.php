<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';

requireLogin();

$currentUser = currentUser();
$editId = null;
$errors = [];
$values = [
    'full_name' => '',
    'email' => '',
    'whatsapp' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $editId = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $values['full_name'] = trim($_POST['full_name'] ?? '');
        $values['email'] = trim($_POST['email'] ?? '');
        $values['whatsapp'] = trim($_POST['whatsapp'] ?? '');

        $errors = [];
        if ($values['full_name'] === '') {
            $errors[] = 'Nama wajib diisi.';
        }
        if ($values['email'] === '' || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid.';
        }
        if ($values['whatsapp'] !== '' && !preg_match('/^[+]?[0-9\s\-]{8,15}$/', $values['whatsapp'])) {
            $errors[] = 'Nomor WhatsApp tidak valid.';
        }

        if (!$errors) {
            if ($editId !== null) {
                $stmt = $pdo->prepare('UPDATE email_data SET full_name = ?, email = ?, whatsapp = ?, department = ? WHERE id = ?');
                $stmt->execute([
                    $values['full_name'],
                    $values['email'],
                    $values['whatsapp'] ?: null,
                    '',
                    $editId,
                ]);
                header('Location: data_email.php?updated=1');
                exit;
            } else {
                $stmt = $pdo->prepare('INSERT INTO email_data (full_name, email, whatsapp, department, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $values['full_name'],
                    $values['email'],
                    $values['whatsapp'] ?: null,
                    '',
                    $currentUser['id'],
                ]);
                header('Location: data_email.php?created=1');
                exit;
            }
        }
    }
} elseif (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $stmt = $pdo->prepare('SELECT full_name, email, whatsapp FROM email_data WHERE id = ?');
    $stmt->execute([$editId]);
    $row = $stmt->fetch();
    if ($row) {
        $values['full_name'] = $row['full_name'];
        $values['email'] = $row['email'];
        $values['whatsapp'] = $row['whatsapp'] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        header('Location: data_email.php');
        exit;
    }
    $deleteId = (int) $_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM email_data WHERE id = ?');
    $stmt->execute([$deleteId]);
    header('Location: data_email.php?deleted=1');
    exit;
}

$search = trim((string) ($_GET['search'] ?? ''));
$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$params = [];
$where = '';
if ($search !== '') {
    $where .= ' WHERE (full_name LIKE :search OR email LIKE :search OR whatsapp LIKE :search)';
    $params[':search'] = "%{$search}%";
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM email_data {$where}");
$totalStmt->execute($params);
$total = (int) $totalStmt->fetchColumn();
$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$params[':limit'] = $perPage;
$params[':offset'] = $offset;

$stmt = $pdo->prepare("SELECT id, full_name, email, whatsapp, created_at FROM email_data {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
if ($search !== '') {
    $stmt->bindValue(':search', "%{$search}%");
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$dataList = $stmt->fetchAll();

pageHeader('Data Email');
?>

<style>
    .data-email-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
        box-sizing: border-box;
    }

    .data-email-table {
        width: 100%;
        max-width: 100%;
        min-width: 0 !important;
        table-layout: fixed;
    }

    .data-email-table th,
    .data-email-table td {
        box-sizing: border-box;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }

    @media (max-width: 768px) {

        /* Container tabel harus mengikuti lebar viewport */
        .data-email-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            box-sizing: border-box;
        }

        /* Pertahankan native table layout pada mobile.
           Jangan ubah tr/thead menjadi flex karena dapat menyebabkan
           kolom dan tombol terpotong pada viewport kecil. */
        .data-email-table {
            width: 100%;
            max-width: 100%;
            min-width: 0 !important;
            table-layout: fixed;
            border-collapse: collapse;
        }

        /* Sembunyikan Email, WhatsApp, dan Dibuat pada mobile */
        .data-email-table th:nth-child(3),
        .data-email-table td:nth-child(3),
        .data-email-table th:nth-child(4),
        .data-email-table td:nth-child(4),
        .data-email-table th:nth-child(5),
        .data-email-table td:nth-child(5) {
            display: none;
        }

        /* Kolom No */
        .data-email-table th:nth-child(1),
        .data-email-table td:nth-child(1) {
            width: 40px;
            min-width: 40px;
            max-width: 40px;
            text-align: center;
            padding-left: 4px;
            padding-right: 4px;
        }

        /* Kolom Nama mengambil ruang yang tersisa */
        .data-email-table th:nth-child(2),
        .data-email-table td:nth-child(2) {
            width: auto;
            min-width: 0;
            max-width: none;
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Kolom Aksi selalu terlihat */
        .data-email-table th:nth-child(6),
        .data-email-table td:nth-child(6) {
            width: 82px;
            min-width: 82px;
            max-width: 82px;
            text-align: center;
            padding-left: 4px;
            padding-right: 4px;
        }

        /* Tombol aksi ditumpuk vertikal agar aman pada layar kecil */
        .data-email-table td:nth-child(6) .action-stack {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: center;
            gap: 4px;
            width: 100%;
        }

        .data-email-table td:nth-child(6) .btn {
            width: 100%;
            min-width: 0;
            max-width: 100%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            white-space: nowrap;
            height: 28px;
            min-height: 28px;
            padding: 0 4px;
            font-size: 9px;
            box-sizing: border-box;
        }

        .data-email-table td:nth-child(6) .btn i {
            font-size: 10px;
            margin-right: 3px;
        }

        /* Form hapus tetap inline-form, tetapi tidak boleh memperlebar kolom */
        .data-email-table td:nth-child(6) .inline-form {
            width: 100%;
            min-width: 0;
            margin: 0;
        }

        /* Empty row */
        .data-email-table tbody tr:not([data-id]) td {
            display: table-cell;
            width: 100%;
        }
    }

</style>

<section class="page-title">
    <div>
        <h1><i class="bi bi-address-book me-2"></i>Data Email & WhatsApp</h1>
        <p class="muted">Kelola daftar nama, email, dan nomor WhatsApp.</p>
    </div>
</section>

<form class="panel form-grid mb-4" method="get" action="data_email.php">
    <div class="full">
        <div class="input-group" style="max-width: 360px;">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Cari nama, email, whatsapp...">
        </div>
    </div>
</form>

<form class="panel form-grid mb-4" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= $editId !== null ? (int) $editId : '' ?>">
    <?= csrfField() ?>
    <?php if (!empty($errors)): ?>
        <div class="full alert alert-error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?= e($error) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <label class="full">
        <i class="fa-solid fa-user"></i> Nama Lengkap
        <input type="text" name="full_name" value="<?= e($values['full_name']) ?>" required>
    </label>

    <label>
        <i class="fa-solid fa-envelope"></i> Email
        <input type="email" name="email" value="<?= e($values['email']) ?>" required>
    </label>

    <label>
        <i class="fa-brands fa-whatsapp"></i> WhatsApp (Opsional)
        <input type="text" name="whatsapp" value="<?= e($values['whatsapp']) ?>" placeholder="Contoh: +6281234567890">
    </label>

    <div class="full actions">
        <?php if ($editId !== null): ?>
            <a class="btn btn-secondary" href="data_email.php"><i class="fa-solid fa-xmark"></i> Batal</a>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit">
            <i class="fa-solid fa-<?= $editId !== null ? 'check' : 'plus' ?>"></i> <?= $editId !== null ? 'Update' : 'Tambah' ?>
        </button>
    </div>
</form>

<section class="panel">
    <div class="table-wrap data-email-table-wrap">
        <table class="data-email-table">
            <thead>
            <tr>
                <th><i class="fa-solid fa-list-ol me-1"></i> No</th>
                <th><i class="fa-solid fa-user me-1"></i> Nama</th>
                <th><i class="fa-solid fa-envelope me-1"></i> Email</th>
                <th><i class="fa-solid fa-phone me-1"></i> WhatsApp</th>
                <th><i class="fa-solid fa-calendar me-1"></i> Dibuat</th>
                <th><i class="fa-solid fa-gear me-1"></i> Aksi</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$dataList): ?>
                <tr>
                    <td colspan="6" class="empty">Belum ada data.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($dataList as $index => $item): ?>
                <tr data-id="<?= (int) $item['id'] ?>">
                    <td><?= $offset + $index + 1 ?></td>
                    <td><?= e($item['full_name']) ?></td>
                    <td><?= e($item['email']) ?></td>
                    <td><?= $item['whatsapp'] ? e($item['whatsapp']) : '-' ?></td>
                    <td><?= e(date('d M Y H:i', strtotime($item['created_at']))) ?></td>
                    <td>
                        <div class="action-stack">
                            <a class="btn btn-small btn-secondary" href="data_email.php?edit=<?= (int) $item['id'] ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                            <form method="post" class="inline-form" onsubmit="return confirm('Hapus data ini?')">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="delete_id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="text-muted small">
            Menampilkan <?= ($offset + 1) ?>-<?= min($offset + $perPage, $total) ?> dari <?= $total ?> data
        </span>
        <ul class="pagination mb-0">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&laquo;</span>
                </li>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= $search !== '' ? '&search=' . urlencode($search) : '' ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i === $page): ?>
                    <li class="page-item active">
                        <span class="page-link"><?= $i ?></span>
                    </li>
                <?php else: ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $i ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"><?= $i ?></a>
                    </li>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $totalPages ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>"><?= $totalPages ?></a>
                </li>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $search !== '' ? '&search=' . urlencode($search) : '' ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&raquo;</span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<?php endif; ?>
<?php pageFooter(); ?>
