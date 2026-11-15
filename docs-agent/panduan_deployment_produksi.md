# Panduan Deployment & Kesiapan Produksi (`ArenaKita`)

Dokumen ini menjadi acuan operasional untuk men-deploy aplikasi **Booking Lapangan Olahraga (Laravel + PostgreSQL + Inertia Vue SSR)** ke lingkungan produksi secara aman.

---

## 1. Checklist `.env` Produksi

Pastikan variabel berikut terpasang di `.env` produksi (jangan pernah men-commit `.env` ke repositori):

- **Aplikasi & Debug:**
  - `APP_ENV=production`
  - `APP_DEBUG=false` *(wajib `false` agar stack trace tidak pernah tampil ke pengguna)*
  - `APP_URL=https://domain-produksi.example`
  - `APP_KEY=base64:...` *(simpan di secret manager; **jangan** digabungkan bersama file backup database)*
- **Sesi & Cookie (OWASP A02):**
  - `SESSION_DRIVER=database`
  - `SESSION_LIFETIME=120`
  - `SESSION_SECURE_COOKIE=true`
  - `SESSION_HTTP_ONLY=true`
  - `SESSION_SAME_SITE=lax`
- **Database PostgreSQL:**
  - `DB_CONNECTION=pgsql`
  - `DB_HOST=...`
  - `DB_PORT=5432`
  - `DB_DATABASE=booking_lapang`
  - `DB_USERNAME=...`
  - `DB_PASSWORD=...`
  - `DB_TIMEZONE=UTC`
  - `DB_SSLMODE=require` *(aktifkan TLS untuk koneksi database)*
- **Queue, Cache & Mail:**
  - `QUEUE_CONNECTION=database` *(atau `redis`)*
  - `CACHE_STORE=database` *(atau `redis`)*
  - `MAIL_MAILER=smtp` *(konfigurasikan kredensial SMTP produksi dan `MAIL_FROM_ADDRESS`)*
- **OAuth Google (Khusus Pembooking):**
  - `GOOGLE_CLIENT_ID=...`
  - `GOOGLE_CLIENT_SECRET=...`
  - `GOOGLE_REDIRECT_URI=https://domain-produksi.example/auth/google/callback`

> **Catatan Seeder:** `LocalDevelopmentSeeder` terkunci untuk environment `local`, `staging`, dan `testing`. Di produksi, jalankan hanya seeder master yang diperlukan (`SubscriptionPlanSeeder`, `SportSeeder`) dan buat akun Superadmin awal secara aman melalui CLI/tinker.

---

## 2. Konfigurasi PostgreSQL (`btree_gist` & Backup)

1. **Aktifkan Ekstensi `btree_gist`:**
   ```sql
   CREATE EXTENSION IF NOT EXISTS btree_gist;
   ```
   Ekstensi ini wajib aktif sebelum menjalankan `php artisan migrate --force` karena dipakai oleh exclusion constraint `bookings_no_overlap`, `price_rules`, dan `schedule_blocks`.

2. **Verifikasi Constraint & Trigger:**
   - Exclusion constraint `bookings_no_overlap` mencegah *double booking* di level database (`SQLSTATE 23P01`).
   - Trigger `forbid_update_delete()` menjaga `booking_status_logs` dan `audit_logs` tetap *append-only*.

3. **Backup Terenkripsi:**
   - Jadwalkan `pg_dump -Fc` harian ke penyimpanan terpisah yang dienkripsi (mis. `gpg` atau KMS cloud).
   - Simpan `APP_KEY` terpisah dari arsip backup database dan folder `storage/app/private` (bukti pembayaran terenkripsi).

---

## 3. Build Produksi & Optimasi Laravel

Jalankan pada proses build/release CI/CD:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
npm run build:ssr

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 4. Laravel Scheduler (`cron`)

Tambahkan satu entri `cron` untuk menjalankan scheduler Laravel setiap menit (menjalankan `bookings:expire`, `bookings:transition-ended`, dan pembersihan bukti sesuai retensi paket):

```cron
* * * * * cd /var/www/booking-lapang && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. Queue Worker & Inertia SSR (`supervisor`)

Gunakan **Supervisor** untuk menjaga queue worker (email konfirmasi/notifikasi rekening/reminder) dan server Inertia SSR tetap berjalan.

### `/etc/supervisor/conf.d/booking-lapang-worker.conf`
```ini
[program:booking-lapang-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/booking-lapang/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/booking-lapang/storage/logs/worker.log
stopwaitsecs=3600
```

### `/etc/supervisor/conf.d/booking-lapang-ssr.conf`
```ini
[program:booking-lapang-ssr]
command=node /var/www/booking-lapang/bootstrap/ssr/ssr.js
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/booking-lapang/storage/logs/ssr.log
```

Setelah membuat konfigurasi:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

---

## 6. Konfigurasi Nginx (Laravel + Inertia SSR + Proteksi Folder Upload)

Pastikan batas ukuran upload di Nginx (`client_max_body_size 6M;`) selaras dengan batas PHP (`upload_max_filesize = 6M`, `post_max_size = 8M`) dan validasi aplikasi (maks 5 MB foto venue, maks 2 MB bukti transfer).

```nginx
server {
    listen 443 ssl http2;
    server_name domain-produksi.example;
    root /var/www/booking-lapang/public;
    index index.php;

    client_max_body_size 6M;

    # Folder upload publik: nonaktifkan total PHP, CGI, HTML, dan SVG
    location ^~ /storage/venue-photos/ {
        alias /var/www/booking-lapang/storage/app/public/venue-photos/;
        autoindex off;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Cache-Control "public, max-age=2592000, immutable";

        location ~* \.(php|phtml|php[3-8]|phar|cgi|pl|py|sh|html?|xhtml|svg)$ {
            return 403;
        }

        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

> **Catatan Apache:** Jika menggunakan Apache, folder `storage/app/public/venue-photos/.htaccess` sudah otomatis dibuat dan memblokir eksekusi `php`, `cgi`, `html`, `xhtml`, dan `svg` serta menonaktifkan directory listing (`Options -ExecCGI -Indexes`).
