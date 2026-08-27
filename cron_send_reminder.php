<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit;
}

date_default_timezone_set('Asia/Jakarta');

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}
$logFile = $logDir . '/cron.log';

function writeLog(string $message): void
{
    global $logFile;
    file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

try {
    require __DIR__ . '/config/database.php';
    require __DIR__ . '/includes/reminder_helpers.php';
} catch (Throwable $e) {
    writeLog('ERROR : ' . $e->getMessage());
    echo "ERROR : " . $e->getMessage() . "\n";
    exit(1);
}

$now = new DateTime('now');
$nowStr = $now->format('Y-m-d H:i:s');

echo "=============================================\n";
echo "        APP REMINDER CRON SENDER\n";
echo "=============================================\n";
echo "Waktu Server : " . $nowStr . "\n\n";

try {

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
        ORDER BY start_date ASC,
                 end_date ASC,
                 reminder_time ASC,
                 id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nowStr]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($rows);

    echo "Reminder yang harus dikirim : {$total}\n\n";

    writeLog("Cron dijalankan. Total reminder : {$total}");

    if ($total === 0) {

        echo "Tidak ada reminder yang harus dikirim.\n";

        writeLog("Tidak ada reminder.");

        exit;
    }

    $success = 0;
    $failed = 0;

    foreach ($rows as $row) {

        echo "-------------------------------------\n";
        echo "ID        : {$row['id']}\n";
        echo "Subject   : {$row['subject']}\n";
        echo "Tanggal Mulai : {$row['start_date']}\n";
        echo "Tanggal Akhir : {$row['end_date']}\n";
        echo "Jam       : {$row['reminder_time']}\n";
        echo "Channel   : " . ($row['send_channel'] ?? 'email') . "\n";
        echo "Tujuan    : {$row['to_emails']}\n";
        echo "WA Nomor  : " . ($row['whatsapp_numbers'] ?: '(dari data email)') . "\n";
        echo "Cc        : " . ($row['cc_emails'] ?: '(tidak ada)') . "\n";
        echo "Pengirim  : {$row['sender_email']}\n";

        writeLog("Mengirim Reminder ID {$row['id']} - {$row['subject']}");

        try {
            $channel = $row['send_channel'] ?? 'email';
            $emailResult = ['success' => true];
            $waResult = ['success' => true];

            if ($channel === 'email' || $channel === 'both') {
                $emailResult = sendReminderEmail(
                    $pdo,
                    (int)$row['id']
                );
            }

            if ($channel === 'whatsapp' || $channel === 'both') {
                $waResult = sendReminderWhatsApp(
                    $pdo,
                    (int)$row['id']
                );
            }

            if ($emailResult['success'] && $waResult['success']) {
                echo "Status    : BERHASIL\n";
                writeLog("Berhasil mengirim Reminder ID {$row['id']}");
                $success++;

                $update = $pdo->prepare("
                    UPDATE reminders
                    SET status = 'done', sent_at = NOW(), last_error = NULL
                    WHERE id = ?
                ");
                $update->execute([(int)$row['id']]);
            } else {
                echo "Status    : GAGAL\n";
                $errors = [];
                if (!$emailResult['success']) {
                    $errors[] = 'Email: ' . ($emailResult['error'] ?? 'unknown');
                }
                if (!$waResult['success']) {
                    $errors[] = 'WA: ' . ($waResult['error'] ?? 'unknown');
                }
                echo "Error     : " . implode(' | ', $errors) . "\n";
                writeLog("Gagal Reminder ID {$row['id']} : " . implode(' | ', $errors));
                $failed++;

                $update = $pdo->prepare("
                    UPDATE reminders
                    SET status = 'failed', last_error = ?
                    WHERE id = ?
                ");
                $update->execute([
                    implode(' | ', $errors),
                    (int)$row['id'],
                ]);
            }
        } catch (Throwable $e) {
            echo "Status    : GAGAL\n";
            echo "Error     : {$e->getMessage()}\n";
            writeLog("Gagal Reminder ID {$row['id']} : " . $e->getMessage());
            $failed++;

            $update = $pdo->prepare("
                UPDATE reminders
                SET status='failed', last_error=?
                WHERE id=?
            ");
            $update->execute([$e->getMessage(), (int)$row['id']]);
        }

        echo "\n";
    }

    echo "=============================================\n";
    echo "SELESAI\n";
    echo "=============================================\n";
    echo "Berhasil : {$success}\n";
    echo "Gagal    : {$failed}\n";
    echo "Selesai  : " . date('Y-m-d H:i:s') . "\n";

    writeLog(
        "Cron selesai. Berhasil={$success}, Gagal={$failed}"
    );

} catch (Throwable $e) {

    echo "Terjadi kesalahan.\n";
    echo $e->getMessage() . "\n";

    writeLog("ERROR : " . $e->getMessage());
}

echo "\n";