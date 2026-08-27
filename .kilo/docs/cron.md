# Cron / Scheduler - AppReminder

## Overview

Reminder dikirim otomatis oleh **GitHub Actions Scheduler** yang memanggil `cron_trigger.php` di hosting AeonFree setiap 5 menit. Ini memungkinkan reminder terkirim tanpa perlu ada user yang login atau membuka website. **cron-job.org sudah tidak digunakan lagi.**

Alternatif lokal tetap tersedia via **Windows Task Scheduler** (`run_cron.bat`) untuk development.

## Components

| File | Function |
|---|---|
| `config/app.php` | App config — `cron_secret` (auto-generated), `app_url` (default: `https://appreminder.zya.me`) |
| `cron_send_reminder.php` | CLI script — mencari reminder dengan status `pending` yang sudah due dan mengirim via email/WhatsApp |
| `cron_trigger.php` | Web-based cron trigger — dipanggil oleh GitHub Actions setiap 5 menit |
| `.github/workflows/reminder.yml` | GitHub Actions workflow — scheduler otomatis |
| `run_cron.bat` | Batch wrapper untuk Windows Task Scheduler (dev/local) |
| `storage/.cron_secret` | Secret key untuk otentikasi `cron_trigger.php` (auto-generated) |
| `logs/cron.log` | Log aktivitas cron (jumlah reminder terkirim, error) |
| `logs/cron_trigger_access.log` | Log akses ke `cron_trigger.php` (dari GitHub Actions) |
| `logs/cron_output.log` | Output CLI — ditulis oleh `cron_trigger.php` dan/atau `run_cron.bat` |

> **Catatan**: `cron_trigger.php` menulis ke `cron_output.log` melalui `sendOutputLog()`, sehingga log terlihat di halaman Settings → tab Cron Log bahkan ketika cron dipicu eksternal (GitHub Actions).

## Environment Variables

### Database (`config/database.php`)
| Variabel | Default | Deskripsi |
|---|---|---|
| `DB_HOST` | `localhost` | MySQL host |
| `DB_NAME` | `appreminder` | Nama database |
| `DB_USER` | `root` | User database |
| `DB_PASS` | `` | Password database |

Untuk local development, gunakan default (localhost, root, tanpa password).

### App Config (`config/app.php`)
| Variabel | Default | Deskripsi |
|---|---|---|
| `APP_CRON_SECRET` | Auto-generated | Secret key otentikasi cron |
| `APP_URL` | `https://appreminder.zya.me` | Base URL aplikasi |

## Setup

### Production: GitHub Actions Scheduler (Recommended)

1. **Commit workflow ke repository GitHub**:
   ```
   .github/workflows/reminder.yml
   ```

2. **Set GitHub Secret** di repository Settings → Secrets and variables → Actions → New repository secret:
   - **Name**: `CRON_TRIGGER_URL`
   - **Value**: `https://appreminder.zya.me/cron_trigger.php?key=<CRON_SECRET>`

3. **Dapatkan cron secret**:
   ```
   storage/.cron_secret  (file otomatis tergenerate, jangan commit)
   ```

4. **Verifikasi** GitHub Actions berjalan:
   - Buka repository → Actions tab → lihat workflow run setiap 5 menit
   - Atau test manual: buka URL di browser, harus kembalikan JSON `{"success": true, ...}`

### Alternative: External Cron Service (Jika GitHub Actions tidak tersedia)

