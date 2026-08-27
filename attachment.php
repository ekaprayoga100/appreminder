<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireLogin();

$isAdmin = (currentUser()['role'] ?? '') === 'admin';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT attachment_path, created_by FROM reminders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$reminder = $stmt->fetch();

if (!$reminder || !$reminder['attachment_path'] || (!$isAdmin && $reminder['created_by'] !== (int) (currentUser()['id'] ?? 0))) {
    http_response_code(404);
    exit('Lampiran tidak ditemukan.');
}

$uploadDir = realpath(__DIR__ . '/uploads');
$filePath = realpath(__DIR__ . '/' . $reminder['attachment_path']);

if (!$uploadDir || !$filePath || strpos($filePath, $uploadDir) !== 0 || !is_file($filePath)) {
    http_response_code(404);
    exit('File lampiran tidak ditemukan.');
}

$fileName = basename($filePath);
$mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
$inlineTypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'text/plain',
];
$disposition = in_array($mimeType, $inlineTypes, true) ? 'inline' : 'attachment';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $fileName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
