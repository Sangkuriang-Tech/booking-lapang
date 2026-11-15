# Catatan Asumsi Sistem & Keputusan Produk (`ASSUMPTIONS.md`)

Dokumen ini mencatat seluruh keputusan bisnis, asumsi teknis, dan perilaku *edge case* yang disepakati selama pengembangan. **AI Coding Agent wajib memperbarui dokumen ini di setiap akhir fase (F0–F6)** jika terdapat asumsi baru yang diambil.

---

## 1. Ketetapan Awal (Baseline dari Spesifikasi & Threat Model)

### A. Paket Berlangganan & Kuota SaaS
- **Kepemilikan Langganan:** Langganan (`subscriptions`) terikat pada akun Pemilik (`owner_id`), bukan per `venue_id`, sehingga satu pemilik paket *Pro* atau *Enterprise* dapat mengelola beberapa cabang venue dalam satu langganan.
- **Rincian Kuota 3 Paket Utama:**
  - **Starter:** Maksimal 1 venue, 4 lapangan per venue, 3 karyawan gabungan, 1 rekening/QRIS per venue, retensi bukti 30 hari, tanpa ekspor laporan CSV/Excel.
  - **Pro:** Maksimal 2 venue, 12 lapangan per venue, 10 karyawan gabungan, 3 rekening/QRIS per venue, retensi bukti 90 hari, termasuk ekspor laporan CSV/Excel.
  - **Enterprise:** Venue tidak terbatas, lapangan per venue tidak terbatas, 25 karyawan gabungan, rekening/QRIS tidak terbatas, retensi bukti 180 hari, termasuk ekspor laporan CSV/Excel.
- **Snapshot Kuota:** Saat langganan dibuat/diperbarui, nilai kuota disalin (*snapshot*) ke tabel `subscriptions` agar perubahan master `subscription_plans` di masa depan tidak mengganggu hak pelanggan yang sedang berjalan.
- **Perilaku Downgrade:** Jika pemilik turun ke paket dengan kuota lebih kecil dari jumlah aset yang sudah dimiliki, data eksisting tetap dipertahankan, namun pembuatan aset baru (venue/lapangan/karyawan/rekening) diblokir sampai jumlah aktif disesuaikan.
- **Masa Tenggang (*Grace Period*) H+7:** Saat `ends_on` terlewati, selama `H+1` s/d `H+7` venue disembunyikan dari pencarian publik dan menolak booking online baru, tetapi pemilik/staf tetap dapat memvalidasi pembayaran yang sudah masuk, melakukan scan QR check-in, dan melihat laporan. Setelah `H+7`, pembuatan booking OTS dikunci hingga langganan diperpanjang.

### B. Aturan Bisnis & Operasional
- **Durasi & Granularitas Slot:** Booking dibatasi kelipatan 1 jam penuh (`60 menit`). Pembooking dapat memilih satu atau beberapa slot jam berurutan dalam satu transaksi (satu kode booking dan satu QR code).
- **Hari Kerja vs Akhir Pekan:** Akhir pekan (*weekend*) didefinisikan sebagai hari Sabtu dan Minggu. Hari libur nasional belum dimodelkan di Fase 1.
- **Jam Operasional Lewat Tengah Malam:** Disimpan sebagai menit sejak `00:00` (`closes_min` maksimal `2880`), sehingga jadwal buka Sabtu 07.00 hingga Minggu 01.00 dini hari tercatat pada `weekday` hari Sabtu (`420` s/d `1500`).
- **Pembagian Area Lapangan:** Setiap lapangan (`courts`) memiliki jadwal dan harga independen serta tidak saling berbagi area fisik dengan lapangan lain di Fase 1.
- **Laporan Keuangan Lintas Bulan:** Pendapatan harian dan bulanan dihitung berdasarkan tanggal main (`play_date` dalam zona waktu venue), bukan tanggal transfer masuk. Jika pembooking membayar DP pada 28 November untuk bermain pada 2 Desember, nominal tersebut masuk ke laporan bulan Desember.
- **Pembatalan & Pengembalian Dana:** Sistem hanya mencatat status `cancelled` dan melepas slot jika memenuhi batas `cancel_min_hours`. Pengembalian dana (refund) maupun kebijakan DP hangus diselesaikan secara manual di luar sistem antara venue dan pembooking.
- **Otomatisasi Status Pasca-Main:** Scheduler per menit otomatis mengubah status `checked_in` yang melewati `ends_at` menjadi `completed`, dan mengubah status `confirmed` yang melewati `ends_at` tanpa scan QR menjadi `no_show`.

