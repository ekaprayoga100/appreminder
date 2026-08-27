<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings.php?tab=contacts');
    exit;
}

if (!validateCsrf($_POST['csrf_token'] ?? null)) {
    header('Location: settings.php?tab=contacts&import_error=csrf');
    exit;
}

if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: settings.php?tab=contacts&import_error=upload');
    exit;
}

$fileTmpPath = $_FILES['csv_file']['tmp_name'];
$fileName = $_FILES['csv_file']['name'];
$fileSize = $_FILES['csv_file']['size'];

$allowedExtensions = ['csv'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($fileExt, $allowedExtensions, true)) {
    header('Location: settings.php?tab=contacts&import_error=format');
    exit;
}

if ($fileSize > 5 * 1024 * 1024) {
    header('Location: settings.php?tab=contacts&import_error=size');
    exit;
}

$handle = fopen($fileTmpPath, 'r');
if ($handle === false) {
    header('Location: settings.php?tab=contacts&import_error=read');
    exit;
}

$currentUserId = (int) (currentUser()['id'] ?? 0);
$inserted = 0;
$skipped = 0;
$errors = [];

$header = fgetcsv($handle, 0, ';', '"');
if ($header === false || count($header) < 2) {
    fclose($handle);
    header('Location: settings.php?tab=contacts&import_error=header');
    exit;
}

while (($row = fgetcsv($handle, 0, ';', '"')) !== false) {
    if (count($row) < 2) {
        $skipped++;
        continue;
    }

    $fullName = trim($row[0] ?? '');
    $email = trim($row[1] ?? '');
    $whatsapp = trim($row[2] ?? '');

    if ($fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $skipped++;
        continue;
    }

    if ($whatsapp !== '') {
        $whatsapp = preg_replace('/[^0-9+]/', '', $whatsapp);
        if ($whatsapp === '') {
            $whatsapp = null;
        }
    } else {
        $whatsapp = null;
    }

    try {
        $stmt = $pdo->prepare('INSERT IGNORE INTO email_data (full_name, email, whatsapp, department, created_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $fullName,
            $email,
            $whatsapp,
            '',
            $currentUserId,
        ]);
        $inserted++;
    } catch (PDOException $e) {
        $skipped++;
    }
}

fclose($handle);

header('Location: settings.php?tab=contacts&import_result=1&inserted=' . $inserted . '&skipped=' . $skipped);
exit;
