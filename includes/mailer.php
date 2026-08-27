<?php
declare(strict_types=1);

require_once __DIR__ . '/mail_config.php';

function splitEmailList(?string $emails): array
{
    $parts = preg_split('/[\s,;]+/', trim((string)$emails), -1, PREG_SPLIT_NO_EMPTY);

    $emails = [];

    foreach ($parts as $email) {
        $email = trim($email);

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower($email);
        }
    }

    return array_values(array_unique($emails));
}

function encodeHeader(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function smtpRead($socket): string
{
    $response = '';

    while (($line = @fgets($socket, 515)) !== false) {
        $response .= $line;

        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function smtpCommand($socket, string $command, array $expected): string
{
    @fwrite($socket, $command . "\r\n");

    $response = smtpRead($socket);

    $code = (int)substr($response, 0, 3);

    if (!in_array($code, $expected, true)) {
        throw new RuntimeException(trim($response));
    }

    return $response;
}

function buildEmailMessage(
    array $config,
    array $reminder,
    array $to,
    array $cc,
    ?string $attachment
): string {

    $subject = '[Reminder] ' . $reminder['subject'];

    $priorityLabels = [
        'low' => 'Rendah',
        'medium' => 'Sedang',
        'high' => 'Tinggi',
        'urgent' => 'Mendesak',
    ];
    $priorityLabel = $priorityLabels[$reminder['priority'] ?? 'medium'] ?? 'Sedang';

    $body = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1f2937">
        <p>Yth. Bapak/Ibu,</p>
        <p>Berikut reminder yang perlu ditindaklanjuti.</p>
        <table cellpadding="6" style="border-collapse:collapse">
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Perihal</td>
                <td style="color:#1f2937">' . htmlspecialchars($reminder['subject']) . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Department</td>
                <td style="color:#1f2937">' . htmlspecialchars($reminder['department']) . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Sub Departemen</td>
                <td style="color:#1f2937">' . htmlspecialchars($reminder['sub_department'] ?? '-') . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">PIC</td>
                <td style="color:#1f2937">' . htmlspecialchars($reminder['pic'] ?? '-') . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Klasifikasi Kategori</td>
                <td style="color:#1f2937">' . htmlspecialchars($reminder['category'] ?? '-') . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Prioritas</td>
                <td style="color:#1f2937">' . htmlspecialchars($priorityLabel) . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Tanggal Mulai</td>
                <td style="color:#1f2937">' . date('d M Y', strtotime($reminder['start_date'] ?? $reminder['reminder_date'] ?? '')) . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Tanggal Akhir</td>
                <td style="color:#1f2937">' . date('d M Y', strtotime($reminder['end_date'] ?? $reminder['start_date'] ?? $reminder['reminder_date'] ?? '')) . '</td>
            </tr>
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Jam</td>
                <td style="color:#1f2937">' . ($reminder['reminder_time'] ?? '-') . '</td>
            </tr>';

    if (!empty($reminder['message'])) {
        $body .= '
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px;vertical-align:top">Isi Pesan</td>
                <td style="color:#1f2937;white-space:pre-line">' . htmlspecialchars($reminder['message']) . '</td>
            </tr>';
    }

    if (!empty($attachment)) {
        $appConfig = require __DIR__ . '/../config/app.php';
        $appUrl = rtrim($appConfig['app_url'] ?? 'https://appreminder.zya.me', '/');
        $attachmentLink = $appUrl . '/attachment.php?id=' . (int) ($reminder['id'] ?? 0);

        $body .= '
            <tr>
                <td style="font-weight:600;color:#374151;padding-right:12px">Lampiran</td>
                <td>
                    <a href="' . $attachmentLink . '" style="color:#2563eb;font-weight:600;text-decoration:none;">
                        <i class="fa-solid fa-paperclip"></i> Buka Lampiran
                    </a>
                </td>
            </tr>';
    }

    $body .= '
        </table>
        <br>
        <p style="color:#6b7280;font-size:13px">Email ini dikirim secara otomatis oleh sistem Email Reminder.</p>
    </div>';

    $headers = [];

    $headers[] = 'From: ' .
        encodeHeader($config['from_name']) .
        ' <' . $config['from_email'] . '>';

    $headers[] = 'To: ' . implode(', ', $to);

    if (!empty($cc)) {
        $headers[] = 'Cc: ' . implode(', ', $cc);
    }

    if (!empty($reminder['sender_email'])) {
        $headers[] = 'Reply-To: ' . $reminder['sender_email'];
    }

    $headers[] = 'Date: ' . date(DATE_RFC2822);
    $headers[] = 'Subject: ' . encodeHeader($subject);
    $headers[] = 'MIME-Version: 1.0';

    $fullAttachment = null;

    if (!empty($attachment)) {
        $fullAttachment = realpath(__DIR__ . '/../' . $attachment);
    }

    if ($fullAttachment && file_exists($fullAttachment)) {

        $boundary = md5(uniqid());

        $headers[] =
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $message = implode("\r\n", $headers) . "\r\n\r\n";

        $message .= "--$boundary\r\n";

        $message .= "Content-Type:text/html;charset=UTF-8\r\n";

        $message .= "Content-Transfer-Encoding:8bit\r\n\r\n";

        $message .= $body . "\r\n";

        $filename = basename($fullAttachment);

        $mime = mime_content_type($fullAttachment);

        $content = chunk_split(
            base64_encode(file_get_contents($fullAttachment))
        );

        $message .= "--$boundary\r\n";

        $message .= "Content-Type:$mime; name=\"$filename\"\r\n";

        $message .= "Content-Disposition:attachment; filename=\"$filename\"\r\n";

        $message .= "Content-Transfer-Encoding:base64\r\n\r\n";

        $message .= $content . "\r\n";

        $message .= "--$boundary--";

        return $message;
    }

    $headers[] = 'Content-Type:text/html;charset=UTF-8';

    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

function sendReminderEmail(PDO $pdo, int $id): array
{
    $config = loadMailConfig($pdo);

    if (!isMailConfigComplete($config)) {

        return [
            'success' => false,
            'error' => 'SMTP belum dikonfigurasi.'
        ];
    }

    $stmt = $pdo->prepare("SELECT * FROM reminders WHERE id=?");

    $stmt->execute([$id]);

    $reminder = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reminder) {

        return [
            'success'=>false,
            'error'=>'Reminder tidak ditemukan.'
        ];
    }

    if ($reminder['status'] === 'done' && $reminder['send_channel'] !== 'both') {

        return [
            'success'=>false,
            'error'=>'Reminder sudah pernah dikirim.'
        ];
    }

    $to = splitEmailList($reminder['to_emails']);

    $cc = splitEmailList($reminder['cc_emails']);

    if (empty($to)) {

        return [
            'success'=>false,
            'error'=>'Email tujuan kosong.'
        ];
    }

    $host = $config['host'];

    $port = (int)$config['port'];

    $timeout = max(10, (int)$config['timeout']);

    $socket = null;

    try {

        $sslContext = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'allow_self_signed'=> false,
                'verify_depth'     => 5,
            ],
        ]);

        $target = $config['encryption'] == 'ssl'
            ? "ssl://$host"
            : $host;

        $socket = null;

        if ($config['encryption'] === 'ssl') {
            $socket = @stream_socket_client(
                $target,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $sslContext
            );
        } else {
            $socket = @fsockopen(
                $target,
                $port,
                $errno,
                $errstr,
                $timeout
            );
        }

        if (!$socket) {
            throw new RuntimeException($errstr);
        }

        stream_set_timeout($socket, $timeout);
        stream_set_blocking($socket, true);

        smtpRead($socket);

        $appConfigMail = require __DIR__ . '/../config/app.php';
        $ehloHost = preg_replace('#^https?://#', '', $appConfigMail['app_url'] ?? 'appreminder.local');
        $ehloHost = preg_replace('/^www\./', '', $ehloHost);
        smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);

        if ($config['encryption']=='tls') {

            smtpCommand($socket,'STARTTLS',[220]);

            $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;

            $cryptoResult = @stream_socket_enable_crypto(
                $socket,
                true,
                $cryptoMethod
            );

            if ($cryptoResult !== true) {

                $cryptoResult2 = @stream_socket_enable_crypto(
                    $socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
                );

                if ($cryptoResult2 !== true) {
                    throw new RuntimeException('TLS negotiation gagal. OpenSSL error: ' . json_encode(error_get_last()));
                }
            }

            $ehloHost = preg_replace('#^https?://#', '', $appConfigMail['app_url'] ?? 'appreminder.local');
            $ehloHost = preg_replace('/^www\./', '', $ehloHost);
            smtpCommand($socket, 'EHLO ' . $ehloHost, [250]);
        }

        smtpCommand($socket,'AUTH LOGIN',[334]);

        smtpCommand(
            $socket,
            base64_encode($config['username']),
            [334]
        );

        smtpCommand(
            $socket,
            base64_encode($config['password']),
            [235]
        );

        smtpCommand(
            $socket,
            'MAIL FROM:<' . $config['from_email'] . '>',
            [250]
        );

        foreach(array_merge($to,$cc) as $mail){

            smtpCommand(
                $socket,
                'RCPT TO:<'.$mail.'>',
                [250,251]
            );

        }

        smtpCommand($socket,'DATA',[354]);

        $message = buildEmailMessage(
            $config,
            $reminder,
            $to,
            $cc,
            $reminder['attachment_path']
        );

        fwrite($socket,$message."\r\n.\r\n");

        smtpRead($socket);

        smtpCommand($socket,'QUIT',[221]);

        fclose($socket);

        $update = $pdo->prepare("
            UPDATE reminders
            SET
                status='done',
                sent_at=NOW(),
                last_error=NULL
            WHERE id=?
        ");

        $update->execute([$id]);

        error_log("Reminder #$id berhasil dikirim");

        return [
            'success'=>true
        ];

    } catch(Throwable $e){

        if(is_resource($socket)){
            fclose($socket);
        }

        $update = $pdo->prepare("
            UPDATE reminders
            SET
                status='failed',
                last_error=?
            WHERE id=?
        ");

        $update->execute([
            $e->getMessage(),
            $id
        ]);

        error_log("Reminder #$id gagal : ".$e->getMessage());

        return [
            'success'=>false,
            'error'=>$e->getMessage()
        ];
    }
}