### C. Keamanan, Enkripsi & Retensi Data
- **Kolom Terenkripsi (`encrypted` cast / enkripsi file):**
  - `users.phone`
  - `bookings.customer_phone`
  - `venue_payment_accounts.account_number`
  - `bookings.qr_token_enc`
  - File bukti transfer pada disk privat (`payments.proof_path`)
- **Kolom Sengaja Tidak Dienkripsi (Keputusan Produk):**
  - `users.name`, `users.email`, `bookings.customer_name`, `bookings.customer_email` (diperlukan untuk proses autentikasi Google Sign-In, keunikan akun, pencarian staf di meja resepsionis, dan verifikasi manual saat scan QR).
- **Jeda Perubahan Rekening Tujuan:** Rekening bank atau QRIS baru yang ditambahkan/diubah oleh Pemilik baru tampil ke pembooking setelah jeda 24 jam (`visible_from`), **kecuali** rekening aktif pertama saat venue baru pertama kali diatur (`visible_from = now()`).
- **Retensi Bukti Pembayaran:** File bukti pembayaran dihapus otomatis oleh job harian sesuai `subscriptions.proof_retention_days` (30/90/180 hari) terhitung sejak tanggal main (`play_date`), dengan metadata log tetap dipertahankan.
- **Format Bukti Pembayaran:** Fase 1 hanya menerima gambar JPG dan PNG (maksimal 2 MB). Format PDF belum didukung.
- **Kesiapan Kanal WhatsApp (Fase 2):** Pengiriman notifikasi di Fase 1 wajib memakai kelas `Notification` bawaan Laravel agar penambahan kanal WhatsApp Business API di fase berikutnya dapat dilakukan tanpa mengubah logika bisnis.

---

## 2. Log Asumsi Tambahan Per Fase (Diisi oleh Agent)

### Fase F0 — Fondasi
- **Stack & Toolchain:** Laravel `v13.42.0` (`inertiajs/inertia-laravel` `v3.7.0`, `pestphp/pest` `v4.7.8`, `laravel/pint` `v1.32.3`), Vue `3`, `@inertiajs/vue3`, `@vue/server-renderer`, Tailwind CSS `v4` (`@tailwindcss/vite`), dan `@lhci/cli` dijalankan pada PHP `8.3.7` (Laragon) dan Node.js `v26.13.1`.
- **Database:** `.env` dan `.env.example` dikonfigurasi menggunakan PostgreSQL (`DB_CONNECTION=pgsql`, port `5432`, database `booking_lapang`). Belum ada migrasi tabel bisnis F1 pada fase ini. Khusus di `.env` lokal F0 sebelum migrasi tabel `sessions`/`cache` pada F1, `SESSION_DRIVER=file` dan `CACHE_STORE=file` digunakan agar halaman publik dapat dilayani tanpa error tabel belum tersedia.
- **Tipografi & Visual:** Dipilih **Barlow Condensed** bobot `600` dan `700` (subset latin `.woff2` self-hosted di `public/fonts/`, `font-display: swap`, preload bobot `700` di `resources/views/app.blade.php`) dan system font stack untuk body. Seluruh design token didefinisikan di `@theme` (`resources/css/app.css`) dengan **nol gradient** termasuk pada `.skeleton-pulse` (pulse opacity murni, statis saat `prefers-reduced-motion: reduce`).
- **SSR & Lighthouse CI:** Entry SSR (`resources/js/ssr.js`) diaktifkan di `vite.config.js` (`bootstrap/ssr/ssr.js`). Konfigurasi Lighthouse CI dibagi menjadi `lighthouserc.cjs` (profil mobile) dan `lighthouserc.desktop.cjs` (profil desktop).
- **Security Headers A02:** Middleware `App\Http\Middleware\SecurityHeaders` menambahkan `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` (saat HTTPS/produksi), dan `Content-Security-Policy` (ketika bukan Vite HMR dev server).

