# Test Case / QA
## App Reminder

## 1. Test Environment
- **Local**: XAMPP (PHP 8.1, MySQL 8.0, Apache)
- **Production**: AeonFree (PHP 8.x, MySQL, Apache)
- Base URL: http://localhost/appreminder (local) | https://appreminder.zya.me (production)

## 2. Test Data Seeding
Run `appreminder.sql` to import:
- User: `admin` / `admin123` (role: admin)
- User: `user` / `user123` (role: user)
- SMTP settings: pre-configured with Gmail host
- Master data: department=HRD, IT, Produksi, Finance

---

## 3. Functional Test Cases

### 3.1 Authentication
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-A01 | Valid login (admin) | Login admin/admin123 | Redirect dashboard, session active | |
| TC-A02 | Valid login (user) | Login user/user123 | Redirect dashboard | |
| TC-A03 | Invalid credentials | Login wrong/password | Error message, no redirect | |
| TC-A04 | Empty fields | Submit empty login form | Validation error | |
| TC-A05 | Session expired | Clear cookies, access dashboard | Redirect to login.php | |
| TC-A06 | Admin access settings | Admin → settings.php | Full access granted | |
| TC-A07 | User access settings | User → settings.php | 403 Forbidden | |
| TC-A08 | Password change (own) | Ganti password diri | Success, can re-login | |
| TC-A09 | Password change (other) | Admin ganti password user | Success | |
| TC-A10 | Register (admin only) | User akses register.php | 403 Forbidden | |

### 3.2 Reminder Management (User)
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-R01 | Create reminder (email) | Isi form lengkap, channel=email | Reminder saved, status=pending | |
| TC-R02 | Create reminder (WA) | channel=whatsapp | Reminder saved, WA numbers required if channel=whatsapp/both | |
| TC-R03 | Create reminder (both) | channel=both | Reminder saved, both email+WA required | |
| TC-R04 | Create reminder (attachment) | Upload PDF 1MB | Attachment saved to uploads/, path stored | |
| TC-R05 | Edit own reminder | Edit subject/time | Updated successfully | |
| TC-R06 | Edit others' reminder | User edit reminder orang lain | 403 Forbidden | |
| TC-R07 | Delete own reminder | Klik hapus | Reminder deleted | |
| TC-R08 | Delete others' reminder | User hapus reminder admin | 403 Forbidden | |
| TC-R09 | Search reminder | Ketik keyword di dashboard | Filter results | |
| TC-R10 | Filter by channel | Pilih channel di dropdown | Filter results | |

### 3.3 Reminder Management (Admin)
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-R11 | Send reminder manual | Klik "Kirim" pada reminder | Status → done, sent_at updated | |
| TC-R12 | Send failed reminder | Klik "Kirim" pada reminder failed | Retry attempt | |
| TC-R13 | View all reminders | Admin dashboard | Semua reminder terlihat | |
| TC-R14 | Bulk delete reminders | Pilih multiple, hapus | Semua terhapus | |

### 3.4 Contact Management
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-C01 | Import CSV | Upload contacts.csv | Kontak ditambahkan | |
| TC-C02 | Import CSV (invalid format) | Upload txt file | Error: format tidak didukung | |
| TC-C03 | Import CSV (duplicate email) | Email sama diulang | Skip or update | |
| TC-C04 | Export XLS | Klik "Ekspor" | Download file .xls | |

### 3.5 Calendar
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-CAL01 | View month | Buka calendar.php | Kalender bulan tampil | |
| TC-CAL02 | Navigate month | Next/prev month | Kalender ganti bulan | |
| TC-CAL03 | Click day with reminders | Klik tanggal | Modal reminder muncul | |
| TC-CAL04 | Click day without reminders | Klik tanggal kosong | Modal kosong atau tidak muncul | |

### 3.6 Settings (Admin)
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-S01 | Update SMTP settings | Edit form, save | Settings updated | |
| TC-S02 | Update WA API settings | Edit WA fields, save | Settings updated | |
| TC-S03 | Add master data | Tambah department baru | Data muncul di dropdown | |
| TC-S04 | Clear log file | Klik "Clear Log" | Log file emptied | |
| TC-S05 | Cron trigger URL | Buka info.php | URL + key ditampilkan | |

### 3.7 Dashboard Stats
| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-D01 | Stat cards | Buka dashboard | 5 cards: sent, failed, overdue, today, scheduled | |
| TC-D02 | Stat accuracy | Setelah send, refresh | Stat bertambah | |

---

## 4. Cron & Reminder Delivery Test Cases

| TC-ID | Cron Scenario | Steps | Expected | Status |
|---|---|---|---|---|
| TC-CRON01 | Pending reminder delivered | Set reminder_time=now-5min, status=pending | Cron kirim, status→done | |
| TC-CRON02 | Daily repeat (done→done) | Set done reminder, sent_at=yesterday | Cron kirim ulang | |
| TC-CRON03 | Outside date range | start_date=future date | Tidak dikirim | |
| TC-CRON04 | Before reminder_time | reminder_time=10:00, sekarang=09:00 | Tidak dikirim | |
| TC-CRON05 | Failed reminder retry | Status failed, klik "Kirim" | Percobaan ulang | |
| TC-CRON06 | Web cron trigger | Akses cron_trigger.php?key=valid | JSON response success | |
| TC-CRON07 | Invalid secret key | cron_trigger.php?key=wrong | 403 Forbidden | |
| TC-CRON08 | CLI-only protection | Web akses cron_send_reminder.php | 403 Forbidden | |
| TC-CRON09 | Attachment delivery | Reminder dengan PDF | Email terkirim + attachment | |

---

## 5. Security Test Cases

| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-SEC01 | IDOR protection | User akses edit?id=reminder_orang_lain | 403 Forbidden | |
| TC-SEC02 | CSRF protection | Form POST tanpa session | Blocked | |
| TC-SEC03 | XSS protection | Input `<script>alert(1)</script>` | Sanitized output | |
| TC-SEC04 | SQL injection | Input `' OR 1=1--` di search | No injection | |
| TC-SEC05 | File upload restriction | Upload .php file | Rejected | |
| TC-SEC06 | .htaccess protection | Akses /storage/, /logs/ | 403 Forbidden | |
| TC-SEC07 | HTTPS redirect | Akses http:// (production) | Redirect to https:// | |

---

## 6. UI/UX Test Cases

| TC-ID | Description | Steps | Expected | Status |
|---|---|---|---|---|
| TC-UI01 | Responsive layout | Resize browser | Layout adjusts | |
| TC-UI02 | Empty state | Dashboard kosong | Show placeholder | |
| TC-UI03 | Loading state | Submit form | Spinner/loading | |
| TC-UI04 | Error message | Submit invalid data | Inline error msg | |
| TC-UI05 | Form validation | Isi form tidak lengkap | Highlight required fields | |

---

## 7. Test Execution Summary

| Category | Total | Pass | Fail | Notes |
|---|---|---|---|---|
| Auth | 10 | | | |
| Reminder (User) | 10 | | | |
| Reminder (Admin) | 4 | | | |
| Contact | 4 | | | |
| Calendar | 4 | | | |
| Settings | 5 | | | |
| Cron & Delivery | 9 | | | |
| Security | 7 | | | |
| UI/UX | 5 | | | |
| **Total** | **58** | | | |

## 8. Notes
- Semua test jalan secara manual
- Cron delivery test: gunakan email tes (gmail non-production) + WA sandbox
- Production test: gunakan GitHub Actions test run atau akses manual cron_trigger.php via browser
