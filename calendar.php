<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
require __DIR__ . '/includes/layout.php';

requireLogin();

$today = date('Y-m-d');

$monthInput = trim((string) ($_GET['month'] ?? ''));
if ($monthInput === '' || !preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
    $monthInput = date('Y-m');
}

$firstOfMonth = date('Y-m-01', strtotime($monthInput . '-01'));
$daysInMonth = (int) date('t', strtotime($firstOfMonth));
$firstWeekday = (int) date('N', strtotime($firstOfMonth)); // 1 = Senin
$leading = $firstWeekday - 1;

$lastOfMonth = date('Y-m-d', strtotime($firstOfMonth . ' +' . ($daysInMonth - 1) . ' days'));
$prevMonth = date('Y-m', strtotime($firstOfMonth . ' -1 month'));
$nextMonth = date('Y-m', strtotime($firstOfMonth . ' +1 month'));
$indonesianMonths = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
];
$monthNum = (int) date('n', strtotime($firstOfMonth));
$yearNum = (int) date('Y', strtotime($firstOfMonth));
$monthLabel = $indonesianMonths[$monthNum] . ' ' . $yearNum;

$stmt = $pdo->prepare("
    SELECT r.*, u.name AS creator_name
    FROM reminders r
    JOIN users u ON u.id = r.created_by
    WHERE r.start_date <= ? AND r.end_date >= ?
    ORDER BY r.start_date ASC, r.reminder_time ASC, r.id DESC
");
$stmt->execute([$lastOfMonth, $firstOfMonth]);
$all = $stmt->fetchAll();

$grouped = [];
$inRangeDates = [];
foreach ($all as $item) {
    $startDt = new DateTime($item['start_date']);
    $endDt = new DateTime($item['end_date']);
    for ($d = clone $startDt; $d <= $endDt; $d->modify('+1 day')) {
        $dateStr = $d->format('Y-m-d');
        $grouped[$dateStr][] = $item;
        $inRangeDates[$dateStr] = true;
    }
}
$totalThisMonth = count($all);

$cells = [];
for ($i = 0; $i < $leading; $i++) {
    $cells[] = null;
}
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = date('Y-m-d', strtotime($firstOfMonth . ' +' . ($d - 1) . ' days'));
    $cells[] = [
        'day' => $d,
        'date' => $dateStr,
        'items' => $grouped[$dateStr] ?? [],
        'isToday' => $dateStr === $today,
    ];
}
$trailing = (7 - (count($cells) % 7)) % 7;
for ($i = 0; $i < $trailing; $i++) {
    $cells[] = null;
}

$weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

function calStatus(array $item, string $today, string $dateStr): array
{
    if ($item['status'] === 'done' && !empty($item['sent_at'])) {
        $sentDate = substr($item['sent_at'], 0, 10);
        if ($sentDate === $dateStr) {
            return ['Terkirim', 'done'];
        }
    }
    if ($item['status'] === 'failed') {
        return ['Gagal', 'failed'];
    }
    if ($dateStr < $today) {
        return ['Terlambat', 'overdue'];
    }
    if ($dateStr === $today) {
        return ['Hari Ini', 'today'];
    }
    return ['Terjadwal', 'upcoming'];
}

function channelLabel(string $channel): string
{
    if ($channel === 'both') {
        return 'Keduanya';
    }
    if ($channel === 'whatsapp') {
        return 'WhatsApp';
    }
    return 'Email';
}

