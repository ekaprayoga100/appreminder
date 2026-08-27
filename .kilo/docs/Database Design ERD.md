# Database Design / ERD
## App Reminder

## Entity Relationship Diagram

```mermaid
erDiagram
    USERS ||--o{ REMINDERS : "created_by"
    USERS ||--o{ EMAIL_DATA : "created_by"
    USERS ||--o{ MASTER_DATA : "created_by"
    
    USERS {
        int id PK
        varchar(100) name
        varchar(150) email
        varchar(50) username
        varchar(255) password
        enum role "admin|user"
        timestamp created_at
    }
    
    REMINDERS {
        int id PK
        varchar(255) subject
        varchar(100) department
        varchar(100) sub_department
        varchar(150) pic
        varchar(100) category
        enum priority "low|medium|high|urgent"
        text message
        date reminder_date
        date start_date
        date end_date
        time reminder_time
        varchar(150) sender_email
        text to_emails
        text cc_emails
        varchar(255) attachment_path
        int attachment_size
        enum status "pending|done|failed"
        datetime sent_at
        text last_error
        int created_by FK
        enum send_channel "email|whatsapp|both"
        text whatsapp_numbers
        timestamp created_at
    }
    
    EMAIL_DATA {
        int id PK
        varchar(100) full_name
        varchar(100) department
        varchar(150) email
        varchar(20) whatsapp
        int created_by FK
        timestamp created_at
        timestamp updated_at
    }
    
    MASTER_DATA {
        int id PK
        enum type "department|sub_department|pic|category"
        varchar(150) value
        int sort_order
        timestamp created_at
    }
    
    SMTP_SETTINGS {
        tinyint id PK
        varchar(150) host
        int port
        enum encryption "tls|ssl|none"
        varchar(150) username
        varchar(255) password
        varchar(150) from_email
        varchar(100) from_name
        int timeout
        varchar(255) wa_api_url
        varchar(255) wa_api_token
        varchar(255) wa_file_base_url
        timestamp updated_at
    }
    
    REMINDERS }|--|| USERS : created_by
    EMAIL_DATA }|--|| USERS : created_by
```

## Table Relationships

```
users (1) ────< reminders (N)     [created_by]
users (1) ────< email_data (N)     [created_by]
users (1) ────< master_data (N)    [created_by] (implicit via role=admin)
```

## Field Details

### reminders.status
| Value | Meaning | Cron Query Logic |
|---|---|---|
| `pending` | Belum dikirim | Dipilih oleh cron query |
| `done` | Terkirim berhasil | Dipilih lagi jika `DATE(sent_at) < CURDATE()` (repeat harian) |
| `failed` | Gagal kirim | Perlu kirim ulang manual |

### reminders.send_channel
| Value | Channel |
|---|---|
| `email` | SMTP email only |
| `whatsapp` | WhatsApp (Fonnte) API only |
| `both` | Email + WhatsApp |

### smtp_settings (Singleton)
Table tunggal dengan `id=1`, diupdate via Settings UI.

### master_data.type
- `department`: Daftar department (HRD, IT, Produksi, dll)
- `sub_department`: Sub-department
- `pic`: Nama PIC
- `category`: Kategori reminder (Meeting, Laporan, Maintenance, dll)

## Indexing

```sql
-- Performance indexes
PRIMARY KEY (id)
KEY fk_reminders_users (created_by)
KEY fk_email_data_users (created_by)
UNIQUE KEY uniq_type_value (type, value)
```

## Schema File
- `appreminder.sql` — contains CREATE TABLE + seed data
- Import via: `mysql -u root -p appreminder < appreminder.sql`
- Seed users: admin/admin123, user/user123
