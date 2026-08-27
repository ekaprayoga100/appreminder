# PRD - Product Requirements Document
## App Reminder

### 1. Purpose
Sistem pengingat otomatis via email dan WhatsApp untuk PT Mayora Indah Tbk - IRGA Jayanti 1.

### 2. Target Users
- **Admin**: Full akses — kelola semua reminder, pengguna, kontak, pengaturan
- **User**: CRUD reminder sendiri, lihat dashboard & kalender

### 3. Core Features

| Feature | Priority | Description |
|---|---|---|
| Scheduled Reminders | High | Kirim reminder otomatis berdasarkan start_date, end_date, reminder_time |
| Repeat Harian | High | Reminder dengan rentang >1 hari dikirim tiap hari sampai end_date |
| Multi-channel | High | Email via SMTP, WhatsApp via Fonnte API, atau keduanya |
| File Attachment | Medium | Attach PDF/Gambar/Dokumen — embedded di email, link publik di WhatsApp |
| Contact Management | Medium | Kelola kontak email (CSV import, PIC-based) |
| Department Filter | Medium | Filter reminder by department/sub-department |
| Priority Levels | Low | low, medium, high, urgent |
| Dashboard Stats | Medium | Stat kartu: Terkirim, Gagal, Terlambat, Hari Ini, Terjadwal |
| Calendar View | Medium | Kalender interaktif dengan reminder ditandai |
| User Management | High | Admin kelola user & password; user ganti password sendiri |
| Cron Monitoring | Medium | Lihat 3 log file: cron.log, cron_trigger_access.log, cron_output.log |

### 4. Non-Goals
- Real-time notification (bukan push notification)
- Mobile app tersendiri (responsive web only)
- Integrasi dengan sistem HR/ERP lain

### 5. Success Metrics
- Reminder terkirim tepat waktu ≥95%
- Cron trigger berhasil ≥99% dari up-time server
- Dashboard load time <3 detik
- Error rate pengiriman <5%
