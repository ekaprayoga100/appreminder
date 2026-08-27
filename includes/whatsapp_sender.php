<?php
declare(strict_types=1);

function logWhatsApp(string $message): void
{
    $logFile = __DIR__ . '/../logs/whatsapp.log';

    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0777, true);
    }

    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

function loadWhatsAppConfig(?PDO $pdo = null): array
{
    $config = [
        'api_url' => '',
        'api_token' => '',
        'file_base_url' => '',
    ];

    if (!$pdo) {
        return $config;
    }

    try {
        $stmt = $pdo->query('SELECT wa_api_url, wa_api_token, wa_file_base_url FROM smtp_settings WHERE id = 1');
        $settings = $stmt->fetch();

        if ($settings) {
            $config['api_url'] = (string) ($settings['wa_api_url'] ?: '');
            $config['api_token'] = (string) ($settings['wa_api_token'] ?: '');
            $config['file_base_url'] = (string) ($settings['wa_file_base_url'] ?: '');
        }
    } catch (Throwable $e) {
        // If the settings table is not installed yet, fall back to empty config.
    }

    return $config;
}

function isWhatsAppConfigComplete(array $config): bool
{
    return $config['api_url'] !== '' && $config['api_token'] !== '';
}

function sendWhatsAppMessage(array $config, string $phoneNumber, string $message, ?string $fileUrl = null, ?string $filePath = null): bool
{
    if (!isWhatsAppConfigComplete($config)) {
        throw new RuntimeException('Konfigurasi WhatsApp API belum lengkap.');
    }

    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

    if (str_starts_with($phoneNumber, '0')) {
        $phoneNumber = '62' . substr($phoneNumber, 1);
    }

    if (strlen($phoneNumber) < 10) {
        throw new RuntimeException('Nomor WhatsApp tidak valid: ' . $phoneNumber);
    }

    $postFields = [
        'target' => $phoneNumber,
        'message' => $message,
    ];

    $headers = [
        'Authorization: ' . $config['api_token'],
    ];

    $fileMode = 'no';
    if ($fileUrl !== null && $fileUrl !== '') {
        $postFields['url'] = $fileUrl;
        $fileMode = 'url';
    } elseif ($filePath !== null) {
        $fullPath = realpath(__DIR__ . '/../' . $filePath);
        if ($fullPath && is_file($fullPath)) {
            $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
            $postFields['file'] = new CURLFile($fullPath, $mime, basename($fullPath));
            $fileMode = 'binary';
        }
    }

    logWhatsApp('REQUEST URL: ' . $config['api_url']);
    logWhatsApp('REQUEST TARGET: ' . $phoneNumber);
    logWhatsApp('REQUEST FILE MODE: ' . $fileMode . ($fileMode === 'url' ? ' (' . $fileUrl . ')' : ''));

    $ch = curl_init();

    $curlOptions = [
        CURLOPT_URL => $config['api_url'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if (isset($postFields['file'])) {
        $curlOptions[CURLOPT_POSTFIELDS] = $postFields;
    } else {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $curlOptions[CURLOPT_HTTPHEADER] = $headers;
        $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($postFields);
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logWhatsApp('HTTP CODE: ' . $httpCode);
    logWhatsApp('RESPONSE: ' . $response);

    if ($curlError) {
        throw new RuntimeException('cURL error: ' . $curlError);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('HTTP ' . $httpCode . ': ' . $response);
    }

    $decoded = json_decode($response, true);

    if (is_array($decoded)) {
        if (isset($decoded['status'])) {
            $status = $decoded['status'];

            if ($status === true || $status === 'true' || $status === 'success' || $status === 'sent' || (is_int($status) && $status === 200)) {
                return true;
            }

            if ($status === false || $status === 'failed' || $status === 'error') {
                $reason = $decoded['reason'] ?? $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
                throw new RuntimeException('WhatsApp API: ' . $reason);
            }

            if (is_string($status) && stripos($status, 'success') !== false) {
                return true;
            }
        }

        if (isset($decoded['result'])) {
            $result = $decoded['result'];

            if ($result === true || $result === 'true' || $result === 'success' || $result === 'sent') {
                return true;
            }

            if ($result === false || $result === 'failed' || $result === 'error') {
                $reason = $decoded['reason'] ?? $decoded['message'] ?? $decoded['error'] ?? 'Unknown error';
                throw new RuntimeException('WhatsApp API: ' . $reason);
            }

            if (is_string($result) && stripos($result, 'success') !== false) {
                return true;
            }
        }

        if (!empty($decoded['reason'])) {
            throw new RuntimeException('WhatsApp API: ' . $decoded['reason']);
        }

        if (!empty($decoded['message'])) {
            throw new RuntimeException('WhatsApp API: ' . $decoded['message']);
        }

        if (!empty($decoded['error'])) {
            throw new RuntimeException('WhatsApp API: ' . $decoded['error']);
        }
    }

    if (is_string($response) && stripos($response, 'success') !== false) {
        return true;
    }

    if (is_string($response) && stripos($response, 'sent') !== false) {
        return true;
    }

    if (is_string($response) && stripos($response, 'error') !== false) {
        throw new RuntimeException('WhatsApp API error: ' . $response);
    }

    if (is_string($response) && trim($response) !== '') {
        return true;
    }

    throw new RuntimeException('WhatsApp API: Respons tidak dikenali. HTTP ' . $httpCode . ' - ' . substr($response, 0, 200));
}

function buildWhatsAppMessage(array $reminder, ?string $fileUrl = null): string
{
    $priorityLabels = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak',
    ];
    $priorityLabel = $priorityLabels[$reminder['priority'] ?? 'medium'] ?? 'Sedang';

    $lines = [];
    $lines[] = 'Yth. Bapak/Ibu,';
    $lines[] = '';
    $lines[] = 'Berikut reminder yang perlu ditindaklanjuti:';
    $lines[] = '';
    $lines[] = 'Perihal          : ' . $reminder['subject'];
    $lines[] = 'Department       : ' . $reminder['department'];
    if (!empty($reminder['sub_department'])) {
        $lines[] = 'Sub Departemen   : ' . $reminder['sub_department'];
    }
    if (!empty($reminder['pic'])) {
        $lines[] = 'PIC              : ' . $reminder['pic'];
    }
    if (!empty($reminder['category'])) {
        $lines[] = 'Kategori         : ' . $reminder['category'];
    }
    $lines[] = 'Prioritas        : ' . $priorityLabel;
    $lines[] = 'Tanggal Mulai    : ' . date('d M Y', strtotime($reminder['start_date'] ?? $reminder['reminder_date']));
    $lines[] = 'Tanggal Akhir    : ' . date('d M Y', strtotime($reminder['end_date'] ?? $reminder['start_date'] ?? $reminder['reminder_date']));
    $lines[] = 'Jam              : ' . ($reminder['reminder_time'] ?? '-');

    if (!empty($reminder['message'])) {
        $lines[] = '';
        $lines[] = 'Isi Pesan:';
        $lines[] = $reminder['message'];
    }

    if ($fileUrl !== null && $fileUrl !== '') {
        $lines[] = '';
        $lines[] = 'Lampiran: ' . $fileUrl;
    }

    if (!empty($reminder['sender_email'])) {
        $lines[] = '';
        $lines[] = 'Dikirim oleh     : ' . $reminder['sender_email'];
    }

    $lines[] = '';
    $lines[] = 'Pesan ini dikirim secara otomatis oleh sistem App Reminder.';

    return implode("\n", $lines);
}

function sendReminderWhatsApp(PDO $pdo, int $id): array
{
    $waConfig = loadWhatsAppConfig($pdo);

    if (!isWhatsAppConfigComplete($waConfig)) {
        return [
            'success' => false,
            'error' => 'Konfigurasi WhatsApp API belum lengkap.',
        ];
    }

    $stmt = $pdo->prepare('SELECT * FROM reminders WHERE id = ?');
    $stmt->execute([$id]);
    $reminder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reminder) {
        return [
            'success' => false,
            'error' => 'Reminder tidak ditemukan.',
        ];
    }

    if ($reminder['status'] === 'done' && $reminder['send_channel'] !== 'both') {
        return [
            'success' => false,
            'error' => 'Reminder WhatsApp sudah pernah dikirim.',
        ];
    }

    $rawNumbers = $reminder['whatsapp_numbers'] ?? '';

    if ($rawNumbers === '') {
        $emails = explode(',', $reminder['to_emails']);
        $numbers = [];

        foreach ($emails as $email) {
            $email = trim($email);
            if ($email === '') {
                continue;
            }

            $stmt = $pdo->prepare('SELECT whatsapp FROM email_data WHERE email = ? AND created_by = ? LIMIT 1');
            $stmt->execute([$email, $reminder['created_by']]);
            $row = $stmt->fetch();

            if ($row && !empty($row['whatsapp'])) {
                $numbers[] = $row['whatsapp'];
            }
        }

        $rawNumbers = implode(',', $numbers);
    }

    $numbers = preg_split('/[\s,;]+/', trim((string) $rawNumbers), -1, PREG_SPLIT_NO_EMPTY);

    if (empty($numbers)) {
        return [
            'success' => false,
            'error' => 'Nomor WhatsApp tujuan kosong.',
        ];
    }

    $attachmentPath = $reminder['attachment_path'] ?? null;

    $fileUrl = null;
    if ($attachmentPath !== null) {
        $baseUrl = ($waConfig['file_base_url'] ?? '') !== '' ? $waConfig['file_base_url'] : '';
        $host = parse_url($baseUrl, PHP_URL_HOST);
        if ($baseUrl !== '' && $host !== null && !in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $fileUrl = rtrim($baseUrl, '/') . '/' . ltrim($attachmentPath, '/');
        }
    }

    $message = buildWhatsAppMessage($reminder, $fileUrl);

    $failures = [];

    foreach ($numbers as $number) {
        try {
            sendWhatsAppMessage($waConfig, $number, $message, $fileUrl, $attachmentPath);
        } catch (Throwable $e) {
            $failures[] = $number . ': ' . $e->getMessage();
        }
    }

    if (!empty($failures)) {
        $update = $pdo->prepare("
            UPDATE reminders
            SET status = 'failed', last_error = ?
            WHERE id = ?
        ");
        $update->execute([implode(' | ', $failures), $id]);

        return [
            'success' => false,
            'error' => implode(' | ', $failures),
        ];
    }

    $update = $pdo->prepare("
        UPDATE reminders
        SET status = 'done', sent_at = NOW(), last_error = NULL
        WHERE id = ?
    ");
    $update->execute([$id]);

    return [
        'success' => true,
    ];
}
