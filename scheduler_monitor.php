<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
$appConfig = require __DIR__ . '/config/app.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();
requireAdmin();

$githubConfig = getGithubConfig($pdo);
$repo   = $githubConfig['github_repository'] ?? '';
$token  = $githubConfig['github_token'] ?? '';
$wfId   = $githubConfig['github_workflow_id'] ?? 'reminder.yml';

function fetchGithubApi(string $url, string $token): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => [
            'Accept: application/vnd.github+json',
            'X-GitHub-Api-Version: 2022-11-28',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT         => 10,
        CURLOPT_FAILONERROR     => false,
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($http === 401 || $http === 403) {
        return ['_error' => 'GitHub API auth failed', '_code' => $http];
    }
    if ($http >= 400) {
        return ['_error' => 'HTTP ' . $http, '_code' => $http];
    }
    return json_decode($body, true);
}

$github = $appConfig['github'] ?? [];
$repo   = $github['repository'] ?? '';
$token  = $github['token'] ?? '';
$wfId   = $github['workflow_id'] ?? 'reminder.yml';

$apiBase = 'https://api.github.com/repos';
$workflowRuns = null;
$lastRun      = null;
$errorMessage = '';
$lastSync     = null;

if ($repo && $token) {
    $url = $apiBase . '/' . $repo . '/actions/workflows/' . rawurlencode($wfId) . '/runs';
    $workflowRuns = fetchGithubApi($url, $token);
    $lastSync     = date('Y-m-d H:i:s');

    if (is_array($workflowRuns) && isset($workflowRuns['_error'])) {
        $errorMessage = $workflowRuns['_error'];
        $workflowRuns  = null;
    }

    if (is_array($workflowRuns) && !empty($workflowRuns['workflow_runs'])) {
        $lastRun = $workflowRuns['workflow_runs'][0];
    }
} else {
    $errorMessage = 'GitHub repository atau token belum dikonfigurasi.';
}

function fmtDateTime(string $dt): string
{
    return date('Y-m-d H:i:s', strtotime(str_replace('Z', '+00:00', $dt)));
}

function durationSeconds(?string $start, ?string $end): string
{
    if (!$start || !$end) return '-';
    $s = strtotime(str_replace('Z', '+00:00', $start));
    $e = strtotime(str_replace('Z', '+00:00', $end));
    if (!$s || !$e) return '-';
    return number_format(($e - $s)) . 's';
}

function badgeColor(string $status): string
{
    return match ($status) {
        'success'  => 'status-done',
        'failure'  => 'status-overdue',
        'in_progress' => 'status-today',
        default    => '',
    };
}

function statusLabel(string $status): string
{
    return match ($status) {
        'success'     => 'SUCCESS',
        'failure'     => 'FAILED',
        'in_progress' => 'RUNNING',
        default       => ucfirst($status),
    };
}

pageHeader('Monitor GitHub Actions');
?>
<section class="page-title">
    <div>
        <h1><i class="fa-solid fa-github me-2"></i>Monitor GitHub Actions Scheduler</h1>
        <p class="muted">Status dan statistik workflow scheduler App Reminder</p>
    </div>
</section>

