<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/config/database.php';
$appConfig = require __DIR__ . '/config/app.php';
$cronSecret = $appConfig['cron_secret'] ?? '';
require __DIR__ . '/includes/layout.php';

requireLogin();

pageHeader('Informasi Aplikasi');
?>
<section class="page-title">
    <div>
        <h1><i class="bi bi-info-circle me-2"></i>Informasi Aplikasi</h1>
        <p class="muted">Panduan lengkap sistem pengingat App Reminder PT Mayora Indah Tbk - Jayanti 1</p>
    </div>
</section>

<div class="panel" style="padding: 28px;">
    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Apa itu App Reminder?</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 20px;">
        App Reminder adalah sistem pengingat otomatis yang digunakan untuk mengirim notifikasi via <strong>Email</strong> dan <strong>WhatsApp</strong>.
        Sistem ini dirancang untuk memastikan tidak ada reminder yang terlewat, baik untuk deadline harian, mingguan, bulanan, maupun tahunan.
        Aplikasi ini dikembangkan untuk <strong>PT Mayora Indah Tbk - Jayanti 1</strong> guna mendukung operasional sehari-hari.
    </p>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Fitur Utama</h2>
    <ul style="color: #334155; line-height: 1.8; margin-bottom: 20px; padding-left: 20px;">
        <li><strong>Multi-Channel:</strong> Kirim reminder via Email, WhatsApp, atau Keduanya sekaligus.</li>
        <li><strong>Rentang Tanggal:</strong> Tentukan Tanggal Mulai dan Tanggal Akhir untuk mengontrol jadwal pengiriman reminder dalam rentang hari tertentu.</li>
        <li><strong>Lampiran File:</strong> Lampirkan file (PDF, gambar, dokumen) pada reminder. Untuk WhatsApp, lampiran hanya dapat dikirim jika base URL file bersifat publik dan dapat diakses oleh API WhatsApp.</li>
        <li><strong>Kelola Kontak:</strong> Simpan data email dan nomor WhatsApp di database untuk pengiriman cepat.</li>
        <li><strong>Import / Export Kontak:</strong> Import dan export data kontak dalam format Excel (.xls).</li>
        <li><strong>Multi-User:</strong> Dukungan akun Admin dan User dengan hak akses berbeda.</li>
        <li><strong>Dashboard Ringkas:</strong> Statistik total, selesai, terlambat, hari ini, akan datang, dan total size.</li>
        <li><strong>Pencarian & Filter:</strong> Cari reminder berdasarkan perihal, departemen, PIC, atau kategori.</li>
        <li><strong>Tab Status:</strong> Filter reminder berdasarkan status: Semua, Hari Ini, Terlambat, Akan Datang, Selesai, Gagal.</li>
        <li><strong>Kalender:</strong> Lihat reminder dalam tampilan kalender bulanan.</li>
        <li><strong>Master Data:</strong> Kelola daftar departemen, sub-departemen, PIC, dan kategori secara terpusat.</li>
        <li><strong>Backup Database:</strong> Download salinan database dalam format SQL untuk keperluan backup atau migrasi.</li>
        <li><strong>Toast Notification:</strong> Notifikasi real-time untuk hasil pengiriman dan aksi lain.</li>
        <li><strong>Keamanan:</strong> Autentikasi session, CSRF protection, dan otorisasi berbasis peran.</li>
        <li><strong>Admin Reset Password:</strong> Admin dapat mengubah password semua user dari halaman Pengaturan.</li>
        <li><strong>Bulk Delete:</strong> Hapus beberapa reminder sekaligus dengan checkbox.</li>
        <li><strong>Kirim Otomatis:</strong> Reminder yang jadwalnya sudah terlewati akan dikirim secara otomatis oleh GitHub Actions Scheduler setiap 5 menit, bahkan ketika tidak ada user yang login atau website tidak dibuka.</li>
        <li><strong>Pagination:</strong> Daftar reminder dibagi menjadi beberapa halaman agar lebih mudah dibaca.</li>
    </ul>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Cara Menggunakan</h2>
    <div style="color: #334155; line-height: 1.7; margin-bottom: 20px;">
        <p><strong>1. Login</strong><br>Masukkan username dan password Anda. Jika belum memiliki akun, daftar terlebih dahulu melalui halaman registrasi. Jika lupa password, hubungi <strong>Admin CCTV</strong>.</p>
        <p style="margin-top: 10px;"><strong>2. Buat Reminder</strong><br>Klik menu <em>Buat Reminder</em> di sidebar, isi form dengan lengkap:
            <br>- Perihal, Departemen, Sub Dept, PIC, Kategori, Prioritas
            <br>- Tanggal Mulai, Tanggal Akhir, dan Jam Pengiriman
            <br>- Pengirim, Penerima (To/Cc)
            <br>- Channel: Email, WhatsApp, atau Keduanya
            <br>- Lampiran file (opsional)
        </p>
        <p style="margin-top: 10px;"><strong>3. Kelola Kontak</strong><br>Gunakan menu <em>Kelola Kontak</em> untuk menambah, mengedit, atau menghapus data email dan nomor WhatsApp. Admin dapat import/export kontak dalam format Excel.</p>
        <p style="margin-top: 10px;"><strong>4. Monitor di Dashboard</strong><br>Dashboard menampilkan statistik dan daftar reminder. Anda bisa:
         <br>- Melihat status reminder (Terkirim, Gagal, Terlambat, Hari Ini, Terjadwal)
         <br>- Filter reminder dengan tab: Semua, Hari Ini, Terlambat, Akan Datang, Selesai, Gagal
         <br>- Mencari dan memfilter reminder
         <br>- Mengirim ulang reminder yang gagal
         <br>- Mengedit atau menghapus reminder
         <br>- Menghapus beberapa reminder sekaligus dengan checkbox
         <br>- Navigasi halaman dengan pagination
        </p>
        <p style="margin-top: 10px;"><strong>5. Lihat Kalender</strong><br>Klik menu <em>Kalender</em> untuk melihat reminder dalam tampilan kalender bulanan.</p>
        <p style="margin-top: 10px;"><strong>6. Kirim Manual</strong><br>Klik tombol <em>Kirim Ulang</em> pada reminder gagal untuk mengirim kembali secara manual tanpa menunggu cron.</p>
        <p style="margin-top: 10px;"><strong>7. Pengaturan (Admin)</strong><br>Admin bisa mengelola:
            <br>- Pengaturan SMTP (host, port, username, password, encryption)
            <br>- Pengaturan WhatsApp API (URL, token, base URL file)
            <br>- Master Data (departemen, sub-departemen, PIC, kategori)
            <br>- Manajemen Akun (tambah/hapus user, ubah password user)
            <br>- Import/Export Kontak (Excel .xls)
            <br>- Backup Database (SQL)
            <br>- <strong>Cron Log</strong> — lihat log aktivitas cron, Trigger Access Log, dan CLI Output Log langsung di tab Settings
        </p>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Rentang Tanggal Pengiriman</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 16px;">
        Setiap reminder memiliki <strong>Tanggal Mulai</strong> dan <strong>Tanggal Akhir</strong>.
        Sistem akan mengirim reminder ketika hari ini berada di dalam rentang tersebut dan waktu pengiriman sudah terlewati.
        Rentang ini berguna untuk mendefinisikan jadwal pengiriman reminder dalam beberapa hari sekaligus.
    </p>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-calendar-day me-2" style="color:#6366f1;"></i>Tanggal Mulai</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Hari pertama reminder diizinkan dikirim. Jika hari ini sama dengan atau setelah tanggal mulai, reminder menjadi eligible.</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-calendar-check me-2" style="color:#16a34a;"></i>Tanggal Akhir</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Hari terakhir reminder diizinkan dikirim. Jika hari ini melebihi tanggal akhir, reminder dianggap terlambat.</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-clock me-2" style="color:#d97706;"></i>Jam Pengiriman</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Waktu pengiriman dalam format 24 jam. Reminder hanya dikirim jika jam pengiriman sudah terlewati di hari yang bersangkutan.</div>
        </div>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Channel Pengiriman</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-envelope me-2" style="color:#6366f1;"></i>Email</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Mengirim reminder via email SMTP. Mendukung lampiran file, To, dan Cc. Cocok untuk komunikasi resmi dan formal.</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-brands fa-whatsapp me-2" style="color:#25D366;"></i>WhatsApp</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Mengirim reminder via WhatsApp API (Fonnte). Lebih personal dan cepat dibaca. Catatan: untuk mengirim lampiran via WhatsApp, base URL file harus berupa URL publik yang dapat diakses oleh API Fonnte.</div>
        </div>
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px;">
            <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 6px;"><i class="fa-solid fa-paper-plane me-2" style="color:#f59e0b;"></i>Keduanya</div>
            <div style="font-size: 13px; color: #64748b; line-height: 1.6;">Mengirim reminder secara paralel via Email dan WhatsApp. Memastikan penerima mendapatkan notifikasi di kedua channel.</div>
        </div>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Konsep Status Reminder</h2>
    <ul style="color: #334155; line-height: 1.8; margin-bottom: 20px; padding-left: 20px;">
        <li><strong>Pending / Terjadwal:</strong> Reminder belum dikirim, menunggu waktu yang ditentukan.</li>
        <li><strong>Hari Ini:</strong> Reminder yang jadwal pengirimannya hari ini.</li>
        <li><strong>Terlambat:</strong> Reminder yang seharusnya sudah dikirim tapi belum terkirim.</li>
        <li><strong>Terkirim:</strong> Reminder berhasil dikirim via email dan/atau WhatsApp.</li>
        <li><strong>Gagal:</strong> Reminder gagal dikirim karena error SMTP, WhatsApp API, atau koneksi.</li>
        <li><strong>Repeat Harian:</strong> Reminder dengan rentang Tanggal Mulai – Tanggal Akhir lebih dari 1 hari akan dikirim setiap hari dalam rentang tersebut. Setelah terkirim, status berubah ke <code>done</code>, dan akan otomatis kirim ulang keesokan harinya selama masih dalam rentang tanggal.</li>
    </ul>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Cara Kerja Pengiriman Otomatis</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 20px;">
        Sistem mengirim reminder secara otomatis berdasarkan <strong>Tanggal Mulai</strong>, <strong>Tanggal Akhir</strong>, dan <strong>Jam Pengiriman</strong>.
        Pengiriman terjadi ketika:
    </p>
    <ul style="color: #334155; line-height: 1.8; margin-bottom: 20px; padding-left: 20px;">
        <li>Hari ini berada dalam rentang Tanggal Mulai – Tanggal Akhir.</li>
        <li>Jam pengiriman sudah terlewati (CONCAT(tanggal, jam) &le; NOW()).</li>
        <li>Status reminder masih <em>pending</em> atau <em>done</em> pada hari sebelumnya (repeat harian).</li>
    </ul>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 20px;">
        Pengiriman otomatis dilakukan oleh <strong>GitHub Actions Scheduler</strong> yang memanggil
        <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;font-size:12px;">cron_trigger.php</code> setiap 5 menit.
        Ini berarti reminder tetap terkirim <strong>tanpa perlu ada user yang login</strong> atau membuka website.
    </p>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Setup Scheduler (GitHub Actions)</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 16px;">
        Scheduler dijalankan otomatis oleh GitHub Actions. File workflow berada di repository GitHub Anda:
    </p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;">
        <div style="font-size:12px;font-weight:600;color:#6366f1;margin-bottom:8px;">File Workflow</div>
        <code style="background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;display:block;white-space:pre-wrap;word-break:break-all;">