### Fase F1 — Data dan Akses
- **Migrasi PostgreSQL:** Seluruh 18 tabel aplikasi + tabel `notifications` dimigrasikan ke PostgreSQL 14.5 (`booking_lapang` & `booking_lapang_test` di `phpunit.xml` karena fitur `btree_gist`, `EXCLUDE USING gist`, `tstzrange`/`int4range`, dan trigger PL/pgSQL tidak tersedia di SQLite).
- **Ekstensi & Trigger:** Ekstensi `btree_gist`, exclusion constraint `bookings_no_overlap` (status terkunci), exclusion constraint `price_rules` & `schedule_blocks`, trigger `prevent_booking_block_overlap()` (`23P01`), dan trigger `forbid_update_delete()` pada `booking_status_logs` & `audit_logs` terpasang di level database.
- **Paket Langganan & Snapshot:** Master paket (`Starter`, `Pro`, `Enterprise`) disalin (*snapshot*) ke tabel `subscriptions` per `owner_id`. Masa tenggang `H+1..H+7` menyembunyikan venue dari publik & menolak booking online baru namun tetap mengizinkan OTS/validasi/check-in, sedangkan `>H+7` mengunci booking OTS juga.
- **Isolasi Multi-Venue & Auth:** `App\Support\VenueContext` (scoped per request) memvalidasi `active_venue_id` di sesi terhadap `venue_users` pada setiap request; `VenueScope` menerapkan fail-closed (0 baris) untuk staf yang belum memilih venue. Staf/pemilik dengan 1 venue melewati modal pilih venue otomatis, sedangkan >1 venue diarahkan ke `/dashboard/venues/select`. Google Sign-In (`laravel/socialite` `v5.32.0`) dikhususkan untuk `customer`.
- **Seeder Lokal/Staging:** `LocalDevelopmentSeeder` hanya berjalan di lingkungan `local`, `staging`, dan `testing` (ketika dipanggil eksplisit oleh tes), mencakup `owner_grace@example.test` (H+3) dan `owner_expired@example.test` (>H+7) selain akun contoh utama.

### Fase F2 — Landing Page
- **Query & Cache Publik:** `App\Actions\Landing\GetPublicVenues` menyaring hanya venue dengan `is_published = true` dan langganan pemilik berstatus aktif (`Subscription::allowsPublicListing()`) pada hari berjalan di zona waktu masing-masing venue, dengan `withoutGlobalScope(VenueScope::class)` pada relasi `courts` dan `photos` agar staf yang sedang login tetap melihat katalog publik secara utuh. Data landing page di-cache 5 menit (`landing:page-data:v1`), sedangkan `/sitemap.xml` dan `/llms.txt` di-cache 10 menit.
- **Placeholder Rute Pencarian `/venues`:** Form pencarian di Hero mengarah ke `/venues?sport={slug}&city={city_slug}` yang dilayani oleh `PublicVenueController@index` (`Pages/Venues/Index.vue`) agar tidak menghasilkan tautan 404 saat diaudit sebelum Fase F3 dikerjakan. Kartu venue unggulan juga diarahkan ke `/venues?city={city_slug}` pada fase ini; URL `/venue/{slug}` dipersiapkan di `sitemap.xml` dan `llms.txt` untuk Fase F3.
- **Aset Visual & Fallback:** Foto sampul kartu venue memakai dimensi eksplisit (`width`, `height`, `loading="lazy"`) di dalam kontainer `aspect-[16/10]`, dengan fallback ilustrasi SVG lapangan inline (nol gradient) jika venue belum memiliki foto upload.
- **Efek, Aksesibilitas & SSR:** Elemen `<h1>` (LCP) dirender langsung tanpa animasi penyembunyi; komponen di bawah fold di-split menggunakan `defineAsyncComponent`; efek stagger, scroll-reveal (`IntersectionObserver`), dan count-up dinonaktifkan otomatis saat `prefers-reduced-motion: reduce`. Google Sign-In script tidak dimuat di halaman landing.
- **SEO & Agentic Browsing:** `<Head>` menyertakan canonical, Open Graph, Twitter Card, dan JSON-LD (`WebSite` dengan `SearchAction`, `Organization`, `BreadcrumbList`). `/robots.txt` memblokir `/dashboard`, `/login`, `/account`, dan `/staff/login`.