<?php if ($errorMessage): ?>
    <div class="panel mb-4" style="padding:20px 24px;">
        <div class="alert alert-warning mb-0">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($errorMessage) ?>
        </div>
        <?php if (!$repo || !$token): ?>
            <p class="small text-muted mt-2 mb-0">
                <i class="fa-solid fa-cog me-1"></i>
                Atur environment variable di hosting: <code>GITHUB_REPOSITORY</code> (format: owner/repo), <code>GITHUB_TOKEN</code>, <code>GITHUB_WORKFLOW_ID</code>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div class="stat" style="--stat-accent: #2563eb;">
        <div class="stat-icon"><i class="fa-solid fa-play"></i></div>
        <span>Last Status</span>
        <strong><?= $lastRun ? statusLabel($lastRun['conclusion'] ?? ($lastRun['status'] ?? 'unknown')) : '-' ?></strong>
    </div>
    <div class="stat" style="--stat-accent: #059669;">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <span>Last Run</span>
        <strong style="font-size: 15px;"><?= $lastRun ? fmtDateTime($lastRun['created_at']) : '-' ?></strong>
    </div>
    <div class="stat" style="--stat-accent: #dc2626;">
        <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
        <span>Total Runs (20 recent)</span>
        <strong><?= is_array($workflowRuns) && isset($workflowRuns['total_count']) ? $workflowRuns['total_count'] : '-' ?></strong>
    </div>
    <div class="stat" style="--stat-accent: #7c3aed;">
        <div class="stat-icon"><i class="fa-solid fa-sync"></i></div>
        <span>Last Sync</span>
        <strong style="font-size: 15px;"><?= e($lastSync ?? '-') ?></strong>
    </div>
</div>

<?php if (is_array($workflowRuns) && !empty($workflowRuns['workflow_runs'])): ?>
    <div class="panel">
        <div class="section-title">
            <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">Riwayat Workflow (20 terbaru)</h3>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Waktu Mulai</th>
                        <th>Waktu Selesai</th>
                        <th>Durasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($workflowRuns['workflow_runs'], 0, 20) as $i => $run): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><span class="badge <?= badgeColor($run['conclusion'] ?? ($run['status'] ?? '')) ?>"><?= statusLabel($run['conclusion'] ?? ($run['status'] ?? 'unknown')) ?></span></td>
                            <td><?= fmtDateTime($run['created_at']) ?></td>
                            <td><?= $run['updated_at'] ? fmtDateTime($run['updated_at']) : '-' ?></td>
                            <td><?= durationSeconds($run['created_at'], $run['updated_at']) ?></td>
                            <td>
                                <?php if (!empty($run['html_url'])): ?>
                                    <a href="<?= e($run['html_url']) ?>" target="_blank" class="btn btn-small btn-secondary">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Buka di GitHub
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($lastRun && isset($lastRun['id'])): ?>
        <?php
        $logUrl = $apiBase . '/' . $repo . '/actions/runs/' . $lastRun['id'] . '/logs';
        ?>
        <div class="panel mt-4">
            <div class="section-title">
                <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">Log Terakhir</h3>
            </div>
            <div style="padding:16px; background:#f8fafc; border-radius:8px; font-size:12px; color:#334155; max-height:320px; overflow-y:auto;">
                <p class="mb-2">Untuk melihat log penuh eksekusi ini, buka di browser:</p>
                <a href="<?= e($run['html_url'] ?? $logUrl) ?>" target="_blank" class="btn btn-small btn-primary">
                    <i class="fa-solid fa-file-text me-1"></i> Buka Halaman Run di GitHub
                </a>
            </div>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php if (!$errorMessage): ?>
        <div class="panel" style="padding:24px; text-align:center;">
            <i class="fa-solid fa-inbox fa-2x text-muted mb-2"></i>
            <p class="text-muted">Tidak ada workflow run ditemukan.</p>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="panel mt-4">
    <div class="section-title">
        <h3 style="margin:0;font-size:16px;font-weight:700;color:#0f172a;">Log Sistem (cron_trigger_access.log)</h3>
    </div>
    <div style="padding:16px; background:#1e293b; border-radius:8px; font-family:'Courier New',monospace; font-size:12px; color:#94a3b8; max-height:320px; overflow-y:auto;">
        <?php
        $logFile = __DIR__ . '/logs/cron_trigger_access.log';
        if (file_exists($logFile)) {
            $lines = readLogFile($logFile);
            foreach (array_slice(array_filter($lines), -50) as $line) {
                echo '<div style="white-space:pre-wrap;">' . e($line) . '</div>';
            }
        } else {
            echo '<span class="text-muted">Log file belum tersedia.</span>';
        }
        ?>
    </div>
</div>
<?php pageFooter(); ?>
