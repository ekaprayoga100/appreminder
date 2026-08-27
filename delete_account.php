<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php?tab=users');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: settings.php?tab=users');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id === (int) currentUser()['id']) {
    header('Location: settings.php?self=1&tab=users');
    exit;
}

$check = $pdo->prepare('SELECT COUNT(*) FROM reminders WHERE created_by = ?');
$check->execute([$id]);

if ((int) $check->fetchColumn() > 0) {
    header('Location: settings.php?used=1&tab=users');
    exit;
}

$delete = $pdo->prepare('DELETE FROM users WHERE id = ?');
$delete->execute([$id]);

header('Location: settings.php?deleted=1&tab=users');
exit;