### Fase F3 — Pencarian dan Booking
- **Kalkulasi Slot & Harga Multi-Jam:** `App\Support\SlotPricing` dan `App\Actions\Booking\GetAvailableSlots` menghitung slot per 60 menit dari `operating_hours` (termasuk `closes_min > 1440` lewat tengah malam), mengurangi booking berstatus terkunci (`held`, `awaiting_validation`, `awaiting_reupload`, `confirmed`, `checked_in`) serta `schedule_blocks`, dan memecah tarif per jam dari `price_rules` di server (mengabaikan input harga/status dari client). Koneksi `pgsql` di `config/database.php` ditetapkan `'timezone' => env('DB_TIMEZONE', 'UTC')` agar `timestamptz` konsisten UTC.
- **Anti-Hoarding & Idempotency:** `HoldSlot` membatasi maksimal **2 booking `held` aktif per akun** dan maksimal **3 booking `held` aktif per subnet `/24` (IPv4) atau `/64` (IPv6)** untuk akun tanpa riwayat booking `completed` (dilacak via `Cache` + verifikasi status aktif di DB tanpa menambah kolom tabel). `idempotency_key` mencegah duplikasi booking saat klik ganda atau retry jaringan.
- **Keamanan Upload & Enkripsi Bukti:** `SubmitPaymentProof` memvalidasi MIME asli via `finfo` (`image/jpeg`, `image/png`), ukuran maks 2 MB, dimensi maks `4000x4000px`, melakukan re-encode gambar via GD (menghapus metadata EXIF/GPS & payload terselubung), menghitung `proof_sha256` (peringatan duplikat), serta mengenkripsi file (`Crypt::encrypt`) ke disk privat `local` dengan nama ULID. Akses bukti (`PaymentProofController`) dan gambar QRIS venue (`VenueQrisController`) hanya dilayani via controller terotorisasi (`visible_from <= now()` dan header `X-Content-Type-Options: nosniff`).
- **Scheduler Kedaluwarsa:** Command `bookings:expire` (`ExpireStaleBookings`) dijadwalkan tiap menit di `routes/console.php` untuk mengubah `held` kedaluwarsa menjadi `expired` dan `awaiting_reupload` kedaluwarsa menjadi `rejected`, melepas slot, serta mencatat `booking_status_logs` (`actor_kind = 'system'`).
- **Frontend & SSR:** Seluruh tanggal/jam memakai `Intl` dengan zona waktu venue eksplisit (`Asia/Jakarta`, `Asia/Makassar`, `Asia/Jayapura`) di `resources/js/Utils/format.js`, dan countdown `hold_expires_at` pada `Bookings/Show.vue` diinisialisasi di `onMounted` agar bebas hydration mismatch SSR. Google Sign-In hanya dimuat di titik aksi pemesanan pada `Venues/Show.vue`.

