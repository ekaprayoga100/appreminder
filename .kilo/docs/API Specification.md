# API Specification - App Reminder

## Base URL
```
https://appreminder.zya.me
```

## Authentication
All pages (except `login.php`, `register.php`, `cron_trigger.php`) require session auth.
Session config: `session.cookie_secure=true`, `cookie_httponly=true`, `SameSite=Lax`

---

## Endpoints

### 1. Login
```
POST /login.php
```
| Parameter | Type | Required | Description |
|---|---|---|---|
| username | string | yes | Username |
| password | string | yes | Password |

**Response:** Redirect to `dashboard.php` (success) or reload with error (fail)

### 2. Register
```
POST /register.php
```
| Parameter | Type | Required | Description |
|---|---|---|---|
| name | string | yes | Full name |
| email | string | yes | Email (unique) |
| username | string | yes | Username (unique) |
| password | string | yes | Password |
| confirm_password | string | yes | Password confirmation |
| role | enum | no | Default: `user` |

### 3. Dashboard
```
GET /dashboard.php
GET /dashboard.php?channel={email|whatsapp|both}&search={keyword}
```
| Param | Type | Default | Description |
|---|---|---|---|
| channel | string | all | Filter by send_channel |
| search | string | - | Search by subject/PIC |

**Response:** HTML dashboard with stats + filtered reminder table

### 4. Calendar
```
GET /calendar.php
GET /calendar.php?date=YYYY-MM-DD
```

**Response:** HTML calendar view. Clickable days show reminder list modal.

### 5. Create Reminder
```
POST /create_reminder.php
```
| Parameter | Type | Required | Description |
|---|---|---|---|
| subject | string | yes | Reminder subject |
| department | string | yes | From master_data |
| sub_department | string | no | From master_data |
| pic | string | no | From master_data |
| category | string | no | From master_data |
| priority | enum | no | low/medium/high/urgent |
| message | text | no | Reminder message body |
| start_date | date | yes | Kirim dimulai dari |
| end_date | date | yes | Kirim sampai |
| reminder_time | time | yes | Jam pengiriman |
| sender_email | string | yes | From SMTP settings |
| to_emails | string | yes | Comma-separated emails |
| cc_emails | string | no | Comma-separated CC emails |
| send_channel | enum | yes | email/whatsapp/both |
| whatsapp_numbers | string | no | Comma-separated WA numbers |
| attachment | file | no | PDF/Gambar/Doc (max 5MB) |

**Response:** JSON `{"success":true,"id":123}` or redirect with error

### 6. Edit Reminder
```
GET /edit_reminder.php?id=123
POST /edit_reminder.php
```
Same parameters as create, plus:
| Parameter | Type | Description |
|---|---|---|
| id | int | Reminder ID |
| remove_attachment | bool | Set 1 untuk hapus lampiran |

### 7. Delete Reminder
```
POST /delete_reminder.php
```
| Parameter | Type | Required |
|---|---|---|
| id | int | yes |

**Response:** Redirect to `dashboard.php`

### 8. Send Reminder (Manual)
```
POST /send_reminder.php
```
| Parameter | Type | Required |
|---|---|---|
| id | int | yes |

**Response:** JSON `{"success":true,"sent":1,"failed":0}`

### 9. Email Data
```
GET /data_email.php
POST /data_email.php
```
- `GET`: List all contacts
- `POST`: Upload CSV (columns: full_name, department, email, whatsapp)

### 10. Attachment
```
GET /attachment.php?id=123
```
Returns: Inline PDF/image | File download | 403 if no permission

### 11. Settings (Admin)
```
GET /settings.php?tab={remind|cronlog|accounts}
POST /settings.php
```

**Tabs:**
| Tab | Description |
|---|---|
| remind | SMTP, WhatsApp API, Master Data CRUD |
| cronlog | 3 log files, cron URL, scheduled reminders |
| accounts | User list, role management |

### 12. Password Change
```
GET /password_change.php
POST /password_change.php
```
| Parameter | Type | Required |
|---|---|---|
| current_password | string | yes |
| new_password | string | yes |
| confirm_password | string | yes |

### 13. Backup
```
GET /backup.php
```
**Response:** SQL file download

---

## Cron API

### Web Cron Trigger
```
GET /cron_trigger.php?key={secret}
```
**Response:**
```json
{"success":true,"sent":5,"failed":0,"time":"2026-08-25 03:18:40"}
```

### Cron Engine (CLI)
```
php cron_send_reminder.php
```
Executed by `run_cron.bat` via Windows Task Scheduler.
Requires `cli` SAPI — returns 403 if accessed via web.

### Email Data Export
```
GET /export_contacts_xls.php
```
**Response:** XLS file download
