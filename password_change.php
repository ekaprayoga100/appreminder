<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';

requireLogin();

$currentUser = currentUser();
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $errors[] = 'Password saat ini wajib diisi.';
        }
        if ($newPassword === '') {
            $errors[] = 'Password baru wajib diisi.';
        }
        if (strlen($newPassword) < 6) {
            $errors[] = 'Password baru minimal 6 karakter.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Konfirmasi password baru tidak sama.';
        }
        if ($currentPassword === $newPassword) {
            $errors[] = 'Password baru harus berbeda dari password saat ini.';
        }

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$currentUser['id']]);
            $storedHash = $stmt->fetchColumn();

            if ($storedHash && password_verify($currentPassword, $storedHash)) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->execute([$newHash, $currentUser['id']]);
                $success = true;
            } else {
                $errors[] = 'Password saat ini salah.';
            }
        }
    }
}

pageHeader('Ganti Password');
?>

<section class="page-title">
    <div>
        <h1><i class="bi bi-key me-2"></i>Ganti Password</h1>
        <p class="muted">Ubah password akun Anda untuk menjaga keamanan.</p>
    </div>
</section>

<?php if ($success): ?>
    <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Password berhasil diubah.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-circle-exclamation"></i>
        <div>
            <?php foreach ($errors as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<form class="panel form-grid" method="post">
    <?= csrfField() ?>

    <label class="full">
        Password Saat Ini
        <input type="password" name="current_password" required autofocus>
    </label>

    <label class="full">
        Password Baru
        <input type="password" name="new_password" required minlength="6">
        <span class="field-note">Minimal 6 karakter.</span>
    </label>

    <label class="full">
        Konfirmasi Password Baru
        <input type="password" name="confirm_password" required>
    </label>

    <div class="full actions">
        <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-xmark"></i> Batal</a>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-check"></i> Ubah Password</button>
    </div>
</form>

<?php pageFooter(); ?>
