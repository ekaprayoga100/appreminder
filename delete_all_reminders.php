<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();

$isAdmin = (currentUser()['role'] ?? '') === 'admin';

if (!$isAdmin) {
    header('Location: dashboard.php?forbidden=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->query('SELECT id, attachment_path FROM reminders');
$reminders = $stmt->fetchAll();

foreach ($reminders as $reminder) {
    deleteAttachmentFile($reminder['attachment_path']);
}

$pdo->exec('DELETE FROM reminders');

header('Location: dashboard.php?deleted_all=1');
exit;
