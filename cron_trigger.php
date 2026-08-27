<?php
declare(strict_types=1);

// Send JSON headers early to prevent hosting anti-bot from intercepting
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-cache, no-store, must-revalidate');

date_default_timezone_set('Asia/Jakarta');

require __DIR__ . '/config/database.php';
$appConfig = require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/reminder_helpers.php';

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/cron_trigger_access.log';

function writeCronAccessLog(string $message): void
{
    global $logFile;
    @file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

writeCronAccessLog('Accessed from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

$secret = $_GET['key'] ?? '';
$expectedSecret = $appConfig['cron_secret'] ?? '';

if ($secret === '' || !hash_equals($expectedSecret, $secret)) {
    writeCronAccessLog('Akses ditolak - secret mismatch');
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit;
}

writeCronAccessLog('Auth berhasil, memulai sendDueReminders');

function sendOutputLog(string $message): void
{
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    file_put_contents(
        $logDir . '/cron_output.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function getPdo(): PDO
{
    require __DIR__ . '/config/database.php';
    return $pdo;
}

$maxRetries = 2;
$attempt = 0;
$results = ['sent' => 0, 'failed' => 0];

while ($attempt < $maxRetries) {
    try {
        $results = sendDueReminders($pdo);
        break;
    } catch (Throwable $e) {
        $attempt++;

        $message = $e->getMessage();
        sendOutputLog('Attempt ' . $attempt . ' error: ' . $message);

        if ($attempt >= $maxRetries || stripos($message, 'MySQL server has gone away') === false) {
            writeCronAccessLog('Error: ' . $message);
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $message,
            ]);
            exit;
        }

        sendOutputLog('Retrying with fresh connection (attempt ' . $attempt . ')');
        $pdo = getPdo();
    }
}

writeCronAccessLog('Selesai. Sent=' . $results['sent'] . ', Failed=' . $results['failed']);
sendOutputLog('Sent=' . $results['sent'] . ', Failed=' . $results['failed']);

echo json_encode([
    'success' => true,
    'sent' => $results['sent'],
    'failed' => $results['failed'],
    'time' => date('Y-m-d H:i:s')
]);
