# BRD - Business Requirements Document
## App Reminder - PT Mayora Indah Tbk - IRGA Jayanti 1

### 1. Business Problem
- Reminder operasional sering terlewat karena bergantung pada manual follow-up
- Koordinasi lintas department kurang efisien
- Tidak ada tracking/apdetan historis pengiriman

### 2. Business Objectives
| Objective | Target | Deadline |
|---|---|---|
| 100% reminder terkirim otomatis | 5/7 reminder hari ini terkirim <5 menit setelah jadwal | Q3 2026 |
| Kurangi follow-up manual | 80% pengurangan email follow-up manual | Q3 2026 |
| User adoption | 100% admin & key user aktif pakai dashboard | Q2 2026 |

### 3. Stakeholders
| Role | Interest |
|---|---|
| Admin IT | Deploy & maintain sistem |
| Admin HRD | Atur template reminder |
| Department Head | Monitor send status |
| End User (requester) | Buat reminder untuk team |

### 4. Business Rules

#### 4.1 Reminder Scheduling
- Reminder dikirim pada `CONCAT(CURDATE(), ' ', reminder_time)` ketika `start_date <= CURDATE() <= end_date`
- Status `done` dengan `DATE(sent_at) < CURDATE()` → dikirim ulang keesokan hari (repeat harian)

#### 4.2 Channel Priority
1. Jika `send_channel = both` → kirim email dulu, baru WhatsApp
2. Jika salah satu gagal → status reminder = `failed`, tampilkan error di `last_error`

#### 4.3 Auth Rules
- User hanya lihat/bisa akses reminder yang dibuatnya sendiri
- Admin akses semua reminder
- Login wajib pakai session (cookie secure)

#### 4.4 Data Retention
- Log cron disimpan tanpa batas (rotasi manual via Settings)
- Backup SQL otomatis harian (cron)
- Attachment max 5MB

### 5. Constraints
- Hosting: zya.me (free hosting, ada JS challenge/anti-bot)
- PHP 8.0+ (XAMPP local), MySQL
- Email: SMTP Gmail App Password
- WhatsApp: Fonnte API
