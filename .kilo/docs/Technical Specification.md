# Technical Specification
## App Reminder - PT Mayora Indah Tbk - IRGA Jayanti 1

## 1. Architecture Overview

```
┌─────────────────────────────────────────────┐
│              Users (Browser)                 │
└──────────────┬──────────────────────────────┘
               │ HTTPS (Browser)
┌──────────────▼──────────────────────────────┐
│         Web Server (Apache/Nginx)           │
│         - .htaccess (security headers)      │
│         - JS Challenge / Anti-Bot bypass     │
└──────────────┬──────────────────────────────┘
               │
        ┌──────▼──────┐
        │  PHP 8.0+  │
        │            │
        │  ┌──────────┴──────────┐
        │  │    Frontend         │
        │  │  - dashboard.php    │
        │  │  - calendar.php     │
        │  │  - form pages       │
        │  └──────────┬──────────┘
        │             │
        │  ┌──────────▼──────────┐
        │  │    Backend          │
        │  │  - cron_trigger.php │
        │  │  - cron_send_       │
        │  │    reminder.php     │
        │  │  - send_reminder    │
        │  │    .php             │
        │  └─────────────────────┘
        │             │
        └──────┬──────┘
               │
    ┌─────────▼─────────┐
    │  Database Layer   │
    │  config/          │
    │  database.php     │
    ├───────────────────┤
    │  MySQL 8.0+       │
    │  appreminder DB   │
    └─────────┬─────────┘
              │
    ┌─────────▼─────────┐    ┌──────────────┐
    │  SMTP Server      │    │ WhatsApp API │
    │  (Gmail SMTP)     │    │ (Fonnte)     │
    └─────────┬─────────┘    └──────────────┘
```

## 2. Stack & Dependencies

| Layer | Technology |
|---|---|
| Language | PHP 8.0+ |
| Database | MySQL 8.0+ / MariaDB 10.4+ |
| Web Server | Apache (local: XAMPP) |
| OS | Windows (local), AeonFree (production) |
| SMTP | Gmail SMTP / App Password |
| WhatsApp | Fonnte API |
| Cron | GitHub Actions (web, 5min) + Windows Task Scheduler (local) |

### PHP Extensions Required
- pdo_mysql
- php_imap (for email fetch)
- php_mbstring
- php_fileinfo
- php_zip

### Third-party Libraries
- **PHPMailer** — SMTP email sending (`vendor/`)

## 3. Environment Configuration

### Local Development
```
DocumentRoot: C:/xampp/htdocs/appreminder
PHP: 8.1+
MySQL: localhost:3306
DB Name: appreminder
DB User: root
DB Pass: '' (empty)
```

### Production (AeonFree)
```
URL: https://appreminder.zya.me
PHP: 8.x (hosting default)
MySQL: MySQL (not localhost)
DB Name: epiz_xxx_appreminder
DB User: epiz_xxx_user
DB Pass: (secure password)
Cron: GitHub Actions every 5 minutes
```

### Environment Variables (config/database.php)
```php
Host:     getenv('DB_HOST') ?? 'localhost'
Name:     getenv('DB_NAME') ?? 'appreminder'
User:     getenv('DB_USER') ?? 'root'
Password: getenv('DB_PASS') ?? ''
Timezone: SET time_zone = '+07:00' (after PDO connect)
```

## 4. Cron Architecture

### 4.1 Web Cron (GitHub Actions)
- GitHub Actions scheduler → `https://appreminder.zya.me/cron_trigger.php?key={secret}`
- Every 5 minutes (`*/5 * * * *`)
- Workflow: `.github/workflows/reminder.yml`
- GitHub Secret: `CRON_TRIGGER_URL`
- Triggers email/WhatsApp sending via sendDueReminders()

### 4.2 Engine Cron (Windows Task Scheduler - Local)
- run_cron.bat → `php cron_send_reminder.php`
- Every 5 minutes
- Query: `SELECT * FROM reminders WHERE (status = 'pending' OR (status = 'done' AND DATE(sent_at) < CURDATE())) AND start_date <= CURDATE() AND end_date >= CURDATE() AND CONCAT(CURDATE(),' ',reminder_time) <= NOW()`

### 4.3 Retry Logic
```php
$max_retries = 3;
$pdo = getPdo(); // fresh connection per attempt
$stmt = $pdo->prepare($query);
// try-catch with retry
```

## 5. File Structure
```
/appreminder/
├── config/
│   └─ database.php
├── includes/
│   ├── auth.php           # session, login check
│   ├── mailer.php         # PHPMailer SMTP wrapper
│   └── reminder_helpers.php
├── storage/
│   └── .cron_secret       # secret key (gitignored)
├── logs/                  # cron.log, cron_output.log (gitignored)
├── backup/                # SQL backup (gitignored)
├── uploads/               # attachments (gitignored)
├── vendor/                # PHPMailer (gitignored)
├── *.php                  # page scripts
├── .htaccess
├── .gitignore
├── appreminder.sql
└── run_cron.bat
```

## 6. Security Measures

| Layer | Measure |
|---|---|
| Auth | Session-based, cookie secure + httponly |
| Auth | Password hashing: password_hash() bcrypt |
| Auth | Anti session fixation | Anti-bot JS bypass for cron endpoint | Input validation via parameterized PDO | XSS | htmlspecialchars() all output |
| IDOR | User can only access own reminders |
| Headers | X-Frame-Options, X-Content-Type-Options, Referrer-Policy |
| Files | Upload dir no-execute (.htaccess deny all) |
| Cron | Require secret key + CLI-only protection |

## 7. Error Handling

### Email Sending
```php
try {
    $mail->send();
    // UPDATE reminders SET status='done', sent_at=NOW()
} catch (Exception $e) {
    // UPDATE reminders SET status='failed', last_error=??
    // Log to logs/cron.log
}
```

### Cron Output
- `logs/cron_output.log` — output log via sendOutputLog()
- `logs/cron_trigger_access.log` — web access log
- `logs/cron.log` — full execution log

## 8. Performance

| Metric | Target |
|---|---|
| Dashboard load time | <3s |
| Send one email | <5s |
| Cron execution (10 reminders) | <60s |
| Calendar month load | <2s |

## 9. Monitoring

### Logs (Settings → Cron Log tab)
| File | Description |
|---|---|
| `cron.log` | Full cron execution (debug) |
| `cron_trigger_access.log` | Web cron access (who called) |
| `cron_output.log` | Output of cron_send_reminder.php |

### Health Checks
- `info.php` — displays cron trigger URL + last 3 logs
- Settings Cron Log tab — 3 log viewers with clear buttons
- Dashboard — status filter (pending, done, failed)
