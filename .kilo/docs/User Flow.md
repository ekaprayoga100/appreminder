# User Flow - App Reminder

## As a User (Non-Admin)

```mermaid
graph TD
    A[Login] --> B{Dashboard}
    B --> C[Lihat Stats: Terkirim/Gagal/Terlambat/Hari Ini/Terjadwal]
    B --> D[Buat Reminder Baru]
    D --> D1[Isi: Subject, Dept, PIC, Kategori, Prioritas]
    D1 --> D2[Pilih: Channel email/WA/both, Tanggal mulai/akhir, Jam]
    D2 --> D3[Upload attachment (opsional)]
    D3 --> D4[Pilih kontak penerima]
    D4 --> E[Simpan Reminder]
    E --> B

    B --> F[Klik reminder tertentu]
    F --> F1[Lihat detail]
    F1 --> F2[Edit reminder]
    F1 --> F3[Kirim ulang manual]
    F1 --> F4[Hapus reminder]

    B --> G[Buka Kalender]
    G --> G1[Lihat reminder di kalender]
    G1 --> F

    B --> H[Kelola Kontak]
    H --> H1[Import CSV]
    H1 --> H2[Pilih kolom: nama, dept, email, WA]
    H --> B

    B --> I[Ganti Password]
    I --> J[Logout]
```

## As an Admin

```mermaid
graph TD
    A[Login sebagai Admin] --> B{Dashboard}
    B --> C[Monitoring Cron]
    C --> C1[Lihat 3 log: cron.log, cron_trigger_access.log, cron_output.log]
    C --> C2[Lihat URL cron trigger + klik "Buka URL Cron"]
    C --> C3[Lihat reminder terjadwal hari ini]

    B --> D[Pengaturan]
    D --> D1[SMTP Settings - host, port, encryption, credentials]
    D2[WhatsApp API - URL, token, file_base_url]
    D3[Master Data - department, sub-dept, PIC, kategori]
    D4[Cron Log - 3 file log viewer + clear button]

    D --> D5[Kelola Pengguna]
    D5 --> D5a[Buat user baru]
    D5 --> D5b[Edit role user]
    D5 --> D5c[Reset password user]
```

## Error Recovery Flow

```mermaid
graph TD
    ERR[Reminder gagal terkirim] --> ERR1[Status = failed, last_error tercatat]
    ERR1 --> ERR2[Admin lihat error di dashboard]
    ERR2 --> ERR3[Pilih "Kirim Ulang"]
    ERR3 --> ERR4[Perbaiki konfigurasi (SMTP/API)]
    ERR4 --> ERR5[Kirim ulang berhasil → status = done]
```