### Fase F4 — Validasi dan Check-in
- **Token & Generator QR Code:** `ValidatePayment` dan `CreateOtsBooking` menghasilkan token acak 32 byte CSPRNG url-safe (`random_bytes(32)`), menyimpan hash SHA-256 (`qr_token_hash`) untuk pencarian check-in serta versi terenkripsi (`qr_token_enc` via cast `encrypted`) untuk tampilan ulang. Paket `chillerlan/php-qrcode` (`^6.0`) ditambahkan di Composer untuk merender QR Code SVG (`data:image/svg+xml;base64,...`) secara konsisten di `Bookings/Show.vue` dan email `BookingConfirmedNotification` tanpa `v-html`. Isi QR hanya token mentah tanpa URL maupun PII (Threat T7).
- **Jendela Validitas & Pelunasan di Tempat:** Token QR berlaku mulai `starts_at - 60 menit` hingga `ends_at` (`qr_valid_from` s/d `qr_valid_until`) pada status `confirmed` selama belum digunakan (`qr_used_at IS NULL`) dan belum dicabut (`qr_revoked_at IS NULL`). Jika masih terdapat sisa tagihan DP, `CheckInBooking` mewajibkan staf mencatat pelunasan (`payments.kind = 'settlement'`, metode `cash`/`transfer`/`qris`) sebesar tepat `price_total - paid_total` sebelum status berubah menjadi `checked_in`.
- **Booking OTS & Masa Tenggang:** `CreateOtsBooking` diperbolehkan pada masa tenggang `H+1..H+7`, namun dikunci (403) setelah `> H+7` (`allowsOtsBooking()`). Staf dapat memilih langsung `checked_in` (mengisi `qr_used_at = now()`) untuk pelanggan walk-in yang langsung bermain.
- **Scheduler Pasca-Main:** Command `bookings:transition-ended` (`TransitionEndedBookings`) dijadwalkan setiap menit di `routes/console.php` untuk mengubah `checked_in` yang melewati `ends_at` menjadi `completed`, dan `confirmed` yang melewati `ends_at` tanpa check-in menjadi `no_show` (`actor_kind = 'system'`).

### Fase F5 — Dashboard Pemilik dan Superadmin
- **Upload Foto Venue & Lapangan (`UploadVenuePhoto`):** Foto divalidasi via `finfo` (allowlist JPG/PNG/WebP, maks 5 MB, dimensi maks `4000x4000px`), di-reencode via GD menjadi WebP utama (`<=1600px`) dan varian `640px` serta AVIF (menghapus metadata EXIF/GPS), disimpan di disk `public/venue-photos/{venue_ulid}/` dengan dimensi `width`/`height` eksplisit dan file `.htaccess` penonaktif eksekusi script (`php_flag engine off`, `Options -ExecCGI -Indexes`).
- **Keamanan Rekening / QRIS Tujuan (Threat T10):** `CreatePaymentAccount`, `UpdatePaymentAccount`, dan `TogglePaymentAccountStatus` mewajibkan verifikasi password akun (`current_password`) pada endpoint dashboard, menerapkan jeda `visible_from = now() + 24 jam` untuk perubahan atau penambahan rekening ke-2 dst. (rekening pertama langsung `now()`), mengirim email `PaymentAccountChangedMail` ke seluruh pemilik venue dan superadmin, serta mencatat ke `audit_logs`.
- **Laporan Keuangan & Selisih (Threat T11):** `GetVenueFinancialReport` menghitung pendapatan (`payments.status = 'approved'`) dan jumlah booking per status berdasarkan `play_date` pada `venue_id` aktif saja, mendeteksi selisih (booking `confirmed`/`checked_in`/`completed` tanpa pembayaran `approved`), dan membatasi ekspor CSV (`ExportVenueReport`) hanya jika `can_export_reports = true`.
- **Langganan & Timezone (`Subscription::statusOn`):** Perbandingan tanggal `starts_on` dan `ends_on` dikonversi ke zona waktu venue (`CarbonImmutable::parse($dateString, $timezone)->startOfDay()`) agar konsisten dengan tanggal lokal venue sejak pukul 00.00.
- **Panel Superadmin & Modal Ganti Venue:** Daftar venue pada modal **Pilih Venue Aktif** di `DashboardLayout.vue` berasal dari `HandleInertiaRequests` (query `venue_users` di server lengkap dengan `subscription_status`), dan setiap kunjungan ke `/admin/transactions` dicatat ke `audit_logs`.

### Fase F6 — Notifikasi dan Poles
- *(Agent: catat asumsi terkait jadwal queue atau channel notifikasi di sini)*