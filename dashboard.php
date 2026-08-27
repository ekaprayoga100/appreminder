<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/reminder_helpers.php';

requireLogin();

$isAdmin = (currentUser()['role'] ?? '') === 'admin';

$today = date('Y-m-d');
$perPage = 15;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END), 0) as done,
        COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed_count,
        COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
        COALESCE(SUM(CASE WHEN status NOT IN ('done','failed') AND end_date < ? THEN 1 ELSE 0 END), 0) as expired_count,
        COALESCE(SUM(CASE WHEN status NOT IN ('done','failed') AND start_date <= ? AND end_date >= ? THEN 1 ELSE 0 END), 0) as today_count,
        COALESCE(SUM(CASE WHEN status NOT IN ('done','failed') AND start_date > ? THEN 1 ELSE 0 END), 0) as upcoming_count,
        COALESCE(SUM(attachment_size), 0) as total_size
    FROM reminders
");
$statsStmt->execute([$today, $today, $today, $today]);
$stats = $statsStmt->fetch();
$total = (int) $stats['total'];
$done = (int)$stats['done'];
$failed = (int)$stats['failed_count'];
$pending = (int)$stats['pending_count'];
$overdue = (int)$stats['expired_count'];
$dueToday = (int)$stats['today_count'];
$upcoming = (int)$stats['upcoming_count'];
$totalSize = (int)$stats['total_size'];

