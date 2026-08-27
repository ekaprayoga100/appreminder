# App Reminder
> Automated email & WhatsApp reminder scheduling system for PT Mayora Indah Tbk - IRGA Jayanti 1

## Quick Start

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- Apache web server

### Installation
```bash
# 1. Clone / copy project to web root
cp -r appreminder /var/www/html/appreminder

# 2. Import database
mysql -u root -p -e "CREATE DATABASE appreminder CHARACTER SET utf8mb4"
mysql -u root -p appreminder < appreminder.sql

# 3. Configure database (config/database.php)
#    Uses getenv() defaults: localhost / appreminder / root / (empty)

# 4. Install PHPMailer
composer install
```

### Run Locally
```bash
# Start MySQL + Apache (XAMPP)
# Access: http://localhost/appreminder/login.php

# Default accounts:
# Admin:  admin / admin123
# User:   user  / user123
```

### Run Cron (Local)
```bash
# Windows Task Scheduler → run_cron.bat (every 5 minutes)
# Or manual:
php cron_send_reminder.php
```

---

## Features

| Feature | Channels | Status Tracking |
|---|---|---|
| Scheduled Reminders | Email / WhatsApp / Both | pending / done / failed |
| Daily Repeat | Auto-repeats until `end_date` | done → sent again next day |
| File Attachments | PDF, images, docs (max 5MB) | Attachment path stored |
| Contact Management | CSV import / XLS export | Filter by department |
| Department Filter | Master data dropdowns | Sort order control |
| Priority Levels | low, medium, high, urgent | Visual color coding |
| Calendar View | Monthly + daily | Color-coded by priority |
| Dashboard Stats | 5 stat cards | sent, failed, overdue, today, scheduled |
| Cron Monitoring | 3 log viewers | cron.log, trigger access, output |
| User Management | Admin only | Create / edit / reset password |

## Project Structure
```
appreminder/
├── config/           Database config
├── includes/         Auth, mailer, helpers
├── logs/             Cron logs (gitignored)
├── storage/          Secrets (gitignored)
├── uploads/          Attachments (gitignored)
├── vendor/           PHPMailer (gitignored)
├── docs/             Documentation (.kilo/docs/)
├── *.php             Page scripts
├── appreminder.sql   Database schema + seed
├── .htaccess         Security headers, access control
├── .gitignore        Deployment ignore rules
├── run_cron.bat      Windows cron runner (local)
├── .github/
│   └── workflows/
│       └── reminder.yml  GitHub Actions scheduler
```

## Documentation
Semua dokumentasi lengkap ada di `.kilo/docs/`:

| Doc | Description |
|---|---|
| [PRD](.kilo/docs/PRD.md) | Product requirements |
| [BRD](.kilo/docs/BRD.md) | Business requirements |
| [User Flow](.kilo/docs/User%20Flow.md) | User journey maps |
| [Sitemap](.kilo/docs/Sitemap.md) | Page hierarchy |
| [UI Design](.kilo/docs/UI-UX%20Design.md) | Design system & screens |
| [Database ERD](.kilo/docs/Database%20Design%20ERD.md) | Schema & relationships |
| [API Spec](.kilo/docs/API%20Specification.md) | All endpoints |
| [Tech Spec](.kilo/docs/Technical%20Specification.md) | Architecture & stack |
| [Roles](.kilo/docs/Role%20and%20Permission.md) | Permission matrix |
| [Test Cases](.kilo/docs/Test%20Case%20QA.md) | QA test suite |
| [Deployment](.kilo/docs/Deployment%20Documentation.md) | Deploy guide |
| [Changelog](CHANGELOG.md) | Version history |
| [AGENTS](.kilo/docs/AGENTS.md) | Development guide + cron reference |
| [Cron Guide](.kilo/docs/cron.md) | Scheduler setup (GitHub Actions) |

## Cron Setup
Scheduler dijalankan oleh **GitHub Actions** — file workflow ada di `.github/workflows/reminder.yml`.
Cron dipicu otomatis setiap 5 menit, memanggil `cron_trigger.php` di hosting.

Lihat `.kilo/docs/cron.md` untuk panduan lengkap GitHub Actions setup & Windows Task Scheduler (local).

**GitHub Secret yang diperlukan:**
- `CRON_TRIGGER_URL` — full URL ke `cron_trigger.php?key=<secret>`