Jika Anda tidak dapat menggunakan GitHub Actions, alternatif layanan cron eksternal:
- [EasyCron](https://www.easycron.com)
- [UptimeRobot](https://uptimerobot.com)

**Konfigurasi:**
- **URL**: `https://appreminder.zya.me/cron_trigger.php?key=<CRON_SECRET>`
- **Schedule**: setiap 5 menit (`*/5 * * * *`)
- **Method**: GET

### Local: Windows Task Scheduler (Development)

1. **Daftarkan Task Scheduler**:
   ```powershell
   schtasks /create /tn "AppReminder Cron" /tr "C:\xampp\htdocs\appreminder\run_cron.bat" /sc minute /mo 5 /ru "$env:USERNAME" /f
   ```

2. **File `run_cron.bat`**:
   ```bat
   @echo off
   cd /d "C:\xampp\htdocs\appreminder"
   echo [%date% %time%] APP REMINDER CRON RUN >> "logs\cron_output.log"
   echo [%date% %time%] =================================== >> "logs\cron_output.log"
   "C:\xampp\php\php.exe" -f cron_send_reminder.php >> "logs\cron_output.log" 2>&1
   ```

3. **Verifikasi**:
   ```powershell
   schtasks /query /tn "AppReminder Cron"
   ```

## GitHub Actions Workflow

File: `.github/workflows/reminder.yml`

```yaml
name: AppReminder Scheduler

on:
  schedule:
    - cron: '*/5 * * * *'
  workflow_dispatch:

jobs:
  trigger-cron:
    runs-on: ubuntu-latest
    steps:
      - name: Trigger AppReminder cron
        run: |
          echo "Triggering cron trigger at $(date -u '+%Y-%m-%d %H:%M:%S') UTC"
          RESPONSE=$(curl -fsS -w "\n%{http_code}" \
            "${{ secrets.CRON_TRIGGER_URL }}" \
            -H "Cache-Control: no-cache" \
            -H "Accept: application/json")
          HTTP_CODE=$(echo "$RESPONSE" | tail -1)
          BODY=$(echo "$RESPONSE" | sed '$d')
          echo "HTTP Status: $HTTP_CODE"
          echo "Response: $BODY"
          if [ "$HTTP_CODE" != "200" ]; then
            echo "::warning ::cron_trigger.php returned HTTP $HTTP_CODE"
          fi
```

## Cron Query Logic

Memilih reminder yang harus dikirim:
- `status = 'pending'` atau `status = 'done'` dengan `DATE(sent_at) < CURDATE()`
- `start_date <= CURDATE()` (sudah mulai)
- `end_date >= CURDATE()` (belum kadaluarsa)
- `CONCAT(CURDATE(), ' ', reminder_time) <= NOW()` (waktu sudah tiba)

### Repeat Harian
Jika reminder dengan rentang Tanggal Mulai – Tanggal Akhir lebih dari 1 hari, sistem akan mengirim ulang setiap hari sampai `end_date`. Setelah terkirim, status berubah ke `done`, dan pada hari berikutnya akan otomatis kirim ulang karena kondisi `DATE(sent_at) < CURDATE()`.

## Retry Logic

`cron_trigger.php` memiliki retry logic untuk error koneksi MySQL:
- Maksimal 2 percobaan jika terjadi `MySQL server has gone away`
- Koneksi PDO dibuat ulang (`$pdo = getPdo()`) pada setiap retry

## Mencegah Pengiriman Ganda

Sistem mencegah duplikat pengiriman dengan:
1. **Database-level**: query hanya memilih reminder dengan `status = 'pending'` atau `status = 'done'` dengan `DATE(sent_at) < CURDATE()` — reminder yang sudah `done` hari ini tidak akan dipilih lagi
2. **Atomic UPDATE**: setelah pengiriman berhasil, `status` langsung diupdate ke `'done'` + `sent_at = NOW()` dalam transaksi yang sama
3. **Single cron instance**: GitHub Actions scheduler adalah single-threaded, tidak ada race condition

## Status Reminder

| Status | Deskripsi |
|---|---|
| `pending` | Belum dikirim, menunggu jadwal |
| `done` | Berhasil dikirim — dikirim ulang keesokan harinya jika repeat harian |
| `failed` | Gagal dikirim — cek `last_error`, klik "Kirim Ulang" di dashboard |

## Monitoring

Buka halaman **Settings → tab Cron Log** untuk melihat ketiga log, URL cron, dan reminder terjadwal hari ini:
- `cron.log` — aktivitas dan error pengiriman reminder
- `cron_trigger_access.log` — akses ke `cron_trigger.php` (dari GitHub Actions)
- `cron_output.log` — output CLI lengkap (dari GitHub Actions **atau** Task Scheduler lokal)

### CLI
```powershell
# Log aktivitas
Get-Content "C:\xampp\htdocs\appreminder\logs\cron.log" -Tail 20

# Log akses cron trigger
Get-Content "C:\xampp\htdocs\appreminder\logs\cron_trigger_access.log" -Tail 20

# Log output CLI
Get-Content "C:\xampp\htdocs\appreminder\logs\cron_output.log" -Tail 20
```

## Anti-Bot JS Challenge (Hosting-Level)

Beberapa hosting gratis (AeonFree/zya.me) menggunakan **anti-bot JS challenge** di reverse proxy (openresty/nginx), **sebelum** request mencapai Apache/PHP.

**Gejala**: `curl https://domain/cron_trigger.php?key=...` mengembalikan HTML `<script src="/aes.js">` bukan JSON.

**Solusi**:
1. **Hosting panel** → Security → Anti-DDoS / JS Challenge → whitelist `cron_trigger.php`
2. Jika tidak bisa dimatikan → gunakan **Task Scheduler lokal** (`run_cron.bat`) sebagai primary scheduler

## Error Handling

- Database connection errors dicatat ke `cron.log` dan keluar dengan HTTP 500 + pesan error JSON
- Jika `cron_output.log` terisi "Koneksi database gagal", pastikan MySQL berjalan dan database `appreminder` sudah impor dari `appreminder.sql`
