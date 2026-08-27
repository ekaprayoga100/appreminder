<?php
declare(strict_types=1);

function pageHeader(string $title): void
{
    $user = currentUser();
    $isAuth = !$user;
    $page = basename($_SERVER['PHP_SELF']);
    $authPages = ['login.php', 'register.php'];
    ?>
    <!doctype html>
    <html lang="id" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> - PT Mayora Indah Tbk - Jayanti 1</title>
        <link rel="icon" type="image/png" href="assets/favcon_reminder.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            :root { --sidebar-width: 260px; --primary: #6366f1; --primary-hover: #4f46e5; --danger: #ef4444; --warning: #f59e0b; --success: #10b981; --info: #06b6d4; --bg: #f1f5f9; --surface: #ffffff; --ink: #0f172a; --ink-secondary: #334155; --muted: #64748b; --line: #e2e8f0; }
            body { min-height: 100vh; }
            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0; width: var(--sidebar-width);
                background: #0f172a; color: #94a3b8; z-index: 1040;
                display: flex; flex-direction: column; border-right: 1px solid rgba(255,255,255,0.06);
                box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            }
            .sidebar-brand {
                padding: 18px 20px; border-bottom: 1px solid rgba(255,255,255,0.06);
                display: flex; align-items: center; gap: 10px; min-height: 64px;
            }
            .sidebar-brand .brand-icon {
                width: 48px; height: 48px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            }
            .sidebar-title { font-size: 13px; font-weight: 700; color: #fff; letter-spacing: -0.2px; line-height: 1.3; }
            .sidebar-nav { flex: 1; display: flex; flex-direction: column; padding: 16px 12px; gap: 2px; overflow-y: auto; }
            .sidebar-link {
                display: flex; align-items: center; gap: 12px; padding: 10px 14px; border-radius: 10px;
                color: #94a3b8; font-weight: 500; font-size: 14px; transition: all 0.2s ease;
                text-decoration: none; white-space: nowrap;
            }
            .sidebar-link:hover { background: #1e293b; color: #e2e8f0; text-decoration: none; }
            .sidebar-link.active { background: #6366f1; color: #fff; box-shadow: 0 4px 12px rgba(99,102,241,0.35); }
            .sidebar-link i { width: 18px; text-align: center; font-size: 14px; opacity: 0.8; flex-shrink: 0; }
            .sidebar-link.active i { opacity: 1; }
            .sidebar-spacer { flex: 1; min-height: 12px; }
            .sidebar-link.logout { color: #f87171; }
            .sidebar-link.logout:hover { background: rgba(248,113,113,0.1); color: #fca5a5; }
            .sidebar-link.logout i { opacity: 1; }
            .app-main { margin-left: 0; min-height: 100vh; transition: margin-left 0.3s ease; position: relative; }
            @media (min-width: 1024px) {
                .sidebar { transform: translateX(0); }
                .app-main { margin-left: var(--sidebar-width); }
            }
            @media (max-width: 1023px) {
                .sidebar { position: fixed; top: 0; left: 0; bottom: 0; z-index: 1040; transform: translateX(-100%); transition: transform 0.3s ease; }
                .sidebar.open { transform: translateX(0); }
                .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1039; backdrop-filter: blur(2px); }
                .sidebar-overlay.open { display: block; }
            }
            .topbar {
                display: flex; align-items: center; gap: 16px; min-height: 64px; padding: 0 24px;
                background: #fff; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0;
                z-index: 1020; box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }
            .menu-toggle {
                display: none; align-items: center; justify-content: center; width: 38px; height: 38px;
                border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; color: #0f172a;
                cursor: pointer; font-size: 16px; transition: all 0.2s ease;
            }
            .menu-toggle:hover { background: #f1f5f9; border-color: #cbd5e1; }
            @media (max-width: 1023px) { .menu-toggle { display: flex; } }
            .topbar-spacer { flex: 1; }
            .topbar-user { display: flex; align-items: center; gap: 12px; }
            .avatar {
                width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg,#6366f1,#8b5cf6);
                color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px;
                box-shadow: 0 2px 8px rgba(99,102,241,0.3);
            }
            .user-meta { display: flex; flex-direction: column; line-height: 1.3; }
            .user-meta strong { font-size: 13px; font-weight: 600; color: #0f172a; }
            .user-meta .role-tag { font-size: 11px; color: #64748b; text-transform: capitalize; letter-spacing: 0.3px; font-weight: 500; }
            .clock {
                color: #64748b; font-size: 12px; white-space: nowrap; font-variant-numeric: tabular-nums;
                background: #f8fafc; padding: 4px 10px; border-radius: 20px; border: 1px solid #e2e8f0;
            }
            @media (max-width: 640px) {
                .clock { display: none; }
                .user-meta { display: none; }
                .topbar-user { gap: 8px; }
            }
            .auth-wrapper {
                min-height: 100vh; display: flex; align-items: center; justify-content: center;
                background: #f8fafc; padding: 20px;
            }
            .auth-card {
                background: #fff; border-radius: 14px; box-shadow: 0 4px 16px rgba(0,0,0,0.06);
                width: min(400px, 100%); padding: 32px; border: 1px solid #e2e8f0;
                animation: fadeInUp 0.5s ease 0.1s both;
            }
            @keyframes fadeInUp {
                from { opacity: 0; transform: translateY(16px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes slideIn {
                from { opacity: 0; transform: translateY(-4px); }
                to { opacity: 1; transform: translateY(0); }
            }
            @keyframes subtlePulse {
                0%,100% { transform: scale(1); }
                50% { transform: scale(1.03); }
            }
            .auth-logo {
                width:90px; height:90px; border:3px solid #fff; border-radius:12px;
                box-shadow: 0 4px 12px rgba(0,0,0,.08);
                animation: subtlePulse 4s ease-in-out infinite;
            }
            .auth-logo:hover {
                animation: none;
                transform: scale(1.05) rotate(2deg) !important;
            }
            .auth-brand { margin-bottom: 24px; }
            .auth-title { font-size: 22px; font-weight: 700; color: #0f172a; }
            .auth-card h1 { margin: 0 0 6px; font-size: 24px; line-height: 1.3; font-weight: 700; letter-spacing: -0.3px; }
            .auth-card .muted { margin: 0 0 28px; color: #64748b; font-size: 14px; line-height: 1.5; }
            .auth-card .btn { width: 100%; margin-top: 14px; min-height: 44px; transition: all 0.2s ease; }
            .auth-card .btn-danger { background: linear-gradient(135deg,#dc2626,#b91c1c); border: none; }
            .auth-card .btn-danger:hover {
                transform: translateY(-1px);
                box-shadow: 0 6px 20px rgba(220,20,60,.35);
            }
            .auth-card .btn-secondary { margin-top: 10px; background: #f8fafc; border-color: #e2e8f0; }
            .auth-card .hint { text-align: center; margin-top: 24px; color: #64748b; font-size: 12px; }
            .auth-form .form-control:focus {
                box-shadow: 0 0 0 3px rgba(220,20,60,.15);
                border-color: #dc2626;
            }
            .auth-form .alert {
                animation: slideIn 0.3s ease;
            }
            .stat {
                background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 16px rgba(0,0,0,0.04); transition: all 0.2s ease; position: relative; overflow: hidden;
            }
            .stat::before {
                content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
                background: var(--stat-accent, #6366f1); opacity: 0; transition: opacity 0.2s ease;
            }
            .stat:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04); border-color: #cbd5e1; }
            .stat:hover::before { opacity: 1; }
            .stat span { color: #64748b; display: block; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
            .stat strong { display: block; font-size: 28px; font-weight: 700; margin-top: 4px; color: #0f172a; letter-spacing: -0.5px; }
            .stat-icon {
                position: absolute; top: 16px; right: 16px; width: 36px; height: 36px; border-radius: 10px;
                background: #eef2ff; color: #6366f1; display: flex; align-items: center; justify-content: center; font-size: 14px;
            }
            .panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 16px rgba(0,0,0,0.04); overflow: hidden; }
            .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .table-wrap::-webkit-scrollbar { height: 6px; }
            .table-wrap::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
            table { border-collapse: separate; border-spacing: 0; min-width: 1320px; width: 100%; }
            th, td { border-bottom: 1px solid #e2e8f0; padding: 14px 16px; text-align: left; vertical-align: middle; }
            th { background: #f8fafc; color: #334155; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; white-space: nowrap; position: sticky; top: 0; }
            tbody tr { transition: background 0.2s ease; }
            tbody tr:hover td { background: #f8fafc; }
            tr:last-child td { border-bottom: 0; }
            .empty { color: #64748b; padding: 48px; text-align: center; font-size: 14px; background: #f8fafc; }
            .badge { border-radius: 999px; display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 600; padding: 4px 10px; white-space: nowrap; letter-spacing: 0.1px; }
            .status-done { background: #f0fdf4; color: #10b981; }
            .status-overdue { background: #fef2f2; color: #ef4444; }
            .status-today { background: #fffbeb; color: #f59e0b; }
            .status-upcoming { background: #ecfeff; color: #06b6d4; }
            .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px; padding: 28px; }
            .form-grid .full { grid-column: 1 / -1; }
            .form-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 16px rgba(0,0,0,0.04); padding: 28px; }
            .form-card .form-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 20px; padding-bottom: 14px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
            .form-card .form-title i { color: #6366f1; font-size: 16px; }
            label { display: grid; gap: 6px; color: #334155; font-weight: 600; font-size: 13px; letter-spacing: 0.1px; }
            label i { color: #64748b; font-size: 12px; margin-right: 4px; }
            input, select, textarea {
                width: 100%; border: 1px solid #e2e8f0; border-radius: 10px; color: #0f172a; font: inherit;
                font-size: 14px; padding: 10px 12px; background: #fff; transition: all 0.2s ease;
            }
            input:hover, select:hover, textarea:hover { border-color: #cbd5e1; }
            input:focus, select:focus, textarea:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); background: #fff; }
            textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
            select {
                appearance: none; -webkit-appearance: none;
                background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23334155' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
                background-repeat: no-repeat; background-position: right 10px center; background-size: 16px; padding-right: 32px; cursor: pointer;
            }
            input[type="time"] { cursor: pointer; letter-spacing: 0.5px; }
            input[type="time"]::-webkit-calendar-picker-indicator { opacity: 0.6; cursor: pointer; transition: opacity 0.2s ease; }
            input[type="time"]::-webkit-calendar-picker-indicator:hover { opacity: 1; }
            .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; border: 1px solid transparent; border-radius: 10px; cursor: pointer; font-weight: 600; padding: 0 18px; font-size: 14px; transition: all 0.2s ease; text-decoration: none; font-family: inherit; }
            .btn:hover { text-decoration: none; transform: translateY(-1px); }
            .btn:active { transform: translateY(0); }
            .btn-primary { background: #6366f1; color: #fff; box-shadow: 0 1px 2px rgba(99,102,241,0.3), 0 1px 3px rgba(0,0,0,0.08); }
            .btn-primary:hover { background: #4f46e5; box-shadow: 0 4px 12px rgba(99,102,241,0.35), 0 1px 3px rgba(0,0,0,0.08); }
            .btn-secondary { background: #fff; border-color: #e2e8f0; color: #0f172a; }
            .btn-secondary:hover { background: #f1f5f9; border-color: #cbd5e1; }
            .btn-danger { background: #fef2f2; border-color: #fecaca; color: #ef4444; }
            .btn-danger:hover { background: #fee2e2; border-color: #fca5a5; }
            .btn-warning { background: #fffbeb; border-color: #fde68a; color: #f59e0b; }
            .btn-warning:hover { background: #fef3c7; border-color: #fcd34d; }
            .btn-small { min-height: 32px; padding: 0 12px; font-size: 13px; font-weight: 600; }
            .actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 10px; margin-top: 8px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
            .inline-form { margin: 0; display: inline-flex; }
            .action-stack { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
            .field-note { color: #64748b; font-size: 12px; font-weight: 400; margin-top: 4px; line-height: 1.4; }
            .error-text { color: #ef4444; display: inline-block; max-width: 300px; font-size: 12px; font-weight: 500; word-break: break-word; background: #fef2f2; padding: 2px 8px; border-radius: 4px; }
            .attachment-box { display: grid; gap: 10px; padding: 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; }
            .attachment-box strong { font-size: 13px; color: #334155; font-weight: 600; }
            .attachment-box a { font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
            .check-row { align-items: center; display: flex; font-weight: 500; gap: 8px; font-size: 14px; color: #334155; }
            .check-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #6366f1; cursor: pointer; }
            .section-title { margin: 32px 0 16px; }
            .section-title h2 { font-size: 20px; margin: 0 0 4px; font-weight: 700; letter-spacing: -0.2px; }
            .section-title .muted { font-size: 14px; }
            .auth-form { display: grid; gap: 18px; }
            .auth-form label { margin-top: 0; }
            .auth-form .alert { margin-bottom: 4px; }
            .auth-form .btn { margin-top: 6px; }
            .pulse-glow { animation: pulse 2s ease-in-out infinite; display: inline-block; }
            .pulse-glow img { transition: all .3s ease; }
            .pulse-glow:hover img { transform: scale(1.05); box-shadow: 0 0 0 4px rgba(220,20,60,.15); }
            .float-bounce { animation: bounce 2s ease-in-out infinite; }
            @keyframes bounce { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
            @keyframes pulse { 0%,100%{box-shadow:0 8px 24px rgba(220,20,60,.2)} 50%{box-shadow:0 8px 24px rgba(220,20,60,.4)} }
            @keyframes slideUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
            @keyframes fadeInDown { from{opacity:0;transform:translateY(-20px)} to{opacity:1;transform:translateY(0)} }
            @keyframes fadeInUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
            @keyframes fadeInLeft { from{opacity:0;transform:translateX(-20px)} to{opacity:1;transform:translateX(0)} }
            @keyframes shake { 0%,100%{transform:translateX(0)} 20%,60%{transform:translateX(-2px)} 40%,80%{transform:translateX(2px)} }
            .toggle-password { display: flex !important; }
            :focus-visible { outline: 2px solid #6366f1; outline-offset: 2px; }
            ::-webkit-scrollbar { width: 8px; height: 8px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
            ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
            .divider { height: 1px; background: #e2e8f0; margin: 24px 0; border: none; }
            .text-muted { color: #64748b; }
            .font-medium { font-weight: 500; }
            .selected-email-tag {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 10px;
                border-radius: 999px;
                background: #eef2ff;
                color: #6366f1;
                font-size: 13px;
                font-weight: 500;
                border: 1px solid #e0e7ff;
                margin: 2px 4px 2px 0;
            }
            .selected-email-tag .tag-remove {
                background: none;
                border: none;
                color: #6366f1;
                cursor: pointer;
                font-size: 16px;
                line-height: 1;
                padding: 0;
                margin-left: 2px;
            }
            .selected-email-tag .tag-remove:hover {
                color: #ef4444;
            }
            .selected-emails { display: flex; flex-wrap: wrap; gap: 0; }
            @media (max-width: 1023px) {
                .sidebar { position: fixed; top: 0; left: 0; bottom: 0; z-index: 1040; }
            }
            @media (max-width: 760px) {
                .auth-card { padding: 24px; max-height: 80vh; overflow-y: auto; }
            }
            @media (max-width: 640px) {
                .topbar { padding: 0 16px; }
            }
            @media (max-width: 480px) {
                .auth-card { padding: 20px; border-radius: 12px; }
                .btn { width: 100%; }
                .action-stack { flex-direction: column; align-items: stretch; }
                .action-stack .btn { width: 100%; }
            }
        </style>
    </head>
    <body>
    <?php if (!$isAuth): ?>
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon"><img src="assets/logo_mayora.png" alt="Logo" style="width:48px;height:48px;border-radius:10px;"></div>
                <span class="sidebar-title">PT Mayora Indah Tbk - IRGA Jayanti 1</span>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="sidebar-link<?= $page === 'dashboard.php' ? ' active' : '' ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="calendar.php" class="sidebar-link<?= $page === 'calendar.php' ? ' active' : '' ?>">
                    <i class="bi bi-calendar3"></i> Kalender
                </a>
                <a href="create_reminder.php" class="sidebar-link<?= $page === 'create_reminder.php' ? ' active' : '' ?>">
                    <i class="bi bi-send"></i> Buat Reminder
                </a>
                <a href="data_email.php" class="sidebar-link<?= $page === 'data_email.php' ? ' active' : '' ?>">
                    <i class="bi bi-people"></i> Kelola Kontak
                </a>
                <a href="info.php" class="sidebar-link<?= $page === 'info.php' ? ' active' : '' ?>">
                    <i class="bi bi-info-circle"></i> Informasi
                </a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="scheduler_monitor.php" class="sidebar-link<?= $page === 'scheduler_monitor.php' ? ' active' : '' ?>">
                    <i class="fa-solid fa-github"></i> Monitor Scheduler
                </a>
                <?php endif; ?>
                <a href="password_change.php" class="sidebar-link<?= $page === 'password_change.php' ? ' active' : '' ?>">
                    <i class="bi bi-key"></i> Ganti Password
                </a>
                 <?php if (($user['role'] ?? '') === 'admin'): ?>
                 <a href="settings.php" class="sidebar-link<?= $page === 'settings.php' ? ' active' : '' ?>">
                     <i class="bi bi-gear"></i> Pengaturan
                 </a>
                 <?php endif; ?>
                 <div class="sidebar-spacer"></div>
                <a href="logout.php" class="sidebar-link logout">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </nav>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <div class="app-main">
            <header class="topbar">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
                    <i class="bi bi-list"></i>
                </button>
                <div class="topbar-spacer"></div>
                <div class="topbar-user">
                    <div class="avatar">
                        <i class="bi bi-person"></i>
                    </div>
                    <div class="user-meta">
                        <strong><?= e($user['name']) ?></strong>
                        <span class="role-tag"><?= e($user['role']) ?></span>
                    </div>
                    <span class="clock" id="realtime-clock">Memuat waktu...</span>
                </div>
            </header>
            <main class="container py-4">
    <?php else: ?>
        <?php $page = basename($_SERVER['PHP_SELF']); ?>
        <?php if (!in_array($page, ['login.php', 'register.php'], true)): ?>
        <div class="auth-wrapper">
            <div class="auth-card">
                <div class="auth-brand">
                    <span class="auth-title"><?= e($title) ?></span>
                </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

function pageFooter(): void
{
    if (currentUser()): ?>
            </main>
        </div>
    <?php else: ?>
        <?php $page = basename($_SERVER['PHP_SELF']); ?>
        <?php if (!in_array($page, ['login.php', 'register.php'], true)): ?>
        </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js">
    </script>
    <?php if (currentUser()): ?>
    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('open');
        }

        if (menuToggle && sidebar) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                if (overlay) overlay.classList.toggle('open');
            });
            if (overlay) overlay.addEventListener('click', closeSidebar);
        }

        const clock = document.getElementById('realtime-clock');

        function updateClock() {
            if (!clock) return;
            const now = new Date();
            clock.textContent = new Intl.DateTimeFormat('id-ID', {
                weekday: 'long', day: '2-digit', month: 'long', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
                timeZone: 'Asia/Jakarta'
            }).format(now) + ' WIB';
        }

        updateClock();
setInterval(updateClock, 1000);
    </script>
    <?php endif; ?>
    <?php if (currentUser()): ?>
    <footer class="text-center py-4 text-muted small border-top mt-auto">
        &copy; <?= date('Y') ?> PT Mayora Indah Tbk &ndash; IRGA Jayanti 1
    </footer>
    <?php endif; ?>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>
    <script>
        function showToast(title, message, type) {
            var container = document.getElementById('toastContainer');
            if (!container) return;
            var bgClass = type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info';
            var toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center text-bg-' + bgClass;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.innerHTML =
                '<div class="d-flex">' +
                '<div class="toast-body">' +
                '<strong>' + title + '</strong><br>' + message +
                '</div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
                '</div>';
            container.appendChild(toastEl);
            var toast = new bootstrap.Toast(toastEl, { delay: 5000 });
            toast.show();
            toastEl.addEventListener('hidden.bs.toast', function() {
                toastEl.remove();
            });
        }
        <?php if (hasFlash('send_result')): ?>
            <?php
                $send = popFlash('send_result');
                $emailOk = ($send['email_status'] ?? '') === 'success';
                $waOk = ($send['wa_status'] ?? '') === 'success';
                $subject = $send['subject'] ?? '';
                $emailErr = $send['email_error'] ?? '';
                $waErr = $send['wa_error'] ?? '';
            ?>
            (function() {
                var emailOk = <?= json_encode($emailOk) ?>;
                var waOk = <?= json_encode($waOk) ?>;
                var subject = <?= json_encode($subject, JSON_UNESCAPED_UNICODE) ?>;
                var emailErr = <?= json_encode($emailErr, JSON_UNESCAPED_UNICODE) ?>;
                var waErr = <?= json_encode($waErr, JSON_UNESCAPED_UNICODE) ?>;
                if (emailOk && waOk) {
                    showToast('Berhasil', 'Reminder' + (subject ? ' "' + subject + '"' : '') + ' terkirim', 'success');
                } else {
                    var msg = '';
                    if (!emailOk) msg += 'Email: ' + emailErr;
                    if (!waOk) { if (msg) msg += '\n'; msg += 'WA: ' + waErr; }
                    showToast('Gagal', msg, 'error');
                }
            })();
        <?php endif; ?>
        <?php if (($_GET['created'] ?? '') === '1'): ?>
            showToast('Berhasil', 'Reminder berhasil disimpan.', 'success');
        <?php elseif (($_GET['updated'] ?? '') === '1'): ?>
            showToast('Berhasil', 'Reminder berhasil diupdate.', 'success');
        <?php elseif (($_GET['deleted'] ?? '') === '1'): ?>
            showToast('Berhasil', 'Reminder berhasil dihapus.', 'success');
        <?php elseif (($_GET['missing'] ?? '') === '1'): ?>
            showToast('Gagal', 'Reminder tidak ditemukan.', 'error');
        <?php elseif (($_GET['registered'] ?? '') === '1'): ?>
            showToast('Berhasil', 'Akun berhasil dibuat. Silakan login.', 'success');
        <?php endif; ?>
    </script>
    </body>
</html>
    <?php
}
