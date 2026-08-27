# Sitemap - App Reminder

## Site Hierarchy

```
/
├── login.php                  [Guest] Login sebagai user/admin
├── register.php               [Guest] Registrasi user baru (hanya admin yang bisa akses)
├── logout.php                 [Auth] Logout & destroy session
│
├── dashboard.php              [Auth] Dashboard utama
│   ├── Statistics cards       (Terkirim, Gagal, Terlambat, Hari Ini, Terjadwal)
│   ├── Reminder table         (search, channel filter, bulk delete)
│   └── Action stack           (kirim ulang, edit, hapus per reminder)
│
├── calendar.php               [Auth] Kalender bulanan + daily view
│   ├── Monthly calendar       (color-coded by priority)
│   └── Event modal            (reminder detail popup)
│
├── create_reminder.php        [Auth] Form buat reminder
│   ├── Reminder details       (subject, dept, PIC, category, priority, message)
│   ├── Schedule               (start_date, end_date, reminder_time)
│   ├── Recipients             (to_emails, cc_emails, send_channel)
│   ├── Attachment             (upload max 5MB)
│   └── Contacts selector      (email_data list)
│
├── edit_reminder.php          [Auth] Form edit reminder
│   ├── Same fields as create
│   └── Attachment management  (view, replace, remove)
│
├── data_email.php             [Auth] Kelola kontak/email
│   ├── Contact list           (filter by department)
│   ├── Upload CSV             (columns: full_name, department, email, whatsapp)
│   └── Export XLS             (export_contacts_xls.php)
│
├── attachment.php             [Auth] Download/view attachment
│   └── ?id=<reminder_id>      (inline for PDF/images, attachment otherwise)
│
├── send_reminder.php          [Auth/Admin] Kirim ulang manual
│   └── POST id=<reminder_id>  (trigger email+WA)
│
├── password_change.php        [Auth] Ganti password
│
├── info.php                   [Auth] Halaman informasi/dokumentasi
│
├── settings.php               [Auth/Admin] Pengaturan sistem
│   ├── Tab: Remind             (SMTP, WA API, master data)
│   ├── Tab: Cron Log           (3 log files, URL cron, schedule list)
│   └── Tab: Accounts           (user management)
│
├── cron_trigger.php           [Secret] Web cron trigger
│   └── GET ?key=<secret>      (dipanggil oleh GitHub Actions tiap 5 menit)
│
├── cron_send_reminder.php     [CLI] Cron engine (Task Scheduler)
│   └── Dipanggil oleh run_cron.bat
│
└── backup.php                 [Auth/Admin] Download database backup
```

## Admin-Only Pages
- `settings.php`
- `password_change.php` (untuk user lain)
- `backup.php`
- `register.php` (buat user baru)

## Guest-Only Pages
- `login.php`
- `register.php`

## Public (No Auth Required)
- `.htaccess` — HTTPS redirect (localhost exempt)
- `logs/`, `storage/`, `backup/` — blocked via .htaccess
