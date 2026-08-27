<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/whatsapp_sender.php';

function parseEmails(string $raw, bool $required, string $label, array &$errors): string
{
    $parts = preg_split('/[\s,;]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);

    if ($required && !$parts) {
        $errors[] = "{$label} wajib diisi.";
        return '';
    }

    foreach ($parts as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format email tidak valid pada {$label}: {$email}";
        }
    }

    return implode(',', array_unique($parts ?: []));
}

function uploadReminderAttachment(array &$errors): array
{
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, 0];
    }

    if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload lampiran gagal.';
        return [null, 0];
    }

    if ($_FILES['attachment']['size'] > 5 * 1024 * 1024) {
        $errors[] = 'Ukuran lampiran maksimal 5 MB.';
        return [null, 0];
    }

    $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip'];
    $originalName = $_FILES['attachment']['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExt, true)) {
        $errors[] = 'Tipe lampiran tidak diizinkan.';
        return [null, 0];
    }

    $uploadDir = __DIR__ . '/../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $safeName = bin2hex(random_bytes(12)) . '.' . $ext;
    $target = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $target)) {
        $errors[] = 'Lampiran tidak bisa disimpan.';
        return [null, 0];
    }

    return ['uploads/' . $safeName, (int) $_FILES['attachment']['size']];
}

function deleteAttachmentFile(?string $path): void
{
    if (!$path) {
        return;
    }

    $fullPath = realpath(__DIR__ . '/../' . $path);
    $uploadDir = realpath(__DIR__ . '/../uploads');

    if ($fullPath && $uploadDir && strpos($fullPath, $uploadDir) === 0 && is_file($fullPath)) {
        unlink($fullPath);
    }
}

