<?php
declare(strict_types=1);

$secretFile = __DIR__ . '/../storage/.cron_secret';

if (!is_dir(dirname($secretFile))) {
    mkdir(dirname($secretFile), 0750, true);
}

if (file_exists($secretFile)) {
    $cronSecret = trim(file_get_contents($secretFile));
} else {
    $cronSecret = bin2hex(random_bytes(16));
    file_put_contents($secretFile, $cronSecret, LOCK_EX);
}

return [
    'cron_secret'    => getenv('APP_CRON_SECRET') ?: $cronSecret,
    'app_url'        => getenv('APP_URL') ?: 'https://appreminder.zya.me',
    'github' => [
        'repository'  => getenv('GITHUB_REPOSITORY') ?: '',
        'token'       => getenv('GITHUB_TOKEN') ?: '',
        'workflow_id' => getenv('GITHUB_WORKFLOW_ID') ?: 'reminder.yml',
    ],
];
