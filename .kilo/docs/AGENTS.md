# App Reminder - Agents

## Project Overview
Email & WhatsApp reminder scheduling application built with PHP and MySQL (XAMPP).

## Database
- File: `database.sql` — create DB, tables, and seed data
- Local MySQL: `localhost`, user `root`, no password, DB `appreminder`

## Key Files
- `config/database.php` — PDO connection (uses env/config, not hardcoded root)
- `config/mail.php` — SMTP settings (placeholder credentials)
- `config/app.php` — app config (cron secret)
- `includes/auth.php` — session & auth helpers
- `includes/mailer.php` — SMTP email sending (raw socket)
- `includes/whatsapp_sender.php` — WhatsApp API sending (cURL)
- `includes/reminder_helpers.php` — shared helpers (formatBytes, upload, sendDueReminders)
- `includes/layout.php` — page header/footer with Bootstrap sidebar
- `includes/mail_config.php` — merge config/mail.php + DB smtp_settings
- `.htaccess` — HTTPS redirect, security headers, directory blocking

## Running
1. Start XAMPP Apache & MySQL
2. Import `database.sql` via phpMyAdmin
3. Access via `http://localhost/appreminder/`
4. Login with seeded accounts (check `database.sql` for credentials)

## Dashboard
- Stat cards with hover effects and mouse-tracking radial gradient
- Responsive grid: 4 columns (1024px), 2 columns (768px), 1 column (360px)
- Reminder table with expandable detail rows
- Bulk delete with checkbox selection
- Search and channel filter (Email/WhatsApp/Keduanya)

## Recent Changes
- Fixed missing `</a>` tag in sidebar (`includes/layout.php`)
- Fixed undefined `$errors` variable in `data_email.php`
- Fixed double-counting of overdue reminders in dashboard stats
- Fixed SQL string interpolation in dashboard stats query (now uses prepared statements)
- Fixed variable name conflict `$today` (string vs DateTime) in dashboard loop
- Removed status filter dropdown from dashboard
- Removed "Buat Reminder Cepat" modal from dashboard
- Removed dashboard auto-send trigger — reminders now send automatically via Task Scheduler / external cron even without user login
- Added admin restriction on changing other admin passwords
- Fixed timezone mismatch between PHP and MySQL (added `SET time_zone = '+07:00'`)
- Fixed duplicate sending bug in cron (now updates status to `done` after sending)
- Removed hardcoded `root` credentials from `backup.php` (now uses PDO from config)
- Replaced `EHLO localhost` with dynamic hostname in mailer.php
- Added `.htaccess` for production deployment (HTTPS redirect except localhost, security headers, directory blocking)
- Added `session_set_cookie_params` for subfolder compatibility
- Fixed `info.php` to use dynamic domain and cron secret instead of hardcoded values
- Changed contact import form from XLS to CSV (uses existing `import_contacts.php`)
- Added `app_url` config in `config/app.php` (default: `https://appreminder.zya.me`)
- Unified `config/database.php` — local credentials (localhost, root, no pass) with `DB_HOST/NAME/USER/PASS` env override
- Fixed `.htaccess` HTTPS redirect to skip localhost/127.0.0.1/.local
- Fixed `readLogFile()` to normalize `\r\n` → `\n` and trim trailing whitespace
- Added "URL Cron Job Eksternal" section with "Buka URL Cron" button in Settings → Cron Log tab
- Added "Reminder Terjadwal Hari Ini" table di Settings → Cron Log tab
- Fixed `cron_trigger.php` retry logic — recreate PDO connection on retry instead of reusing stale one
- Added `sendOutputLog()` to `cron_trigger.php` — writes to `cron_output.log` for external trigger visibility
- Added attachment download link in email body using `appreminder.zya.me`
- Simplified `run_cron.bat` — removed redundant headers and `echo.` blank lines
- Fixed CSS log viewer: `word-break:break-all` → `overflow-wrap:anywhere`

## Cron
- `cron_send_reminder.php` — CLI cron for sending reminders (runs every 5 min via Task Scheduler or GitHub Actions)
- `cron_trigger.php` — web-based cron trigger for external services (also writes to `cron_output.log`)
- Task Scheduler (local): runs via `run_cron.bat` every 5 minutes
- External cron (production): GitHub Actions Scheduler memanggil `https://appreminder.zya.me/cron_trigger.php?key=<SECRET>` setiap 5 menit
- Secret key stored in `storage/.cron_secret` (auto-generated, per-environment)
- App URL in `config/app.php` (`app_url` key, default: `https://appreminder.zya.me`)
- Logs: `logs/cron.log`, `logs/cron_trigger_access.log`, `logs/cron_output.log`
- **Reminders are sent automatically without requiring any user to be logged in or browsing the app** — external cron service is the primary mechanism.
- Monitoring: open **Settings → tab Cron Log** untuk melihat ketiga log, URL cron, dan reminder terjadwal hari ini.
- See `.kilo/docs/cron.md` for full setup details.

## Deployment

### Pre-deployment Checklist
1. Set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` environment variables for production MySQL (or edit `config/database.php` defaults)
2. Verify `config/app.php` — `app_url` should be `https://appreminder.zya.me` (production domain)
3. Create directories via File Manager: `storage/`, `logs/`, `uploads/` (chmod 755 or 777)
4. Re-generate cron secret: delete `storage/.cron_secret` (auto-generates on next load)
5. Update SMTP settings via Settings UI (Gmail App Password recommended)
6. Update WhatsApp API settings via Settings UI

### Important Notes
- Do NOT deploy `logs/*`, `storage/.cron_secret`, `backup/`, `run_cron.bat`
- `.gitignore` already configured for sensitive files
- `config/database.php` uses local defaults (localhost/root) — update via `DB_HOST/NAME/USER/PASS` env vars for production (no hardcoded credentials)
- `config/app.php` has `app_url` (default: `https://appreminder.zya.me`) — used for cron URL, attachment links, and EHLO hostname in emails
- Scheduler: `cron_trigger.php?key=<CRON_SECRET>` is called by **GitHub Actions** every 5 minutes (see `.github/workflows/reminder.yml`)
- GitHub Secrets: set `CRON_TRIGGER_URL` = `https://appreminder.zya.me/cron_trigger.php?key=<CRON_SECRET>`
- `.htaccess` forces HTTPS except for localhost/127.0.0.1/.local
- **Anti-bot JS challenge**: Free hosting (zya.me/aeonfree) intercepts at proxy level. If `cron_trigger.php` returns HTML instead of JSON, disable anti-bot in hosting panel or use local Task Scheduler
- `cron_trigger.php` sends JSON headers early to prevent anti-bot interception
- `cron_send_reminder.php` requires CLI execution (returns 403 via web)

### Post-deployment
1. Verify HTTPS is working (forced by `.htaccess`)
2. Test login and create a reminder
3. Verify cron is running via `cron.log`
4. Set GitHub Secret `CRON_TRIGGER_URL` in repository Settings
5. Check email/WhatsApp delivery
6. Buka `scheduler_monitor.php` untuk melihat status GitHub Actions workflow
