# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Migrated scheduler from cron-job.org to **GitHub Actions Scheduler** (.github/workflows/reminder.yml)
- GitHub Actions workflow runs every 5 minutes, calling `cron_trigger.php` via `CRON_TRIGGER_URL` secret
- Added `cron_output.log` write to `cron_trigger.php` for external trigger visibility

### Changed
- Schedule interval: 1 minute → **5 minutes**
- Secret management: moved from cron-job.org dashboard to **GitHub Repository Secrets**

## [1.2.1] - 2026-08-25

### Summary
Fix automated cron sending reminders by diagnosing why external cron-job.org is not triggering `cron_trigger.php` every 1 minute.

### Fixed
- **dashboard.php:712** — Bound `:channel` parameter via `$stmt->bindValue(':channel', $channelFilter)` (was unbound)
- **send_reminder.php** — Fixed double semicolon (`?;?`) syntax error on `prepare()` call
- **edit_reminder.php** — Status reset fix: preserves `'done'` status + `sent_at`/`last_error` if already sent (was forcing `status='pending'`)
- **cron_trigger.php** — Added `header('Content-Type: application/json')`, `X-Content-Type-Options: nosniff`, `Cache-Control` headers; uses `getPdo()` with `require database.php`
- **cron_send_reminder.php** — CLI-only proteksi: blocks non-CLI access (403 JSON response)
- **run_cron.bat** — Simplified (removed duplicate headers/blank lines)
- **TimeZone** — Added `date_default_timezone_set('Asia/Jakarta')` in `create_reminder.php`, `cron_trigger.php`, `settings.php`; `SET time_zone = '+07:00'` in `config/database.php`

### Added
- **cron_trigger.php** — `sendOutputLog()` writes to `logs/cron_output.log`
- **settings.php** — Cron Log tab with 3 log viewers, dynamic cron trigger URL with button, "Reminder Terjadwal Hari Ini", clear log section
- **includes/reminder_helpers.php** — `readLogFile()` strips `\r`, trims trailing whitespace
- **info.php** — Updated "Verifikasi Cron", "Monitoring", "Konsep Status Reminder" sections
- **README.md** — New project documentation with quick start
- **docs/** — Full documentation suite: PRD, BRD, User Flow, Sitemap, UI/UX Design, Database ERD, API Specification, Technical Specification, Role & Permission, Test Case/QA, Deployment Documentation
- **config/database.php** — `getenv()` defaults for DB connection

### Security
- **includes/auth.php** — Session cookie hardening (secure, httponly, samesite=Lax)
- **includes/auth.php** — `if (defined('AUTH_PHP_LOADED'))` guard to prevent double-loading
- **includes/mailer.php** — Dynamic EHLO host for SMTP (`$_SERVER['HTTP_HOST'] ?? 'appreminder.local'`)
- **.htaccess** — HTTPS redirect (localhost bypass), blocks `storage/`, `logs/`, `backup/`, security headers, anti-bot bypass for `cron_trigger.php`
- **.gitignore** — Added `vendor/`, `storage/.cron_secret`, `logs/*.log`, `backup/`, `uploads/*.pdf`, `*.sql`, `*.zip`, `run_cron.bat`, `test_cron.php`

### Chores
- Deleted all `_test_*.php` files from production

## [1.2.0] - 2026-08-24

### Added
- Web cron endpoint (`cron_trigger.php`) with secret key authentication
- cron-job.org integration support
- Anti-Bot bypass for cron endpoint via `.htaccess`

## [1.1.0] - 2026-08-23

### Added
- Daily repeat logic for reminders (status `done` → re-trigger next day)
- WhatsApp integration via Fonnte API
- Calendar view (monthly + daily)
- Dashboard stat cards (Terkirim, Gagal, Terlambat, Hari Ini, Terjadwal)

## [1.0.0] - 2026-08-20

### Added
- Initial release
- User authentication (admin/user roles)
- Reminder CRUD with email/WhatsApp
- Contact management (CSV import)
- SMTP settings configuration
- Master data management
- File attachment support