function sendDueReminders(PDO $pdo): array
{
    date_default_timezone_set('Asia/Jakarta');

    $now = new DateTime('now');

    $sql = "
        SELECT *
        FROM reminders
        WHERE (
            status = 'pending'
            OR (status = 'done' AND DATE(sent_at) < CURDATE())
        )
        AND start_date <= CURDATE()
        AND end_date >= CURDATE()
        AND CONCAT(CURDATE(),' ',reminder_time) <= ?
        ORDER BY start_date ASC, end_date ASC, reminder_time ASC, id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$now->format('Y-m-d H:i:s')]);
    $rows = $stmt->fetchAll();

    $results = ['sent' => 0, 'failed' => 0];

    foreach ($rows as $row) {
        try {
            $channel = $row['send_channel'] ?? 'email';
            $emailResult = ['success' => true];
            $waResult = ['success' => true];

            if ($channel === 'email' || $channel === 'both') {
                $emailResult = sendReminderEmail($pdo, (int) $row['id']);
            }

            if ($channel === 'whatsapp' || $channel === 'both') {
                $waResult = sendReminderWhatsApp($pdo, (int) $row['id']);
            }

            if ($emailResult['success'] && $waResult['success']) {
                $results['sent']++;

                $update = $pdo->prepare("
                    UPDATE reminders
                    SET status = 'done', sent_at = NOW(), last_error = NULL
                    WHERE id = ?
                ");
                $update->execute([(int) $row['id']]);
            } else {
                $results['failed']++;

                $update = $pdo->prepare("
                    UPDATE reminders
                    SET status = 'failed', last_error = ?
                    WHERE id = ?
                ");
                $update->execute([
                    $emailResult['error'] ?? $waResult['error'] ?? 'Unknown error',
                    (int) $row['id'],
                ]);
            }
        } catch (Throwable $e) {
            $results['failed']++;

            $update = $pdo->prepare("
                UPDATE reminders
                SET status='failed', last_error=?
                WHERE id=?
            ");
            $update->execute([$e->getMessage(), (int) $row['id']]);
        }
    }

    return $results;
}

function formatBytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return $bytes . ' B';
}

function getMasterData(PDO $pdo, string $type): array
{
    $allowed = ['department', 'sub_department', 'pic', 'category'];
    if (!in_array($type, $allowed, true)) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT id, value FROM master_data WHERE type = ? ORDER BY value ASC');
    $stmt->execute([$type]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function renderMasterField(
    string $type,
    string $name,
    string $label,
    array $list,
    string $current,
    bool $required,
    string $placeholder,
    bool $allowOther = true
): string {
    $options = '';
    $inList = false;

    foreach ($list as $opt) {
        $value = (string) $opt['value'];
        if ($value === $current) {
            $inList = true;
        }
        $selected = $value === $current ? ' selected' : '';
        $options .= '<option value="' . e($value) . '"' . $selected . '>' . e($value) . '</option>';
    }

    $otherSelected = ($current !== '' && !$inList) ? ' selected' : '';
    $inputDisplay = ($current !== '' && !$inList) ? '' : 'display:none;';
    $selectRequired = $required ? ' required' : '';

    $html = '<label>' . e($label);
    $html .= '<select name="' . e($name) . '_select" id="' . e($name) . '_select" onchange="syncMasterSelect(this, \'' . e($name) . '\')"' . $selectRequired . '>';
    $html .= '<option value="">— Pilih —</option>';
    $html .= $options;
    if ($allowOther) {
        $html .= '<option value="__other__"' . $otherSelected . '>Lainnya (isi manual)</option>';
    }
    $html .= '</select>';
    $html .= '<input type="text" name="' . e($name) . '" id="' . e($name) . '" value="' . e($current) . '" placeholder="' . e($placeholder) . '" style="margin-top:6px;' . $inputDisplay . '">';
    $html .= '</label>';

    return $html;
}

function readLogFile(string $filename, int $maxLines = 200): string
{
    $path = __DIR__ . '/../logs/' . $filename;

    if (!file_exists($path) || !is_readable($path)) {
        return '(belum ada log)';
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false || count($lines) === 0) {
        return '(kosong)';
    }

    $lines = array_slice($lines, max(0, count($lines) - $maxLines));

    $lines = array_map(function (string $line): string {
        return rtrim($line, " \t\r");
    }, $lines);

    return implode("\n", $lines);
}

function clearLogFile(string $filename): bool
{
    $path = __DIR__ . '/../logs/' . $filename;

    if (!file_exists($path)) {
        return true;
    }

    return @file_put_contents($path, '') !== false;
}

function ensureGithubColumns(PDO $pdo): void
{
    $columns = [
        'github_repository'     => "VARCHAR(255) NULL AFTER wa_file_base_url",
        'github_token'          => "VARCHAR(500) NULL AFTER github_repository",
        'github_workflow_id'    => "VARCHAR(255) NULL AFTER github_token",
    ];

    foreach ($columns as $col => $def) {
        $sql = "ALTER TABLE smtp_settings ADD COLUMN `$col` $def";
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'duplicate column')) {
                throw $e;
            }
        }
    }
}

function getGithubConfig(PDO $pdo): array
{
    ensureGithubColumns($pdo);
    $stmt = $pdo->query("SELECT github_repository, github_token, github_workflow_id FROM smtp_settings WHERE id = 1");
    $row = $stmt->fetch();

    return [
        'github_repository'  => $row['github_repository'] ?? '',
        'github_token'       => $row['github_token'] ?? '',
        'github_workflow_id' => $row['github_workflow_id'] ?? 'reminder.yml',
    ];
}

function saveGithubConfig(PDO $pdo, array $config): bool
{
    ensureGithubColumns($pdo);
    $sql = "UPDATE smtp_settings SET
        github_repository = ?,
        github_token = ?,
        github_workflow_id = ?
        WHERE id = 1";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $config['github_repository']  ?? '',
        $config['github_token']       ?? '',
        $config['github_workflow_id'] ?? 'reminder.yml',
    ]);
}
