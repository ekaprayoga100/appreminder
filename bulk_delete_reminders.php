<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php');
    exit;
}

$ids = $_POST['ids'] ?? [];
if (!is_array($ids) || empty($ids)) {
    header('Location: dashboard.php');
    exit;
}

$currentUserId = (int) (currentUser()['id'] ?? 0);
$isAdmin = (currentUser()['role'] ?? '') === 'admin';
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("SELECT id, attachment_path, created_by FROM reminders WHERE id IN ({$placeholders})");
$stmt->execute(array_map('intval', $ids));
$reminders = $stmt->fetchAll();

if (empty($reminders)) {
    header('Location: dashboard.php');
    exit;
}

$deleteIds = [];
foreach ($reminders as $reminder) {
    if ($isAdmin || (int) $reminder['created_by'] === $currentUserId) {
        $deleteIds[] = (int) $reminder['id'];
        deleteAttachmentFile($reminder['attachment_path']);
    }
}

if (!empty($deleteIds)) {
    $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
    $delete = $pdo->prepare("DELETE FROM reminders WHERE id IN ({$placeholders})");
    $delete->execute($deleteIds);
}

header('Location: dashboard.php?deleted=1');
exit;