.github/workflows/reminder.yml
        </code>
        <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.6;">
            Workflow ini dipicu (dipicu) secara otomatis oleh GitHub scheduler setiap 5 menit.
            Pastikan repository GitHub Anda terhubung dengan akun Anda.
        </div>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">GitHub Secret (CRON_TRIGGER_URL)</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 16px;">
        Agar GitHub Actions dapat memanggil <code>cron_trigger.php</code>, berikan secret berikut di pengaturan repository GitHub:
    </p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;">
        <div style="font-size:12px;font-weight:600;color:#6366f1;margin-bottom:8px;">Nama Secret</div>
        <code style="background:#f1f5f9;padding:6px 10px;border-radius:4px;font-size:12px;">CRON_TRIGGER_URL</code>
        <div style="font-size:12px;font-weight:600;color:#6366f1;margin-top:12px;margin-bottom:8px;">Nilai</div>
        <code style="background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;display:block;white-space:pre-wrap;word-break:break-all;">
<?= e(rtrim($appConfig['app_url'] ?? 'https://appreminder.zya.me', '/')) ?>/cron_trigger.php?key=<?= e($appConfig['cron_secret'] ?? 'YOUR_CRON_SECRET') ?>
        </code>
        <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.6;">
            Navigasi ke <strong>Settings → Secrets and variables → Actions → New repository secret</strong> di GitHub.
        </div>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Verifikasi Cron</h2>
    <p style="color: #334155; line-height: 1.7; margin-bottom: 16px;">
        Verifikasi apakah scheduler berjalan dengan membuka URL berikut di browser:
    </p>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:20px;">
        <code style="background:#f1f5f9;padding:8px 12px;border-radius:6px;font-size:12px;display:block;white-space:pre-wrap;word-break:break-all;">
