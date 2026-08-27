<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';

redirectIfLoggedIn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Permintaan tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $stmt = $pdo->prepare('SELECT id, name, email, username, password, role FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'username' => $user['username'],
                    'role' => $user['role'],
                ];
                header('Location: dashboard.php');
                exit;
            }

            $error = 'Username atau password salah.';
        }
    }
}

pageHeader('Login');
?>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-4">
            <img src="assets/logo_mayora.png" alt="Logo Mayora" class="auth-logo">
            <h5 class="fw-bold mt-3 mb-1" style="color: #0f172a;">PT Mayora Indah Tbk</h5>
            <p class="text-muted small mb-0">IRGA Jayanti 1</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="animation: slideIn 0.3s ease;">
                <i class="fa-solid fa-circle-exclamation me-2"></i><?= e($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on" class="auth-form">
            <?= csrfField() ?>

            <div>
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username"
                       autocomplete="username" required autofocus placeholder="Masukkan username">
            </div>

            <div>
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required placeholder="Masukkan password">
            </div>

            <div>
                <button class="btn btn-danger" type="submit">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Masuk
                </button>
                <a class="btn btn-secondary" href="register.php">
                    <i class="fa-solid fa-user-plus me-2"></i> Buat Akun Baru
                </a>
            </div>
        </form>
    </div>
</div>
<?php pageFooter(); ?>
