# Deployment Documentation
## App Reminder

## 1. Prerequisites

| Component | Minimum Version |
|---|---|
| PHP | 8.0+ |
| MySQL | 8.0+ / MariaDB 10.4+ |
| Apache | 2.4+ (untua `.htaccess` support) |
| OS | Windows 10+, Linux (tested on XAMPP) |

### PHP Extensions
```bash
php -m | grep -E "pdo_mysql|mbstring|fileinfo|zip"
```
Must include: `pdo_mysql`, `mbstring`, `fileinfo`, `zip`

## 2. Deployment Methods

### Method A: Production to AeonFree

1. **Upload files**
   - Zip project (exclude `vendor/`, `logs/`, `storage/`, `backup/`, `uploads/`)
   - Upload via AeonFree File Manager
   - Extract to `htdocs/`

2. **Create database**
   - AeonFree MySQL → Create new database
   - Note: DB name = `epiz_12345678_appreminder`

3. **Import SQL**
   - Upload `appreminder.sql` via phpMyAdmin
   - Or: MySQL → Database → Import

4. **Configure `config/database.php`**
   - Update with DB credentials (dari Hosting Panel → MySQL Details)
   - Or set environment variables (jika hosting support)

5. **Configure SMTP**
   - Buka `https://yourapp.epizare...?app=settings`
   - Masukkan Gmail SMTP:
     - Host: `smtp.gmail.com`
     - Port: `587`
     - Encryption: `tls`
     - Username: `your-gmail@gmail.com`
     - Password: Gmail App Password
     - From Email: `your-gmail@gmail.com`
     - From Name: `App Reminder`

6. **Configure WhatsApp API**
   - Dari Fonnte: token + API URL
   - File Base URL: untuk akses attachment via WA

7. **Get Cron Secret Key**
   - Buka `info.php` → "Cron Trigger URL"
   - Copy key from URL atau baca `storage/.cron_secret`

8. **Setup GitHub Actions Scheduler**
   - Commit `.github/workflows/reminder.yml` to repository
   - Set GitHub Secret `CRON_TRIGGER_URL` = `https://appreminder.zya.me/cron_trigger.php?key={key}`
   - Scheduler dipicu otomatis setiap 5 menit

**OR: Alternative External Cron Service** (jika GitHub Actions tidak tersedia)
   - Buka https://easycron.com  atau  https://uptimerobot.com
   - Create Cronjob:
     - URL: `https://yourapp.zya.me/cron_trigger.php?key={key}`
     - Execute every: 5 minutes (`*/5 * * * *`)
     - Title: "App Reminder Cron"

9. **Anti-Bot Challenge Exclusion**
   - AeonFree Hosting Panel → Security → Anti Bot
   - Exclude `cron_trigger.php` from JS challenge

### Method B: Local Development (XAMPP)

1. **Clone/copy project**
   ```bash
   cp -r project /xampp/htdocs/appreminder
   ```

2. **Import database**
   ```bash
   mysql -u root -p
   CREATE DATABASE appreminder CHARACTER SET utf8mb4;
   USE appreminder;
   SOURCE appreminder.sql;
   ```

3. **Create `.env` or use defaults**
   - Default config: localhost/root/empty password
   - Run `php -r "echo getenv('DB_HOST') ?: 'localhost';"`

4. **Start local cron (Windows Task Scheduler)**
   - Buka Task Scheduler
   - Buat task baru:
     - Name: "App Reminder Cron"
     - Trigger: every 1 minute
     - Action: Start a program → Browse `run_cron.bat`
   - Pastikan MySQL service running

5. **Verify**
   - Buka `http://localhost/appreminder/login.php`
   - Akses `info.php` → cek URL cron trigger

## 3. Post-Deployment

### Verification Checklist
- [ ] Login admin (admin/admin123) berhasil
- [ ] Login user (user/user123) berhasil
- [ ] Dashboard stat cards tampil
- [ ] Buat reminder test → dapat email
- [ ] Cron URL dapat diakses via browser
- [ ] Cron-job.org trigger berhasil (cek `cron_trigger_access.log`)
- [ ] Settings dapat diakses admin
- [ ] User tidak dapat akses settings

### Email Deliverability
- Pastikan domain/IP tidak di-blacklist
- Cek spam folder
- Gunakan Gmail App Password (bukan password biasa)

### SSL Certificate
- Production: HTTPS sudah disediakan AeonFree
- Local: XAMPP (http:// sudah cukup untuk dev)

## 4. Maintenance

### Backup
```bash
# Manual backup
php backup.php > backup_$(date +%Y%m%d).sql
```
- Auto-backup: dapat ditambahkan ke GitHub Actions via endpoint (opsional)

### Log Rotation
| File | Rotate at | Action |
|---|---|---|
| `cron.log` | 10MB | Clear via Settings → Cron Log |
| `cron_output.log` | 5MB | Clear via Settings → Cron Log |
| `cron_trigger_access.log` | 10MB | Clear via Settings → Cron Log |

### Updates
1. Export `appreminder.sql` sebelum deploy baru
2. Backup `uploads/` dan `smtp_settings` values
3. Deploy new files → restore DB + SMTP settings

## 5. Rollback
1. Buka AeonFree File Manager
2. Upload `appreminder.sql` backup (replace current)
3. Restore `uploads/` files if needed

## 6. Troubleshooting

| Issue | Check |
|---|---|
| Cron tidak jalan | 1. Akses URL di browser — error? 2. Cek hosting → Security → Anti Bot 3. Cek cron-jobs.org status |
| Email tidak terkirim | 1. Test SMTP di settings.php 2. Cek `last_error` di reminder 3. Pastikan App Password valid |
| WhatsApp tidak terkirim | 1. Cek Fonnte token 2. Cek WA number format 3. Cek `wa_file_base_url` |
| Reminder stuck `pending` | 1. Cek `start_date <= CURDATE()` 2. Cek `reminder_time <= NOW()` 3. Cek timezone DB |
| Dashboard kosong | 1. Login sebagai user yang punya reminder 2. Atau login sebagai admin |