<?= e(rtrim($appConfig['app_url'] ?? 'https://appreminder.zya.me', '/')) ?>/cron_trigger.php?key=<?= e($appConfig['cron_secret'] ?? 'YOUR_CRON_SECRET') ?>
        </code>
        <div style="font-size:12px;color:#64748b;margin-top:8px;line-height:1.6;">
            Jika mengembalikan JSON <code>{"success": true, "sent": 0, ...}</code>, berarti cron berjalan dengan baik.
            <br><br>
            <strong>Tiga file log</strong> yang dicatat sistem:
            <ul style="margin:8px 0 0 20px;padding:0;">
                <li><code>cron.log</code> — aktivitas dan error pengiriman reminder</li>
                <li><code>cron_trigger_access.log</code> — akses ke <code>cron_trigger.php</code> (dari GitHub Actions)</li>
                <li><code>cron_output.log</code> — output CLI lengkap (dari GitHub Actions <strong>atau</strong> Task Scheduler lokal)</li>
            </ul>
            Cek ketiganya di halaman <strong>Pengaturan → tab Cron Log</strong>.
        </div>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 8px;">Monitoring</h2>
    <ul style="color: #334155; line-height: 1.8; margin-bottom: 20px; padding-left: 20px;">
        <li>Cek log cron di tab <strong>Cron Log</strong> pada halaman Pengaturan.</li>
        <li>Pada tab Cron Log ditampilkan <strong>URL Cron Trigger</strong> dengan tombol <strong>Buka URL Cron</strong> untuk testing manual.</li>
        <li>Pada tab Cron Log ditampilkan <strong>Reminder Terjadwal Hari Ini</strong> — reminder yang jadwalnya hari ini dan belum waktunya dikirim.</li>
        <li>Dashboard menampilkan status reminder: <strong>Terkirim</strong>, <strong>Gagal</strong>, <strong>Terlambat</strong>, <strong>Hari Ini</strong>, <strong>Terjadwal</strong>.</li>
        <li>Tombol <strong>Kirim Ulang</strong> pada reminder gagal untuk mengirim kembali secara manual.</li>
        <li>Pastikan <strong>SMTP</strong> dan <strong>WhatsApp API</strong> sudah dikonfigurasi di Pengaturan.</li>
    </ul>

    <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 10px; padding: 16px; margin-top: 24px;">
        <div style="font-size: 13px; font-weight: 700; color: #6366f1; margin-bottom: 6px;"><i class="bi bi-building me-1"></i> Tentang PT Mayora Indah Tbk - Jayanti 1</div>
        <div style="font-size: 13px; color: #475569; line-height: 1.6;">
            Aplikasi ini dikembangkan khusus untuk kebutuhan operasional PT Mayora Indah Tbk - Jayanti 1.
            Sistem ini bertujuan untuk memastikan komunikasi dan pengingat berjalan efektif, efisien, dan terstruktur.
        </div>
    </div>
</div>

<?php pageFooter(); ?>
