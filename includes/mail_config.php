<?php
declare(strict_types=1);

function defaultMailConfig(): array
{
    return require __DIR__ . '/../config/mail.php';
}

function loadMailConfig(?PDO $pdo = null): array
{
    $config = defaultMailConfig();

    if ($pdo) {
        try {
            $stmt = $pdo->query('SELECT * FROM smtp_settings WHERE id = 1');
            $settings = $stmt->fetch();

            if ($settings) {
                $config['host'] = $settings['host'] ?: $config['host'];
                $config['port'] = (int) ($settings['port'] ?: $config['port']);
                $config['encryption'] = $settings['encryption'] ?: $config['encryption'];
                $config['username'] = $settings['username'] ?: $config['username'];
                $config['password'] = $settings['password'] ?: $config['password'];
                $config['from_email'] = $settings['from_email'] ?: $settings['username'];
                $config['from_name'] = $settings['from_name'] ?: $config['from_name'];
                $config['timeout'] = (int) ($settings['timeout'] ?: $config['timeout']);
            }
        } catch (Throwable $e) {
            // If the settings table is not installed yet, fall back to config/mail.php.
        }
    }

    $config['from_email'] = $config['from_email'] ?: $config['username'];

    return $config;
}

function isMailConfigComplete(array $config): bool
{
    return $config['host'] !== ''
        && (int) $config['port'] > 0
        && $config['username'] !== ''
        && $config['password'] !== ''
        && strpos($config['username'], 'ISI_EMAIL') === false
        && strpos($config['password'], 'ISI_APP_PASSWORD') === false;
}
