<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();

$isAdmin = (currentUser()['role'] ?? '') === 'admin';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: dashboard.php');
    exit;
}

$reminderId = (int) ($_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM reminders WHERE id = ? LIMIT 1');
$stmt->execute([$reminderId]);
$reminder = $stmt->fetch();

if (!$reminder || (!$isAdmin && $reminder['created_by'] !== (int) (currentUser()['id'] ?? 0))) {
    header('Location: dashboard.php?missing=1');
    exit;
}

$channel = $reminder['send_channel'] ?? 'email';
$emailResult = ['success' => true];
$waResult = ['success' => true];

if ($channel === 'email' || $channel === 'both') {
    $emailResult = sendReminderEmail($pdo, $reminderId);
}

if ($channel === 'whatsapp' || $channel === 'both') {
    $waResult = sendReminderWhatsApp($pdo, $reminderId);
}

    $flash = [];
$flash['subject'] = $reminder['subject'];
$flash['channel'] = $channel;
$flash['email_status'] = $emailResult['success'] ? 'success' : 'failed';
$flash['email_error'] = $emailResult['error'] ?? null;
$flash['wa_status'] = $waResult['success'] ? 'success' : 'failed';
$flash['wa_error'] = $waResult['error'] ?? null;

setFlash('send_result', $flash);

header('Location: dashboard.php');
exit;