pageHeader('Kalender Reminder');
?>
<meta http-equiv="refresh" content="60">
<style>
    .cal-container {
        background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
    }

    .cal-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }
    .cal-month {
        font-size: 24px;
        font-weight: 800;
        letter-spacing: -0.4px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .cal-nav {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .cal-nav .btn {
        min-height: 36px;
        padding: 6px 16px;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .cal-nav .btn:hover {
        transform: translateY(-1px);
    }
    .cal-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 20px;
        font-size: 13px;
        color: #64748b;
        padding: 12px 16px;
        background: #f8fafc;
        border-radius: 12px;
    }
    .cal-legend span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .cal-legend i {
        width: 12px;
        height: 12px;
        border-radius: 4px;
        display: inline-block;
        box-shadow: 0 1px 2px rgba(0,0,0,0.15);
    }
    .cal-legend .total {
        margin-left: auto;
        color: #0f172a;
        font-weight: 700;
    }
    .cal-legend .total strong {
        color: #6366f1;
        font-size: 15px;
    }

    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }
    .cal-weekday {
        text-align: center;
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        padding: 10px 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #e2e8f0;
    }
    .cal-weekday span {
        display: inline-block;
        width: 32px;
        height: 32px;
        line-height: 32px;
        border-radius: 8px;
        color: #475569;
    }
    .cal-weekday .week-sen { color: #ef4444; }
    .cal-weekday .week-min { color: #f59e0b; }
    .cal-weekday .week-sel { color: #3b82f6; }
    .cal-weekday .week-rab { color: #10b981; }
    .cal-weekday .week-kam { color: #8b5cf6; }
    .cal-weekday .week-jum { color: #f97316; }
    .cal-weekday .week-sab { color: #06b6d4; }

    .cal-cell {
        min-height: 120px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px;
        background: #fff;
        display: flex;
        flex-direction: column;
        gap: 5px;
        transition: all 0.2s ease;
        position: relative;
        overflow: visible;
    }
    .cal-cell:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-color: #cbd5e1;
    }
    .cal-cell.in-range {
        background: linear-gradient(135deg, #eef2ff 0%, #ffffff 100%);
        border-color: #c7d2fe;
    }
    .cal-cell.muted {
        background: #f8fafc;
        opacity: 0.45;
        border-color: #f1f5f9;
    }
    .cal-cell.muted:hover {
        transform: none;
        box-shadow: none;
    }
    .cal-cell.today {
        border-color: #6366f1;
        box-shadow: 0 0 0 2px rgba(99,102,241,0.15);
    }
    .cal-cell.today.in-range {
        border-color: #6366f1;
    }
    .cal-cell.today .cal-daynum {
        color: #6366f1;
        font-weight: 800;
    }
    .cal-daynum {
        font-size: 13px;
        font-weight: 700;
        color: #64748b;
        align-self: flex-start;
        padding: 2px 4px;
    }

    .cal-events {
        display: flex;
        flex-direction: column;
        gap: 3px;
        width: 100%;
    }
    .cal-event {
        text-align: left;
        border: none;
        border-radius: 8px;
        padding: 5px 8px;
        cursor: pointer;
        font: inherit;
        display: flex;
        gap: 5px;
        align-items: center;
        width: 100%;
        overflow: hidden;
        font-size: 12px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
    }
    .cal-event:hover {
        filter: brightness(0.95);
        transform: translateX(2px);
    }
    .cal-event .cal-time {
        font-size: 11px;
        font-weight: 700;
        flex-shrink: 0;
        color: rgba(0,0,0,0.7);
    }
    .cal-event .cal-subject {
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        flex: 1;
        min-width: 0;
    }
    .cal-event .cal-channel {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 16px;
        height: 16px;
        border-radius: 4px;
        flex-shrink: 0;
    }
    .cal-event.done {
        background: linear-gradient(135deg, #dcfce8 0%, #ffffff 100%);
        color: #15803d;
        border-left: 3px solid #22c55e;
    }
    .cal-event.failed {
        background: linear-gradient(135deg, #fee2e2 0%, #ffffff 100%);
        color: #b91c1c;
        border-left: 3px solid #ef4444;
    }
    .cal-event.overdue {
        background: linear-gradient(135deg, #ffedd5 0%, #ffffff 100%);
        color: #b45309;
        border-left: 3px solid #f59e0b;
    }
    .cal-event.today {
        background: linear-gradient(135deg, #dbeafe 0%, #ffffff 100%);
        color: #1d4ed8;
        border-left: 3px solid #3b82f6;
    }
    .cal-event.upcoming {
        background: linear-gradient(135deg, #ecfeff 0%, #ffffff 100%);
        color: #0e7490;
        border-left: 3px solid #06b6d4;
    }
    .cal-more {
        font-size: 10px;
        color: #94a3b8;
        padding-left: 4px;
        font-weight: 500;
        opacity: 0.7;
    }
    .cal-more:hover {
        opacity: 1;
        color: #64748b;
    }

    .ev-row {
        display: flex;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .ev-row:last-child {
        border-bottom: none;
    }
    .ev-label {
        width: 120px;
        flex-shrink: 0;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        padding-top: 2px;
    }
    .ev-value {
        flex: 1;
        font-size: 13px;
        color: #0f172a;
        word-break: break-word;
    }
    .ev-value ul {
        margin: 0;
        padding-left: 18px;
    }
    .ev-value ul li {
        margin-bottom: 2px;
    }

    .modal-content {
        border: none;
        border-radius: 16px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    }
    .modal-header {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        border-radius: 16px 16px 0 0 !important;
        padding: 16px 24px;
    }
    .modal-header .btn-close {
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        opacity: 0.8;
    }
    .modal-header .btn-close:hover {
        opacity: 1;
    }
    .modal-title {
        font-size: 18px;
        font-weight: 700;
    }
    .modal-body {
        padding: 20px 24px;
    }

    @media (max-width: 640px) {
        .cal-grid {
            grid-template-columns: 1fr;
        }
        .cal-weekday {
            display: none;
        }
        .cal-cell {
            min-height: auto;
            padding: 6px;
        }
        .cal-cell::before {
            content: attr(data-weekday);
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }
        .cal-cell.today::before {
            color: #6366f1;
        }
        .cal-toolbar {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        .cal-month {
            font-size: 22px;
        }
        .ev-row {
            flex-direction: column;
            gap: 4px;
        }
        .ev-label {
            width: 100%;
        }
    }
</style>

<section class="page-title">
    <div>
        <h1><i class="bi bi-calendar3 me-2"></i>Kalender Reminder</h1>
        <p class="muted">Tampilan kalender semua reminder. Klik sebuah reminder untuk melihat detail lengkapnya.</p>
    </div>
    <a class="btn btn-primary" href="create_reminder.php"><i class="fa-solid fa-plus"></i> Buat Reminder</a>
</section>

<section class="cal-container">
    <div class="cal-toolbar">
        <div class="cal-month text-capitalize"><?= e($monthLabel) ?></div>
        <div class="cal-nav">
            <a class="btn btn-secondary btn-small" href="?month=<?= e($prevMonth) ?>"><i class="fa-solid fa-chevron-left"></i> Sebelumnya</a>
            <a class="btn btn-secondary btn-small" href="?month=<?= e(date('Y-m')) ?>">Bulan Ini</a>
            <a class="btn btn-secondary btn-small" href="?month=<?= e($nextMonth) ?>">Berikutnya <i class="fa-solid fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="cal-legend">
        <span><i style="background:#22c55e;"></i> Terkirim</span>
        <span><i style="background:#ef4444;"></i> Gagal</span>
        <span><i style="background:#f59e0b;"></i> Terlambat</span>
        <span><i style="background:#3b82f6;"></i> Hari Ini</span>
        <span><i style="background:#06b6d4;"></i> Terjadwal</span>
        <span class="total">Total bulan ini: <strong><?= $totalThisMonth ?></strong> reminder</span>
    </div>

    <div class="cal-grid">
        <?php foreach ($weekdays as $wd): ?>
            <div class="cal-weekday"><span class="week-<?= strtolower($wd) ?>"><?= e($wd) ?></span></div>
        <?php endforeach; ?>

<?php
$cellIndex = 0;
foreach ($cells as $cell):
    $cellWeekday = $weekdays[$cellIndex % 7];
    $cellIndex++;
?>
    <?php if ($cell === null): ?>
        <div class="cal-cell muted" data-weekday="<?= e($cellWeekday) ?>"></div>
    <?php else: ?>
        <div class="cal-cell<?= $cell['isToday'] ? ' today' : '' ?><?= isset($inRangeDates[$cell['date']]) ? ' in-range' : '' ?>" data-weekday="<?= e($cellWeekday) ?>">
                    <div class="cal-daynum"><?= (int) $cell['day'] ?></div>
                    <div class="cal-events">
                        <?php foreach ($cell['items'] as $item): ?>
                            <?php [$label, $cls] = calStatus($item, $today, $cell['date']); ?>
                            <button type="button" class="cal-event <?= e($cls) ?>" data-bs-toggle="modal" data-bs-target="#eventModal"
                                 data-id="<?= (int) $item['id'] ?>"
                                 data-subject="<?= e($item['subject']) ?>"
                                 data-department="<?= e($item['department']) ?>"
                                 data-subdept="<?= e($item['sub_department'] ?: '-') ?>"
                                 data-category="<?= e($item['category'] ?: '-') ?>"
                                 data-priority="<?= e($item['priority'] ?? 'medium') ?>"
                                 data-startdate="<?= e(date('d M Y', strtotime($item['start_date']))) ?>"
                                 data-enddate="<?= e(date('d M Y', strtotime($item['end_date']))) ?>"
                                 data-time="<?= e(substr($item['reminder_time'], 0, 5)) ?>"
                                 data-sender="<?= e($item['sender_email'] ?: '-') ?>"
                                 data-to="<?= e($item['to_emails']) ?>"
                                 data-cc="<?= e($item['cc_emails'] ?: '') ?>"
                                 data-channel="<?= e(channelLabel($item['send_channel'] ?? 'email')) ?>"
                                 data-channel-type="<?= e($item['send_channel'] ?? 'email') ?>"
                                 data-status="<?= e($label) ?>"
                                 data-statusclass="<?= e($cls) ?>"
                                 data-creator="<?= e($item['creator_name']) ?>"
                                 data-sent="<?= e(!empty($item['sent_at']) ? date('d M Y H:i', strtotime($item['sent_at'])) : '') ?>"
                                 data-attachment="<?= $item['attachment_path'] ? 'attachment.php?id=' . (int) $item['id'] : '' ?>"
                                 data-error="<?= e($item['last_error'] ?? '') ?>">
                                <span class="cal-time"><?= e(substr($item['reminder_time'], 0, 5)) ?></span>
                                <span class="cal-subject" title="<?= e($item['subject']) ?>"><?= e($item['subject']) ?></span>
                                <span class="cal-channel" title="<?= e(channelLabel($item['send_channel'] ?? 'email')) ?>"><i class="fa-solid <?= ($item['send_channel'] ?? 'email') === 'whatsapp' ? 'fa-brands fa-whatsapp' : (($item['send_channel'] ?? '') === 'both' ? 'fa-solid fa-paper-plane' : 'fa-solid fa-envelope') ?>"></i></span>
                            </button>
                        <?php endforeach; ?>
                        <?php if (count($cell['items']) > 4): ?>
                            <span class="cal-more">+<?= count($cell['items']) - 4 ?> lainnya</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="evSubject">Detail Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="ev-row">
                    <div class="ev-label">Status</div>
                    <div class="ev-value"><span class="badge" id="evStatus"></span></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Department</div>
                    <div class="ev-value" id="evDepartment">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Sub Dept</div>
                    <div class="ev-value" id="evSubDept">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Kategori</div>
                    <div class="ev-value" id="evCategory">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Prioritas</div>
                    <div class="ev-value" id="evPriority">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Tanggal Mulai</div>
                    <div class="ev-value"><span id="evStartDate">-</span></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Tanggal Akhir</div>
                    <div class="ev-value"><span id="evEndDate">-</span></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Jam</div>
                    <div class="ev-value"><span id="evTime">-</span></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Pengirim</div>
                    <div class="ev-value" id="evSender">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">To</div>
                    <div class="ev-value"><ul id="evTo"></ul></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Cc</div>
                    <div class="ev-value"><ul id="evCc"></ul></div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Channel</div>
                    <div class="ev-value" id="evChannel">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Dibuat Oleh</div>
                    <div class="ev-value" id="evCreator">-</div>
                </div>
                <div class="ev-row">
                    <div class="ev-label">Dikirim</div>
                    <div class="ev-value" id="evSent">-</div>
                </div>
                <div class="ev-row" id="evAttachmentRow" style="display:none;">
                    <div class="ev-label">Lampiran</div>
                    <div class="ev-value"><a id="evAttachment" href="#" target="_blank">Buka lampiran</a></div>
                </div>
                <div class="ev-row" id="evErrorRow" style="display:none;">
                    <div class="ev-label">Error</div>
                    <div class="ev-value"><span class="error-text" id="evError"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <a class="btn btn-primary btn-small" id="evEdit" href="#">Edit</a>
                <button type="button" class="btn btn-secondary btn-small" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var eventModal = document.getElementById('eventModal');
    if (!eventModal) return;

    var statusColors = {
        done: 'status-done',
        failed: 'status-overdue',
        overdue: 'status-overdue',
        today: 'status-today',
        upcoming: 'status-upcoming'
    };

    function fillList(el, value) {
        el.innerHTML = '';
        var parts = (value || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
        if (!parts.length) {
            el.innerHTML = '-';
            return;
        }
        parts.forEach(function (p) {
            var li = document.createElement('li');
            li.textContent = p;
            el.appendChild(li);
        });
    }

    eventModal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (!btn) return;
        var d = btn.dataset;

        document.getElementById('evSubject').textContent = d.subject || '-';
        var statusEl = document.getElementById('evStatus');
        statusEl.textContent = d.status || '-';
        statusEl.className = 'badge ' + (statusColors[d.statusclass] || 'status-upcoming');
        document.getElementById('evDepartment').textContent = d.department || '-';
        document.getElementById('evSubDept').textContent = d.subdept || '-';
        document.getElementById('evCategory').textContent = d.category || '-';
        document.getElementById('evPriority').textContent = d.priority || '-';
        document.getElementById('evStartDate').textContent = d.startdate || '-';
        document.getElementById('evEndDate').textContent = d.enddate || '-';
        document.getElementById('evTime').textContent = d.time || '-';
        document.getElementById('evSender').textContent = d.sender || '-';

        var channelEl = document.getElementById('evChannel');
        var channelType = d.channelType || 'email';
        var channelHtml = '';
        if (channelType === 'both') {
            channelHtml = '<i class="fa-solid fa-envelope" style="color:#6366f1;margin-right:6px;" title="Email"></i>' +
                          '<i class="fa-brands fa-whatsapp" style="color:#25D366;" title="WhatsApp"></i>';
        } else if (channelType === 'whatsapp') {
            channelHtml = '<i class="fa-brands fa-whatsapp" style="color:#25D366;" title="WhatsApp"></i>';
        } else {
            channelHtml = '<i class="fa-solid fa-envelope" style="color:#6366f1;" title="Email"></i>';
        }
        channelEl.innerHTML = channelHtml;

        document.getElementById('evCreator').textContent = d.creator || '-';
        document.getElementById('evSent').textContent = d.sent || '-';
        fillList(document.getElementById('evTo'), d.to);
        fillList(document.getElementById('evCc'), d.cc);

        var attRow = document.getElementById('evAttachmentRow');
        var attLink = document.getElementById('evAttachment');
        if (d.attachment) {
            attRow.style.display = '';
            attLink.href = d.attachment;
        } else {
            attRow.style.display = 'none';
        }

        var errRow = document.getElementById('evErrorRow');
        if (d.error) {
            errRow.style.display = '';
            document.getElementById('evError').textContent = d.error;
        } else {
            errRow.style.display = 'none';
        }

        document.getElementById('evEdit').href = 'edit_reminder.php?id=' + (d.id || '');
    });
});
</script>

<?php pageFooter(); ?>