$totalPages = (int) ceil($total / $perPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$search = trim((string) ($_GET['search'] ?? ''));
$channelFilter = trim((string) ($_GET['channel'] ?? ''));

$where = 'WHERE 1=1';
$params = [];

if ($search !== '') {
    $where .= ' AND (r.subject LIKE :search OR r.department LIKE :search OR r.sub_department LIKE :search OR r.pic LIKE :search OR r.category LIKE :search OR u.name LIKE :search OR r.to_emails LIKE :search)';
    $params[':search'] = "%{$search}%";
}

if ($channelFilter !== '') {
    $where .= ' AND r.send_channel = :channel';
    $params[':channel'] = $channelFilter;
}

$stmt = $pdo->prepare("
    SELECT r.*, u.name AS creator_name
    FROM reminders r
    JOIN users u ON u.id = r.created_by
    {$where}
    ORDER BY r.start_date ASC,
             r.reminder_time ASC,
             r.id DESC
    LIMIT :limit OFFSET :offset
");
if ($search !== '') {
    $stmt->bindValue(':search', "%{$search}%");
}

if ($channelFilter !== '') {
    $stmt->bindValue(':channel', $channelFilter);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reminders = $stmt->fetchAll();

$filterQuery = [];
if ($search !== '') {
    $filterQuery['search'] = $search;
}
if ($channelFilter !== '') {
    $filterQuery['channel'] = $channelFilter;
}
$filterString = http_build_query($filterQuery);

function reminderStatus(array $item, string $today): array
{
    if ($item['status'] === 'done') {
        return ['Terkirim', 'status-done'];
    }

    if ($item['status'] === 'failed') {
        return ['Gagal', 'status-overdue'];
    }

    if ($item['end_date'] < $today) {
        return ['Terlambat', 'status-overdue'];
    }

    if ($item['start_date'] <= $today && $item['end_date'] >= $today) {
        return ['Hari Ini', 'status-today'];
    }

    return ['Terjadwal', 'status-upcoming'];
}

pageHeader('Dashboard');
?>
<section class="page-title">
    <div>
         <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard Reminder</h1>
        <p class="muted">Ringkasan semua reminder dan progress pengingat email & whatsapp.</p>
    </div>
    <a class="btn btn-primary" href="create_reminder.php"><i class="fa-solid fa-plus"></i> Buat Reminder</a>
    <?php if ($isAdmin): ?>
        <form method="post" action="delete_all_reminders.php" onsubmit="return confirm('Hapus SEMUA reminder? Tindakan ini tidak bisa dibatalkan.');" style="display:inline;">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-trash"></i> Hapus Semua</button>
        </form>
    <?php endif; ?>
</section>

<?php if (popFlash('auto_new_id')): ?>
    <script>showToast('Berhasil', 'Reminder baru dibuat.', 'info');</script>
<?php endif; ?>

<?php if (popFlash('scheduled')): ?>
    <script>showToast('Dijadwalkan', 'Reminder akan dikirim otomatis sesuai tanggal dan jam yang ditentukan.', 'info');</script>
<?php endif; ?>

<form class="panel form-grid mb-4" method="get" action="dashboard.php">
    <div class="full">
        <div class="input-group" style="max-width: 360px;">
            <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
            <input type="text" class="form-control" name="search" value="<?= e($search) ?>" placeholder="Cari perihal, departemen, atau pembuat...">
        </div>
    </div>
    <?php if ($search !== ''): ?>
    <div>
        <a class="btn btn-secondary" href="dashboard.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
    </div>
    <?php endif; ?>
</form>

<section class="stats-grid" id="statsGrid">
    <div class="stat-card stat-total">
        <div class="stat-icon"><i class="fa-solid fa-layer-group"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $total ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    <div class="stat-card stat-pending">
        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $pending ?></div>
            <div class="stat-label">Menunggu</div>
        </div>
    </div>
    <div class="stat-card stat-done">
        <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $done ?></div>
            <div class="stat-label">Terkirim</div>
        </div>
    </div>
    <div class="stat-card stat-failed">
        <div class="stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $failed ?></div>
            <div class="stat-label">Gagal</div>
        </div>
    </div>
    <div class="stat-card stat-today">
        <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $dueToday ?></div>
            <div class="stat-label">Hari Ini</div>
        </div>
    </div>
    <div class="stat-card stat-overdue">
        <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $overdue ?></div>
            <div class="stat-label">Terlambat</div>
        </div>
    </div>
    <div class="stat-card stat-upcoming">
        <div class="stat-icon"><i class="fa-solid fa-calendar-plus"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= $upcoming ?></div>
            <div class="stat-label">Akan Datang</div>
        </div>
    </div>
    <div class="stat-card stat-size">
        <div class="stat-icon"><i class="fa-solid fa-file"></i></div>
        <div class="stat-body">
            <div class="stat-value"><?= e(formatBytes($totalSize)) ?></div>
            <div class="stat-label">Total Lampiran</div>
        </div>
    </div>
</section>

<style>
    #statsGrid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 14px;
        margin-bottom: 20px;
    }
    .stat-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 14px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 18px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.02);
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: default;
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        opacity: 0.7;
        transition: opacity 0.3s ease, height 0.3s ease;
    }
    .stat-card::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(99,102,241,0.06) 0%, transparent 60%);
        opacity: 0;
        transition: opacity 0.3s ease;
        pointer-events: none;
        border-radius: 14px;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(0,0,0,0.12), 0 4px 8px -2px rgba(0,0,0,0.06);
        border-color: #cbd5e1;
    }
    .stat-card:hover::before {
        opacity: 1;
        height: 4px;
    }
    .stat-card:hover::after {
        opacity: 1;
    }
    .stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        z-index: 1;
    }
    .stat-card:hover .stat-icon {
        transform: scale(1.08);
    }
    .stat-body {
        display: flex;
        flex-direction: column;
        gap: 3px;
        min-width: 0;
        position: relative;
        z-index: 1;
    }
    .stat-value {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
        letter-spacing: -0.5px;
        transition: color 0.3s ease;
    }
    .stat-label {
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .stat-total .stat-icon { background:#f1f5f9; color:#0f172a; box-shadow: 0 2px 4px rgba(15,23,42,0.08); }
    .stat-total::before { background:#0f172a; }
    .stat-total:hover .stat-value { color: #0f172a; }
    .stat-pending .stat-icon { background:#eef2ff; color:#6366f1; box-shadow: 0 2px 4px rgba(99,102,241,0.15); }
    .stat-pending::before { background:#6366f1; }
    .stat-pending:hover .stat-value { color: #6366f1; }
    .stat-done .stat-icon { background:#f0fdf4; color:#16a34a; box-shadow: 0 2px 4px rgba(22,163,74,0.15); }
    .stat-done::before { background:#16a34a; }
    .stat-done:hover .stat-value { color: #16a34a; }
    .stat-failed .stat-icon { background:#fef2f2; color:#dc2626; box-shadow: 0 2px 4px rgba(220,38,38,0.15); }
    .stat-failed::before { background:#dc2626; }
    .stat-failed:hover .stat-value { color: #dc2626; }
    .stat-today .stat-icon { background:#fff7ed; color:#d97706; box-shadow: 0 2px 4px rgba(217,119,6,0.15); }
    .stat-today::before { background:#d97706; }
    .stat-today:hover .stat-value { color: #d97706; }
    .stat-overdue .stat-icon { background:#fef2f2; color:#dc2626; box-shadow: 0 2px 4px rgba(220,38,38,0.15); }
    .stat-overdue::before { background:#dc2626; }
    .stat-overdue:hover .stat-value { color: #dc2626; }
    .stat-upcoming .stat-icon { background:#ecfeff; color:#0891b2; box-shadow: 0 2px 4px rgba(8,145,178,0.15); }
    .stat-upcoming::before { background:#0891b2; }
    .stat-upcoming:hover .stat-value { color: #0891b2; }
    .stat-size .stat-icon { background:#f8fafc; color:#475569; box-shadow: 0 2px 4px rgba(71,85,105,0.08); }
    .stat-size::before { background:#475569; }
    .stat-size:hover .stat-value { color: #475569; }

    @media (max-width: 1024px) {
        #statsGrid {
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
    }
    @media (max-width: 768px) {
        #statsGrid {
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat-card {
            padding: 14px;
            gap: 10px;
            border-radius: 12px;
        }
        .stat-icon {
            width: 36px;
            height: 36px;
            font-size: 14px;
            border-radius: 10px;
        }
        .stat-value {
            font-size: 20px;
        }
        .stat-label {
            font-size: 10px;
        }
    }
    @media (max-width: 480px) {
        #statsGrid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .stat-card {
            padding: 12px;
            gap: 8px;
            border-radius: 10px;
        }
        .stat-icon {
            width: 32px;
            height: 32px;
            font-size: 13px;
            border-radius: 8px;
        }
        .stat-value {
            font-size: 18px;
        }
        .stat-label {
            font-size: 9px;
            letter-spacing: 0.3px;
        }
    }
    @media (max-width: 360px) {
        #statsGrid {
            grid-template-columns: 1fr;
            gap: 8px;
        }
        .stat-card {
            padding: 14px;
        }
    }

    .reminder-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow: hidden !important;
        box-sizing: border-box;
    }
    .compact-table.reminder-table {
        width: 100%;
        max-width: 100%;
        min-width: 0 !important;
        table-layout: fixed;
        overflow-wrap: anywhere;
        border-collapse: collapse;
    }
    .compact-table.reminder-table td,
    .compact-table.reminder-table th {
        box-sizing: border-box;
        padding: 6px 5px;
        font-size: 11px;
        vertical-align: middle;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        min-width: 0;
    }
    .compact-table.reminder-table td.ellipsis,
    .compact-table.reminder-table th.ellipsis {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .compact-table.reminder-table td.checkbox-cell {
        width: 32px;
        padding: 0 5px;
    }
    .compact-table.reminder-table td.expand-cell {
        width: 28px;
        padding: 0 2px;
    }
    .compact-table.reminder-table td.ellipsis.text-cell {
        width: 30%;
    }
    .compact-table.reminder-table td.dept-cell {
        width: 20%;
    }
    .compact-table.reminder-table td.pic-cell {
        width: 15%;
    }
    .compact-table.reminder-table td.priority-cell {
        width: 15%;
    }
    .compact-table.reminder-table td.action-cell {
        width: 100px;
        padding: 0 5px;
    }
    .compact-table.reminder-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
    }
    .compact-table.reminder-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .compact-table.reminder-table td.empty {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
    }
    .btn-expand {
        border: none;
        background: none;
        padding: 2px 6px;
        cursor: pointer;
        font-size: 14px;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
    }
    .btn-expand:hover {
        color: #0f172a;
    }
    .detail-row td {
        padding: 0;
        border-bottom: none;
        background: #f8fafc;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        padding: 12px;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .detail-label {
        font-size: 10px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-bottom: 2px;
    }
    .detail-value {
        color: #0f172a;
        font-size: 11px;
        word-break: break-word;
    }
    .detail-value.ellipsis {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .error-text {
        color: #dc2626;
        font-size: 10px;
    }
    .reminder-date-range {
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }
    .action-stack .inline-form {
        margin-bottom: 4px;
    }
    .action-stack .inline-form:last-child {
        margin-bottom: 0;
    }
    .tab-link {
        padding: 10px 16px; font-size: 13px; font-weight: 600; color: #64748b;
        text-decoration: none; white-space: nowrap; border-bottom: 2px solid transparent;
        transition: all 0.2s ease;
    }
    .tab-link:hover { color: #6366f1; border-bottom-color: #c7d2fe; }
    .tab-link.active { color: #6366f1; border-bottom-color: #6366f1; }
    @media (max-width: 768px) {
        .reminder-table-wrap {
            width: 100%;
            max-width: 100%;
            overflow: visible;
            box-sizing: border-box;
        }

        .compact-table.reminder-table {
            min-width: 0 !important;
            table-layout: auto;
        }

        .compact-table.reminder-table thead {
            display: none;
        }

        .compact-table.reminder-table tbody {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .reminder-main-row {
            display: grid;
            grid-template-columns: 28px 1fr auto;
            grid-template-rows: auto auto auto;
            gap: 3px 8px;
            width: 100%;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }

        .reminder-main-row td {
            padding: 0 !important;
            border: none !important;
            min-width: 0;
        }

        .reminder-main-row td:nth-child(1) {
            display: none;
        }

        .reminder-main-row td:nth-child(2) {
            grid-column: 1;
            grid-row: 1 / 3;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 2px;
        }

        .reminder-main-row td:nth-child(3) {
            grid-column: 2;
            grid-row: 1;
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
        }

        .reminder-main-row td:nth-child(4) {
            grid-column: 2;
            grid-row: 2;
            font-size: 12px;
            color: #475569;
        }

        .reminder-main-row td:nth-child(5) {
            display: none;
        }

        .reminder-main-row td:nth-child(6) {
            grid-column: 3;
            grid-row: 1 / 3;
            display: flex;
            align-items: flex-start;
            justify-content: flex-end;
        }

        .reminder-main-row td:nth-child(7) {
            grid-column: 1 / -1;
            grid-row: 3;
            display: flex;
            justify-content: flex-end;
        }

        .reminder-main-row .action-stack {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
            width: 100%;
        }

        .reminder-main-row .action-stack .btn {
            height: 32px;
            min-height: 32px;
            padding: 0 10px;
            font-size: 12px;
            border-radius: 6px;
        }

        .reminder-main-row .action-stack .btn i {
            font-size: 11px;
            margin-right: 4px;
        }

        .reminder-main-row .action-stack .inline-form {
            margin: 0;
        }

        .mobile-row-meta {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #475569;
        }


        .btn-expand {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            font-size: 12px;
        }

        .btn-expand:hover {
            background: #e2e8f0;
        }

        .mobile-row-meta .mobile-meta-sep {
            color: #cbd5e1;
        }

        .reminder-date-range {
            font-size: 10px;
            color: #64748b;
            margin-top: 1px;
            white-space: normal !important;
            overflow-wrap: anywhere !important;
            word-break: break-word !important;
        }

        /* Detail row inside card */
        .detail-row {
            display: block;
            width: 100%;
            box-sizing: border-box;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            margin-top: 6px;
        }

        .detail-row td {
            display: block;
            width: 100%;
            padding: 0 !important;
            border: none !important;
        }

        .detail-grid {
            grid-template-columns: 1fr;
            gap: 6px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 1px;
            padding: 0 !important;
        }

        .detail-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
        }

        .detail-value {
            font-size: 13px;
            color: #0f172a;
            word-break: break-word;
        }

        /* Empty row */
        tbody tr:not(.reminder-main-row):not(.detail-row) {
            display: block;
            width: 100%;
        }
        tbody tr:not(.reminder-main-row):not(.detail-row) td {
            display: block;
            width: 100%;
        }
    }</style>

<form class="panel form-grid mb-3" method="post" action="bulk_delete_reminders.php" id="bulkActionForm" onsubmit="return confirm('Hapus reminder yang dipilih?');">
    <?= csrfField() ?>
    <?php if ($search !== ''): ?>
        <input type="hidden" name="search" value="<?= e($search) ?>">
    <?php endif; ?>
    <div class="full d-flex justify-content-between align-items-center">
        <span class="text-muted small" id="selectedCount">0 reminder dipilih</span>
        <button type="submit" class="btn btn-danger btn-small" id="bulkDeleteBtn" disabled>
            <i class="fa-solid fa-trash"></i> Hapus yang Dipilih
        </button>
    </div>
    <div>
        <select class="form-select" name="channel" onchange="this.form.submit()">
            <option value="">Semua Channel</option>
            <option value="email" <?= ($_GET['channel'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
            <option value="whatsapp" <?= ($_GET['channel'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
            <option value="both" <?= ($_GET['channel'] ?? '') === 'both' ? 'selected' : '' ?>>Keduanya</option>
        </select>
    </div>
</form>

<section class="panel" style="overflow: hidden;">
    <div class="table-wrap reminder-table-wrap">
        <table class="compact-table reminder-table">
            <thead>
                <tr>
                    <th style="width: 32px;"><input type="checkbox" id="selectAll" class="form-check-input" style="width:14px;height:14px;accent-color:#6366f1;cursor:pointer;"></th>
                    <th style="width: 28px;">&nbsp;</th>
                    <th class="text-cell ellipsis">Perihal</th>
                    <th class="dept-cell">Department</th>
                    <th class="pic-cell ellipsis">PIC</th>
                    <th class="priority-cell">Prioritas</th>
                    <th class="action-cell">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$reminders): ?>
                <tr>
                    <td colspan="7" class="empty">Belum ada reminder.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($reminders as $item): ?>
                <?php [$label, $class] = reminderStatus($item, $today); ?>
                <?php
                    $priorityLabels = [
                        'low' => ['Rendah', 'status-upcoming'],
                        'medium' => ['Sedang', 'status-today'],
                        'high' => ['Tinggi', 'status-overdue'],
                        'urgent' => ['Mendesak', 'status-overdue'],
                    ];
                    $pr = $priorityLabels[$item['priority'] ?? 'medium'] ?? $priorityLabels['medium'];

                    $channel = $item['send_channel'] ?? 'email';
                    $channelIcons = '';
                    if ($channel === 'both'):
                        $channelIcons = '<i class="fa-solid fa-envelope" style="color:#6366f1;margin-right:4px;" title="Email"></i><i class="fa-brands fa-whatsapp" style="color:#25D366;" title="WhatsApp"></i>';
                    elseif ($channel === 'whatsapp'):
                        $channelIcons = '<i class="fa-brands fa-whatsapp" style="color:#25D366;" title="WhatsApp"></i>';
                    else:
                        $channelIcons = '<i class="fa-solid fa-envelope" style="color:#6366f1;" title="Email"></i>';
                    endif;

                    $attachmentHtml = $item['attachment_path']
                        ? '<a href="attachment.php?id=' . (int)$item['id'] . '" target="_blank">Buka</a>'
                        : '-';
                    $attachmentSize = $item['attachment_path'] ? e(formatBytes((int) ($item['attachment_size'] ?? 0))) : '-';

                    $sentHtml = '';
                    if (!empty($item['sent_at'])):
                        $sentHtml = e(date('d M Y H:i', strtotime($item['sent_at'])));
                    elseif (!empty($item['last_error'])):
                        $sentHtml = '<span class="error-text">' . e($item['last_error']) . '</span>';
                    endif;

                    $startDate = new DateTime($item['start_date']);
                    $endDate = new DateTime($item['end_date']);
                    $todayDt = new DateTime('today');
                    $remainingDays = (int) $todayDt->diff($endDate)->format('%a');
                    $dateRangeLabel = $startDate->format('d M') . ' - ' . $endDate->format('d M Y');
                    $remainingLabel = $remainingDays > 0 ? $remainingDays . ' hari lagi' : 'Hari ini';
                ?>
                <tr class="reminder-main-row" data-id="<?= (int) $item['id'] ?>">
                    <td class="checkbox-cell" data-label="Pilih">
                        <input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?= (int) $item['id'] ?>" style="width:14px;height:14px;accent-color:#6366f1;cursor:pointer;">
                    </td>
                    <td class="expand-cell" data-label="Detail">
                        <button type="button" class="btn-expand" onclick="toggleDetail(this)" data-id="<?= (int) $item['id'] ?>">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </td>
                    <td class="text-cell ellipsis reminder-subject" title="<?= e($item['subject']) ?>">
                        <?= e($item['subject']) ?>
                        <div class="reminder-date-range"><?= e($dateRangeLabel) ?> · <?= e($remainingLabel) ?></div>
                    </td>
                    <td class="dept-cell ellipsis">
                        <div class="mobile-row-meta">
                            <span class="mobile-meta-item"><?= e($item['department']) ?></span>
                            <span class="mobile-meta-sep">·</span>
                            <span class="mobile-meta-item"><?= e($item['pic'] ?: '-') ?></span>
                        </div>
                    </td>
                    <td class="pic-cell ellipsis"><?= e($item['pic'] ?: '-') ?></td>
                    <td class="priority-cell"><span class="badge <?= $pr[1] ?>"><?= e($pr[0]) ?></span></td>
                    <td class="action-cell">
                        <div class="action-stack">
                            <?php if ($item['status'] === 'failed'): ?>
                                <form method="post" action="send_reminder.php" class="inline-form" onsubmit="return confirm('Kirim ulang reminder ini?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <button class="btn btn-small btn-warning" type="submit"><i class="fa-solid fa-rotate-right"></i> Kirim Ulang</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($item['status'] !== 'done'): ?>
                                <a class="btn btn-small btn-secondary" href="edit_reminder.php?id=<?= (int) $item['id'] ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                            <?php endif; ?>
                            <form method="post" action="delete_reminder.php" class="inline-form" onsubmit="return confirm('Hapus reminder ini?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button class="btn btn-small btn-danger" type="submit"><i class="fa-solid fa-trash"></i> Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr class="detail-row" data-id="<?= (int) $item['id'] ?>" style="display:none;">
                    <td colspan="7" class="p-0">
                        <div class="detail-grid">
                            <div class="detail-item"><span class="detail-label">Perihal</span><span class="detail-value ellipsis" title="<?= e($item['subject']) ?>"><?= e($item['subject']) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Department</span><span class="detail-value"><?= e($item['department']) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Sub Department</span><span class="detail-value"><?= e($item['sub_department'] ?: '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">PIC</span><span class="detail-value"><?= e($item['pic'] ?: '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Kategori</span><span class="detail-value"><?= e($item['category'] ?: '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">Prioritas</span><span class="detail-value"><span class="badge <?= $pr[1] ?>"><?= e($pr[0]) ?></span></span></div>
                            <div class="detail-item"><span class="detail-label">Tanggal Mulai</span><span class="detail-value"><?= e(date('d M Y', strtotime($item['start_date']))) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Tanggal Akhir</span><span class="detail-value"><?= e(date('d M Y', strtotime($item['end_date']))) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Jam</span><span class="detail-value"><?= e(substr($item['reminder_time'],0,5)) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Pengirim</span><span class="detail-value ellipsis" title="<?= e($item['sender_email']) ?>"><?= e($item['sender_email'] ?: '-') ?></span></div>
                            <div class="detail-item"><span class="detail-label">To</span><span class="detail-value" title="<?= nl2br(e(str_replace(',', "\n", $item['to_emails']))) ?>"><?= e(str_replace(',', ', ', $item['to_emails'])) ?></span></div>
                            <div class="detail-item"><span class="detail-label">Cc</span><span class="detail-value" title="<?= $item['cc_emails'] ? nl2br(e(str_replace(',', "\n", $item['cc_emails']))) : '-' ?>"><?= $item['cc_emails'] ? e(str_replace(',', ', ', $item['cc_emails'])) : '-' ?></span></div>
                            <div class="detail-item"><span class="detail-label">Channel</span><span class="detail-value"><?= $channelIcons ?></span></div>
                            <div class="detail-item"><span class="detail-label">Lampiran</span><span class="detail-value"><?= $attachmentHtml ?></span></div>
                            <div class="detail-item"><span class="detail-label">Size</span><span class="detail-value"><?= $attachmentSize ?></span></div>
                            <div class="detail-item"><span class="detail-label">Progress / Status</span><span class="detail-value"><span class="badge <?= e($class) ?>"><?= e($label) ?></span></span></div>
                            <div class="detail-item"><span class="detail-label">Dikirim</span><span class="detail-value"><?= $sentHtml ?: '-' ?></span></div>
                            <div class="detail-item"><span class="detail-label">Dibuat Oleh</span><span class="detail-value"><?= e($item['creator_name']) ?></span></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($totalPages > 1): ?>
<nav aria-label="Page navigation" class="mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <span class="text-muted small">
            Menampilkan <?= ($offset + 1) ?>-<?= min($offset + $perPage, $total) ?> dari <?= $total ?> data
        </span>
        <ul class="pagination mb-0">
            <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page - 1 ?><?= $filterString !== '' ? '&' . $filterString : '' ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&laquo;</span>
                </li>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            if ($startPage > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=1<?= $filterString !== '' ? '&' . $filterString : '' ?>">1</a>
                </li>
                <?php if ($startPage > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <?php if ($i === $page): ?>
                    <li class="page-item active">
                        <span class="page-link"><?= $i ?></span>
                    </li>
                <?php else: ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $i ?><?= $filterString !== '' ? '&' . $filterString : '' ?>"><?= $i ?></a>
                    </li>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                <?php endif; ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $totalPages ?><?= $filterString !== '' ? '&' . $filterString : '' ?>"><?= $totalPages ?></a>
                </li>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?= $page + 1 ?><?= $filterString !== '' ? '&' . $filterString : '' ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link" aria-hidden="true">&raquo;</span>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<?php endif; ?>

<script>
function toggleDetail(button) {
    var mainRow = button.closest('tr');
    var id = mainRow.getAttribute('data-id');
    var detailRow = document.querySelector('.detail-row[data-id="' + id + '"]');
    var icon = button.querySelector('i');

    if (window.innerWidth <= 768) {
        if (detailRow.style.display === 'block') {
            detailRow.style.display = 'none';
            icon.className = 'fa-solid fa-plus';
        } else {
            detailRow.style.display = 'block';
            icon.className = 'fa-solid fa-minus';
        }
    } else {
        if (detailRow.style.display === 'table-row') {
            detailRow.style.display = 'none';
            icon.className = 'fa-solid fa-plus';
        } else {
            detailRow.style.display = 'table-row';
            icon.className = 'fa-solid fa-minus';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var selectAll = document.getElementById('selectAll');
    var rowChecks = document.querySelectorAll('.row-check');
    var selectedCount = document.getElementById('selectedCount');
    var bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

    function updateCount() {
        var checked = document.querySelectorAll('.row-check:checked').length;
        if (selectedCount) {
            selectedCount.textContent = checked + ' reminder dipilih';
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = checked === 0;
        }
        if (selectAll) {
            selectAll.checked = checked === rowChecks.length && rowChecks.length > 0;
            selectAll.indeterminate = checked > 0 && checked < rowChecks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            rowChecks.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateCount();
        });
    }

    rowChecks.forEach(function(cb) {
        cb.addEventListener('change', updateCount);
    });

    var cards = document.querySelectorAll('.stat-card');
    cards.forEach(function(card) {
        card.addEventListener('mousemove', function(e) {
            var rect = card.getBoundingClientRect();
            var x = ((e.clientX - rect.left) / rect.width) * 100;
            var y = ((e.clientY - rect.top) / rect.height) * 100;
            card.style.setProperty('--mouse-x', x + '%');
            card.style.setProperty('--mouse-y', y + '%');
        });
    });
});
</script>

<?php pageFooter(); ?>
