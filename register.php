<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';

if (isLoggedIn() && (currentUser()['role'] ?? '') !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$values = [
    'name' => '',
    'email' => '',
    'username' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        foreach ($values as $key => $_) {
            $values[$key] = trim($_POST[$key] ?? '');
        }

        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        if ($values['name'] === '') {
            $errors[] = 'Nama wajib diisi.';
        }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }
    if ($values['username'] === '') {
        $errors[] = 'Username wajib diisi.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password minimal 6 karakter.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR email = ?');
        $check->execute([$values['username'], $values['email']]);

        if ((int) $check->fetchColumn() > 0) {
            $errors[] = 'Username atau email sudah digunakan.';
        }
    }

    if (!$errors) {
        $role = isLoggedIn() && (currentUser()['role'] ?? '') === 'admin'
            ? (($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user')
            : 'user';

        $stmt = $pdo->prepare('INSERT INTO users (name, email, username, password, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $values['name'],
            $values['email'],
            $values['username'],
            password_hash($password, PASSWORD_DEFAULT),
            $role,
        ]);

        header('Location: ' . (isLoggedIn() ? 'settings.php?created=1' : 'login.php?registered=1'));
        exit;
    }
    }
}

pageHeader('Tambah Akun');
?>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5 col-sm-7">
            <div class="card shadow-lg border-0 mt-5">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h4 class="fw-bold text-primary mb-1">PT Mayora Indah Tbk</h4>
                        <p class="text-muted small mb-0">IRGA Jayanti 1</p>
                        <hr class="my-3">
                        <h5 class="fw-semibold"><i class="bi bi-person-plus me-2"></i>Tambah Akun</h5>
                    </div>

                    <?php if ($errors): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php foreach ($errors as $error): ?>
                                <div><?= e($error) ?></div>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <?= csrfField() ?>
                        <div class="mb-3">
                            <label for="name" class="form-label text-muted small fw-semibold">Nama</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-user text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" id="name" name="name" value="<?= e($values['name']) ?>" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label text-muted small fw-semibold">Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-envelope text-muted"></i>
                                </span>
                                <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" value="<?= e($values['email']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label text-muted small fw-semibold">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-at text-muted"></i>
                                </span>
                                <input type="text" class="form-control border-start-0 ps-0" id="username" name="username" value="<?= e($values['username']) ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label text-muted small fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label text-muted small fw-semibold">Konfirmasi Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fa-solid fa-lock text-muted"></i>
                                </span>
                                <input type="password" class="form-control border-start-0 ps-0" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <?php if (isLoggedIn() && (currentUser()['role'] ?? '') === 'admin'): ?>
                            <div class="mb-4">
                                <label for="role" class="form-label text-muted small fw-semibold">Role</label>
                                <select class="form-select" id="role" name="role">
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-user-check me-1"></i> Simpan Akun
                            </button>
                            <a class="btn btn-outline-secondary" href="<?= isLoggedIn() ? 'settings.php' : 'login.php' ?>">
                                <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                            </a>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <small class="text-muted">&copy; 2026 PT Mayora Indah Tbk &ndash; IRGA Jayanti 1</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php pageFooter(); ?>
