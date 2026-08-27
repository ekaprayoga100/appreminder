<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php');
    exit;
}

$isAdmin = (currentUser()['role'] ?? '') === 'admin';

$id = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT attachment_path, created_by FROM reminders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$reminder = $stmt->fetch();

if (!$reminder || (!$isAdmin && $reminder['created_by'] !== (int) (currentUser()['id'] ?? 0))) {
    header('Location: dashboard.php?missing=1');
    exit;
}

if ($reminder) {
    $delete = $pdo->prepare('DELETE FROM reminders WHERE id = ?');
    $delete->execute([$id]);
    deleteAttachmentFile($reminder['attachment_path']);
}

header('Location: dashboard.php?deleted=1');
exit;
