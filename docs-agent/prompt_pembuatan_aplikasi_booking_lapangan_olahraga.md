# Prompt Pembuatan Aplikasi Booking Lapangan Olahraga

Untuk dipakai sebagai konteks tetap di Claude Code atau AI coding agent lain. Tempel Bagian 1 sampai 15 sekali di awal, lalu minta pekerjaan per fase (Bagian 16). Jangan minta semuanya sekaligus. Diperbarui 15 November 2026.

## 1. Peran dan tujuan

Kamu adalah senior full-stack engineer. Bangun aplikasi web **booking lapangan olahraga** (futsal, mini soccer, badminton, dll.) berbasis SaaS multi-venue: banyak venue memakai satu sistem, dengan sistem paket berlangganan bulanan (*Starter*, *Pro*, *Enterprise*) per akun pemilik. Pilot pertama: satu venue futsal dan mini soccer. Prioritas: (1) benar (tidak ada double booking, data venue terisolasi), (2) cepat dan mulus, (3) sederhana. Hindari fitur di luar daftar.

## 2. Stack dan aturan teknis

- Laravel (versi stabil terbaru), **PostgreSQL**, **Inertia.js (v2+) dengan Vue 3** (Composition API, `<script setup>`; TypeScript boleh), **Tailwind CSS** versi terbaru, Vite.
- Satu monolit, tanpa API terpisah. Logika bisnis di `app/Actions` (satu class per use case: `HoldSlot`, `SubmitPaymentProof`, `ValidatePayment`, `CheckInBooking`, `CreateOtsBooking`, dst.), controller tipis, validasi di Form Request, otorisasi di Policy, data ke frontend lewat API Resource. Tujuannya agar API untuk aplikasi mobile bisa ditambah nanti tanpa menulis ulang logika.
- Queue (driver database) dan scheduler untuk email, pengingat, kedaluwarsa slot, dan transisi otomatis pasca-main (`completed` dan `no_show`). Gunakan kelas `Notification` bawaan Laravel agar kanal baru (seperti WhatsApp di fase berikutnya) dapat ditambahkan tanpa mengubah logika bisnis.
- File: bukti pembayaran di disk privat (diakses lewat controller dengan otorisasi atau signed URL); foto venue dan lapangan di disk publik dalam beberapa ukuran.
- Test dengan Pest, style dengan Pint. Tambah dependensi baru hanya jika benar-benar perlu dan sebutkan alasannya.
- Kode, nama tabel, dan komentar dalam bahasa Inggris; teks antarmuka dalam bahasa Indonesia (`lang="id"`).
- Waktu disimpan UTC (`timestamptz`), ditampilkan dalam zona waktu venue (default Asia/Jakarta).

## 3. Peran pengguna (Fase 1)

Sistem memiliki tiga peran global di tabel `users` (`customer`, `staff`, `superadmin`) dan dua peran keanggotaan venue di tabel `venue_users` (`owner`, `staff`). Dengan begitu terdapat empat peran operasional: **Pembooking** (Google Sign-In via Socialite), **Karyawan Venue**, **Pemilik Venue**, dan **Superadmin** (staf dan superadmin memakai email dan password). Admin Sistem digabung ke Superadmin di Fase 1; rancang role agar mudah ditambah.

- **Karyawan (`venue_users.role = 'staff'`):** validasi pembayaran (setujui, minta bukti baru, atau tolak dengan alasan), buat booking OTS (dengan opsi langsung check-in), atur jadwal dan blokir slot, scan QR check-in, catat pelunasan.
- **Pemilik (`venue_users.role = 'owner'`):** semua hak karyawan, ditambah profil venue, kelola lapangan dan kategori, harga, jam operasional, kebijakan DP dan pembatalan, rekening atau QRIS tujuan, kelola akun karyawan, laporan harian dan bulanan.
- **Superadmin (`users.role = 'superadmin'`):** kelola master paket berlangganan (`subscription_plans`), kelola akun pemilik dan karyawan, buat venue baru dan menautkannya ke pemilik, aktif/nonaktifkan langganan pemilik beserta tanggal berakhirnya, lihat transaksi semua venue, daftar langganan aktif, masa tenggang, dan tidak aktif.
- **Pembooking (`users.role = 'customer'`):** cari venue, booking (satu atau beberapa slot jam berurutan), riwayat, batalkan sesuai kebijakan.
- **Isolasi data:** semua tabel milik venue memiliki `venue_id`; terapkan global scope dan Policy. Staf venue A tidak boleh membaca atau mengubah data venue B (wajib ada tes).

**Satu pemilik, banyak venue & Paket Berlangganan**

Satu akun pemilik (`owner_id`) bisa memiliki beberapa venue sesuai kuota paket langganannya, dan staf bisa terdaftar di beberapa venue. Keanggotaan akses ditentukan oleh tabel `venue_users`, sedangkan kepemilikan utama venue dicatat di `venues.owner_id`.

- **Paket berlangganan per akun pemilik (`subscriptions.owner_id`):** terdapat 3 paket standar (`Starter`, `Pro`, `Enterprise`) yang membatasi:
  - Maksimal venue milik `owner_id` (`Starter`: 1, `Pro`: 2, `Enterprise`: tidak terbatas).
  - Maksimal lapangan aktif per venue (`Starter`: 4, `Pro`: 12, `Enterprise`: tidak terbatas).
  - Maksimal akun karyawan gabungan di seluruh venue milik `owner_id` (`Starter`: 3, `Pro`: 10, `Enterprise`: 25).
  - Maksimal rekening/QRIS tujuan aktif per venue (`Starter`: 1, `Pro`: 3, `Enterprise`: tidak terbatas).
  - Masa retensi bukti transfer (`Starter`: 30 hari, `Pro`: 90 hari, `Enterprise`: 180 hari).
- **Penegakan kuota di Action & Policy:** saat menambah venue, lapangan, karyawan, atau rekening tujuan, sistem mengecek kuota aktif pada langganan `owner_id`. Jika penuh, aksi ditolak dengan pesan ramah yang menyarankan *upgrade* paket. Saat *downgrade* paket, data eksisting tidak dihapus otomatis, tetapi penambahan baru diblokir sampai jumlah aktif disesuaikan.
- **Masa tenggang (*grace period*) H+7:** jika tanggal berakhir langganan (`ends_on`) terlewati:
  - **Hari H+1 s/d H+7 (Masa Tenggang):** venue otomatis disembunyikan dari pencarian publik dan tidak menerima booking online baru, namun pemilik/staf tetap dapat login penuh untuk memvalidasi sisa pembayaran, melakukan scan QR check-in untuk booking yang sudah `confirmed`, dan melihat laporan.
  - **Setelah H+7 (Nonaktif):** akses pembuatan booking OTS dikunci dan dashboard menampilkan peringatan perpanjangan paket (data laporan dan check-in booking lama tetap dapat dibaca).
- **Pilih venue lewat modal:** setelah login, pemilik atau staf yang terdaftar di lebih dari satu venue melihat modal **Pilih venue** berisi **hanya venue milik pengguna yang sedang login**. Daftarnya diambil di server dari `venue_users`, bukan dari input client, dan tidak memuat nama atau data venue lain. Jika hanya terdaftar di satu venue, venue itu dipilih otomatis tanpa modal. Jika belum terdaftar di venue mana pun, tampilkan keadaan kosong yang mengarahkan menghubungi Superadmin.
- **Venue aktif:** pilihan disimpan di sesi dan **divalidasi di server pada setiap request** lewat `venue_users`; ID venue dari client tidak pernah dipercaya. Header menampilkan nama venue aktif dengan tombol ganti venue yang membuka modal yang sama.
- **Isolasi dan laporan:** global scope memakai venue aktif. Semua data dan **laporan dibuat per venue** (venue yang sedang dipilih). Tidak ada laporan gabungan di Fase 1.
- **Notifikasi** (in-app, email) selalu menyebut nama venue.
- **Pembuatan venue baru** di Fase 1 dilakukan Superadmin (dengan memeriksa kuota paket pemilik), yang juga menautkannya ke pemilik. Pendaftaran venue mandiri bukan lingkup Fase 1.
- Pemilik terakhir sebuah venue (`venue_users.role = 'owner'`) tidak boleh dihapus atau diturunkan perannya.
- **Tes:** pemilik venue A dan B tidak bisa mengakses data venue C; memilih atau mengirim venue yang bukan miliknya ditolak; setelah ganti venue, data dan laporan hanya berisi venue terpilih; pembuatan venue/lapangan/karyawan/rekening melebihi kuota paket ditolak; status masa tenggang H+1..H+7 dan nonaktif >H+7 bekerja sesuai aturan.

## 4. Alur utama

1. Pengunjung (tanpa login) mencari venue per kategori dan kota, membuka halaman venue (lapangan, jumlah, foto, harga), memilih lapangan dan tanggal, lalu melihat slot kosong. Jika penuh, tawarkan lapangan atau tanggal lain.
2. Pengunjung memilih satu atau beberapa slot jam berurutan (multi-jam digabung menjadi satu booking). Setelah memilih slot, pengunjung login Google (login hanya diminta di titik ini), mengisi nama, nomor telepon (wajib), dan memilih DP atau lunas.
3. Sistem membuat booking berstatus `held` dan menahan slot selama N menit (default 20, bisa diatur per venue). Sistem menampilkan rincian nominal transfer serta pilihan rekening bank atau QRIS aktif milik venue. Pembooking membayar manual ke rekening atau QRIS venue, lalu mengunggah bukti (atau membatalkan booking jika berubah pikiran).
4. Status menjadi `awaiting_validation`; karyawan dan pemilik mendapat notifikasi. Karyawan menyetujui (`confirmed`) atau meminta bukti baru dengan alasan (`awaiting_reupload`: slot tetap ditahan selama batas unggah ulang, default 60 menit; bila bukti baru tidak masuk, status otomatis menjadi `rejected` dan slot dilepas). Karyawan juga bisa menolak langsung (`rejected`) untuk kasus seperti bukti palsu.
5. Saat `confirmed`: sistem membuat token QR acak, mengirim email berisi QR dan detail transaksi, dan menjadwalkan email pengingat H-1 dan 5 jam sebelum main.
6. Di venue, karyawan scan QR di halaman khusus. Tampil detail transaksi (nama, nomor telepon, lapangan, jam), sisa pembayaran (jika DP) atau status lunas. Karyawan mencatat pelunasan, menandai `checked_in`, dan setelah main `completed` (otomatis oleh scheduler jika melewati `ends_at`). Jika pelanggan tidak hadir sampai `ends_at` berlalu, status otomatis menjadi `no_show`. Token hanya berlaku sekali dan hanya untuk venue pemiliknya.
7. Booking OTS dibuat karyawan: langsung `confirmed` (atau langsung `checked_in` jika pelanggan langsung bermain saat itu), mengunci slot, dan mencatat pembayaran tunai atau transfer.

Status lengkap: `held`, `awaiting_validation`, `awaiting_reupload`, `confirmed`, `checked_in`, `completed`, `rejected`, `expired`, `cancelled`, `no_show`.

## 5. Model data dan aturan bisnis (PostgreSQL)

Tabel inti: `users`, `subscription_plans`, `subscriptions`, `venues`, `venue_users`, `courts`, `court_categories`, `price_rules` (per lapangan: weekday/weekend, rentang jam, harga per jam), `operating_hours`, `schedule_blocks`, `bookings`, `payments` (jumlah, tipe DP/pelunasan/penuh, metode, bukti, status), `booking_status_logs`, `audit_logs`, `venue_payment_accounts`, `booking_reminders`, serta tabel notifikasi bawaan Laravel.

- **Anti double booking di level database:** aktifkan ekstensi `btree_gist`, lalu pada `bookings` buat exclusion constraint: `EXCLUDE USING gist (court_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status IN ('held','awaiting_validation','awaiting_reupload','confirmed','checked_in'))`. Buat booking maupun blokir jadwal di dalam transaksi dengan mengunci baris `courts` (`lockForUpdate()`) dan ubah pelanggaran constraint (`23P01`) menjadi pesan ramah (slot baru saja diambil orang lain atau diblokir). Tulis tes konkurensi.
- Blokir jadwal (`schedule_blocks`) juga harus mencegah booking lewat trigger database dan pengecekan Action.
- Job terjadwal tiap menit:
  - Mengubah `held` yang melewati `hold_expires_at` menjadi `expired` dan melepas slot.
  - Mengubah `awaiting_reupload` yang melewati `hold_expires_at` menjadi `rejected` dan melepas slot.
  - Mengubah `checked_in` yang melewati `ends_at` menjadi `completed`.
  - Mengubah `confirmed` yang melewati `ends_at` tanpa check-in menjadi `no_show`.
- Token QR: 32 byte acak (url-safe), unik, bisa dicabut. Jangan memakai ID berurutan.
- Index untuk query jadwal: `(court_id, starts_at)` dan `(venue_id, status, starts_at)`; index pencarian venue pada kota, kategori, dan status aktif.
- Venue dengan langganan habis (`ends_on < today`) tidak muncul di pencarian dan tidak menerima booking online baru; booking yang sudah `confirmed` tetap berjalan.
- Laporan harian dan bulanan dihitung berdasarkan tanggal main (`play_date`); tampilkan pendapatan yang diterima (`payments` berstatus `approved`: DP + pelunasan + lunas) dan jumlah booking per status. Fitur ekspor CSV/Excel laporan tersedia untuk paket *Pro* dan *Enterprise*.
- Pembatalan: kebijakan sederhana per venue (`cancel_min_hours` sebelum main; DP hangus atau dikembalikan secara manual di luar sistem, sistem hanya mencatat).
- Seeder: ikuti bagian **Data contoh untuk seeder** di `docs-agent/skema_database_f1_aplikasi_booking_lapangan_postgresql.md` (3 paket langganan, 2 venue, lapangan beragam, harga berbeda tiap lapangan, akun per peran, dan satu booking per status). Seeder hanya berjalan di lingkungan lokal dan staging.

## 6. Desain visual

Tema: **sporty yang elegan**. Bersih, kontras tegas, tipografi kuat, banyak ruang kosong.

**Larangan:** tidak ada gradient di mana pun (termasuk shimmer skeleton, overlay foto, tombol, dan latar), tidak ada ornamen dekoratif berlebihan, tidak ada ilustrasi berat, bayangan hanya yang sangat tipis.

Design tokens (definisikan di Tailwind `@theme`, jangan hardcode warna di komponen):

- Latar `#F5F4EF`, permukaan `#FFFFFF`, teks `#0E1B16`, teks sekunder `#53625B`, garis `#DAD8CE`
- Primary `#0F3B2E` (hover `#0B2D23`), accent `#D4F34A` (hanya penekanan; teks di atasnya memakai warna teks utama)
- Success `#1F7A4D`, warning `#8A5A0B`, danger `#B3261E`
- Verifikasi semua pasangan teks dan latar: minimal 4.5:1 (teks besar 3:1) dengan alat kontras. Ubah nilainya jika gagal.

Tipografi: heading memakai satu font condensed bergaya sporty (misalnya Barlow Condensed 600/700), di-self-host sebagai woff2 subset latin, `font-display: swap`, preload satu file. Body memakai system font stack. Jangan tambah font lain.

Bentuk: radius 10 sampai 12px, garis tegas, ikon SVG inline (tanpa icon font), foto sebagai visual utama. Status booking ditampilkan dengan teks dan ikon, bukan warna saja. Mobile-first, target sentuh minimal 44px.

## 7. Landing page dan efek

Bagian: hero (judul kuat, pencarian kategori dan kota, satu CTA), kategori olahraga, cara kerja (3 langkah), venue unggulan, untuk pemilik venue (ringkasan paket *Starter*, *Pro*, *Enterprise* & CTA hubungi), FAQ (`<details>` native), footer. Teks contoh dalam bahasa Indonesia.

Efek (halus, hanya `transform` dan `opacity`, tanpa library animasi):

- Animasi masuk hero yang bertahap (stagger), **tetapi elemen LCP (judul atau gambar hero) tidak boleh disembunyikan sebelum animasi**. Tampilkan langsung, animasikan elemen pendukungnya.
- Scroll-reveal dengan `IntersectionObserver` untuk section di bawah fold.
- Count-up angka statistik saat terlihat; hover kartu naik 2 sampai 4px dan zoom foto 1.03; header menyusut saat scroll; carousel venue dengan CSS scroll-snap; smooth scroll untuk anchor.
- Semua efek dimatikan atau diganti transisi instan saat `prefers-reduced-motion: reduce`.
- Tanpa video autoplay, tanpa parallax berat, tanpa efek yang menyebabkan layout shift.

## 8. Skeleton dan rasa mulus

- Buat komponen skeleton: gambar, baris teks, kartu venue, grid slot jam, baris tabel. Dimensinya **sama dengan konten asli** (aspect-ratio atau tinggi tetap) agar CLS mendekati 0.
- Animasi skeleton memakai **pulse opacity**, bukan shimmer bergradient; statis saat reduced motion.
- Pakai Inertia deferred props (`Deferred`), `WhenVisible`, dan partial reload: halaman tampil segera, bagian berat (venue unggulan, slot jadwal, laporan) memuat dengan skeleton. Slot jadwal dimuat per tanggal.
- Gambar: placeholder berukuran tetap, lalu fade-in saat dimuat.
- Navigasi: prefetch Inertia saat hover atau focus untuk link utama, progress bar bertema primary, transisi halaman fade pendek (maksimal 150ms), tombol submit punya state loading dan tidak bisa diklik ganda. Optimistic UI hanya jika aman.

## 9. Performa (target Lighthouse 100, mobile dan desktop)

Target 100 untuk Performance, Accessibility, Best Practices, dan SEO pada halaman publik (landing, pencarian, detail venue, booking, login), diuji untuk profil mobile (throttled) dan desktop.

- **SSR Inertia** untuk halaman publik; dashboard tidak perlu SSR.
- Bundel kecil: code-splitting per halaman, `defineAsyncComponent` untuk komponen di bawah fold, tanpa library besar (tanpa moment atau lodash penuh; pakai `Intl` dan utilitas kecil).
- Script pihak ketiga (Google Sign-In) dimuat **hanya saat dibutuhkan** (di halaman atau aksi login), tidak di landing.
- Gambar: buat AVIF/WebP dan beberapa ukuran saat upload, `srcset` dan `sizes`, atribut `width` dan `height`, `loading="lazy"` kecuali gambar LCP (`fetchpriority="high"`, tanpa lazy).
- Font sesuai Bagian 6. CSS: purge Tailwind, hindari CSS yang memblokir render di luar yang kritis.
- Cache dan kompresi: header cache panjang `immutable` untuk aset ber-hash, Brotli atau gzip, HTTP/2 atau 3. Laravel produksi: `config:cache`, `route:cache`, `view:cache`, `event:cache`, OPcache.
- Database: eager loading (aktifkan `Model::preventLazyLoading()` di non-produksi), index sesuai Bagian 5, `Cache::remember` untuk venue unggulan dan kategori.
- Pasang **Lighthouse CI** untuk mobile dan desktop pada URL publik. Gate CI: Accessibility, Best Practices, SEO = 1.0; Performance minimal 0.95 (target 1.0, toleransi variasi pengukuran). Laporkan hasilnya di akhir tiap fase.
- Dashboard (di balik login) tidak diuji Lighthouse otomatis, tetapi tetap mengikuti aturan ringan, aksesibel, dan memakai skeleton.

## 10. SEO (halaman publik)

- `<Head>` Inertia per halaman: title unik, meta description, canonical, Open Graph, Twitter Card, `lang="id"`.
- URL bersih (`/venue/{slug}`, `/{kategori}/{kota}`), heading hierarkis dengan satu `h1` per halaman, tautan internal, status 404 yang benar.
- JSON-LD: `WebSite` dan `Organization` (landing), `SportsActivityLocation` atau `LocalBusiness` untuk venue (alamat, jam buka, foto), `BreadcrumbList`.
- `sitemap.xml` dinamis (venue aktif) dan `robots.txt`; dashboard, login, dan halaman akun memakai `noindex`.
- Konten penting harus ada di HTML hasil SSR, bukan baru muncul setelah JavaScript berjalan.

## 11. Aksesibilitas (target WCAG 2.2 AA)

- HTML semantik dan landmark (`header`, `nav`, `main`, `footer`), skip link, urutan heading benar.
- Semua input berlabel; error form terhubung lewat `aria-describedby`; toast memakai `aria-live`.
- Semua interaksi bisa lewat keyboard dengan fokus yang jelas terlihat. Pemilih slot memakai `<button>` dengan status (`aria-pressed` atau `aria-disabled`), bukan `div`.
- Dialog dan menu: focus trap dan kembalikan fokus; alt text bermakna (gambar dekoratif: `alt=""`).
- Kontras sesuai Bagian 6; informasi tidak disampaikan lewat warna saja; fungsi tetap utuh pada zoom 200%; `prefers-reduced-motion` dihormati.

## 12. UX data: format uang, angka, tanggal, dan validasi form

Semua uang, angka, dan tanggal ditampilkan lewat satu modul format berbasis `Intl` dengan locale `id-ID`, dan semua form memakai pola validasi yang sama. Jangan memformat manual di tiap komponen.

**Format tampilan**

- **Uang:** Rupiah tanpa desimal dengan titik sebagai pemisah ribuan, misalnya `Rp150.000`, harga per jam `Rp150.000/jam`, rentang `Rp100.000 – Rp200.000`. Di database simpan sebagai integer rupiah (bigint), bukan float. Semua perhitungan (DP, sisa bayar, laporan) dilakukan di server.
- **Input uang:** tampil terformat saat diketik, tetapi nilai yang dikirim berupa angka murni. Pakai `inputmode="numeric"` dan tolak desimal serta nilai negatif.
- **Angka:** `1.250` untuk ribuan, `30%` untuk persentase. Bentuk singkat (misalnya `1,2 rb`) hanya di kartu statistik, dengan nilai lengkap di `aria-label`.
- **Tanggal dan jam:** `Sabtu, 14 Nov 2026` (panjang: `14 November 2026`), jam 24 jam dengan zona venue, misalnya `19.00 WIB`, rentang `19.00–20.00`. Tanggal relatif (Hari ini, Besok) hanya untuk label, bukan untuk data transaksi.
- **Zona waktu:** server mengirim ISO 8601 UTC; format memakai zona venue secara eksplisit, bukan zona perangkat. Dengan begitu hasil SSR dan client sama dan tidak terjadi hydration mismatch.
- **Nomor telepon:** simpan ternormalisasi (62...) terenkripsi, tampilkan seperti `0812-3456-7890`.
- Modul format (`formatRupiah`, `formatNumber`, `formatDate`, `formatTime`, `formatPhone`) wajib punya tes unit, termasuk kasus nol, angka besar, dan pergantian hari di zona WIT.

**Validasi form**

- **Dua lapis:** Form Request di server adalah sumber kebenaran; validasi ringan di client (wajib diisi, format, panjang) hanya untuk umpan balik cepat. Jangan mengandalkan client saja.
- **Pesan error** dalam bahasa Indonesia, spesifik, dan memberi tindakan, misalnya `Nama wajib diisi` atau `Bukti bayar maksimal 2 MB (JPG atau PNG)`. Sediakan `lang/id/validation.php` dan nama atribut per field.
- **Penampilan:** pesan di bawah field terkait dengan ikon dan teks (bukan warna saja), `aria-invalid` dan `aria-describedby`. Setelah submit gagal, fokus pindah ke field error pertama. Untuk form panjang, tambahkan ringkasan error di atas form dengan tautan ke tiap field.
- **Waktu muncul:** error pertama muncul saat field kehilangan fokus (blur), lalu diperbarui saat mengetik dan hilang begitu benar. Jangan tampilkan error saat pengguna baru mulai mengetik.
- **Data tidak hilang** setelah error (gunakan `useForm` Inertia). Tombol submit menampilkan loading dan nonaktif selama `processing`. Pembuatan booking memakai idempotency key agar klik ganda atau koneksi ulang tidak membuat booking ganda.
- **Aturan khusus:** nama wajib (maksimal 100 karakter); nomor telepon wajib dan valid; nominal integer lebih dari 0; bukti bayar hanya JPG atau PNG maksimal 2 MB (PDF belum diizinkan di Fase 1); tanggal tidak boleh di masa lalu; jam harus dalam jam operasional dan tidak bentrok dengan blokir jadwal.

**Error di luar field**

- **Slot bentrok:** tampilkan `Slot baru saja diambil, pilih jam lain`, tandai slot itu tidak tersedia, dan muat ulang jadwal tanpa menghapus input lain.
- **419 (sesi habis):** pesan jelas dan input tetap tersimpan. **403 dan 404:** halaman dengan jalan keluar yang jelas. **429:** tampilkan lama waktu tunggu. **500:** halaman error ramah dengan tombol coba lagi. **Offline:** tampilkan pesan koneksi.
- Toast sukses singkat memakai `aria-live="polite"`. Error penting tidak boleh hanya berupa toast.

**Tes:** tiap form utama punya tes feature untuk aturan validasinya, dan minimal satu tes browser (Playwright atau Dusk, opsional) untuk alur error, perbaikan, lalu sukses.

## 13. Agentic browsing (Lighthouse 13+)

Kategori ini tidak diberi skor 0 sampai 100, melainkan rasio audit yang lolos. Target: **semua audit lolos**.

- Pohon aksesibilitas sehat: semua elemen interaktif punya nama, role valid, dan tidak ada elemen interaktif yang tersembunyi dari a11y tree. Ini sejalan dengan Bagian 11.
- CLS mendekati 0: gambar berdimensi, skeleton berukuran tetap, tidak ada konten yang disisipkan di atas konten lain.
- Sediakan `/llms.txt` (ringkasan situs, halaman penting, cara kerja booking; dibuat otomatis dari data venue aktif atau berupa file statis).
- Opsional, jika sederhana: tool WebMCP deklaratif untuk pencarian venue dan formulir booking. Bobotnya 0 di Lighthouse, jadi kerjakan paling akhir.
- Pastikan Lighthouse yang dipakai di CI versi 13 atau lebih baru.

## 14. Keamanan: OWASP Top 10:2025 dan upload file

Aplikasi ini menyimpan data pribadi dan bukti transfer, jadi keamanan adalah syarat rilis. Terapkan kontrol berikut untuk tiap kategori OWASP Top 10:2025 (edisi final Januari 2026). Ini batas minimum, bukan jaminan bebas celah. Tulis tes untuk setiap kontrol yang bisa diuji dan catat hasilnya di `docs-agent/checklist_keamanan_owasp_top_10_2025_upload_file.md`.

**Per kategori OWASP Top 10:2025**

- **A01 Broken Access Control (termasuk SSRF):** Policy untuk setiap model dan aksi, default tolak. Global scope `venue_id`. Cek kepemilikan pada booking, bukti bayar, QR, dan batas kuota paket berlangganan (`subscription_plans`). Tanpa ID berurutan di URL untuk data sensitif (pakai slug atau ULID). `$fillable` eksplisit, jangan `$guarded = []`. Aplikasi tidak mengambil URL dari input pengguna; bila terpaksa, pakai allowlist host dan blokir IP privat. Tes: staf venue A mengakses data venue B, pembooking membuka booking orang lain, dan pemilik melewati batas kuota paket harus ditolak.
- **A02 Security Misconfiguration:** `APP_DEBUG=false` di produksi, hanya folder `public/` yang terbuka, `.env` tidak masuk repo. Header: HSTS, CSP, `X-Content-Type-Options: nosniff`, Referrer-Policy, Permissions-Policy, `frame-ancestors`. CORS dibatasi. Cookie sesi `Secure`, `HttpOnly`, `SameSite`. Debugbar atau Telescope dimatikan atau dikunci di produksi. Seeder demo dan akun contoh tidak berjalan di produksi.
- **A03 Software Supply Chain Failures:** `composer.lock` dan `package-lock.json` di-commit. `composer audit` dan `npm audit` berjalan di CI. Pasang Dependabot atau Renovate. Dependensi seminimal mungkin dan paket baru direview. Aset dari CDN luar wajib memakai SRI. Build dan deploy hanya dari branch terproteksi.
- **A04 Cryptographic Failures:** HTTPS wajib dengan redirect dan HSTS. Password lewat `Hash` Laravel (bcrypt atau Argon2id). Token QR dan token lain dibuat dari CSPRNG (minimal 32 byte). `APP_KEY` dijaga dan tidak masuk repo. Backup terenkripsi. Jangan simpan data kartu pembayaran. Data pribadi dienkripsi sesuai sub-bagian Enkripsi data pribadi di bawah.
- **A05 Injection:** Eloquent atau query builder dengan binding; kolom untuk `orderBy` atau `whereRaw` dari input harus lewat allowlist. Blade memakai `{{ }}`, bukan `{!! !!}`. Dilarang `v-html` untuk data pengguna. Tidak ada `shell_exec` dengan input pengguna. Email lewat Mailable atau Notification. Tes dengan input seperti `' OR 1=1` dan `<script>` pada field teks.
- **A06 Insecure Design:** buat threat model di `docs-agent/threat_model_alur_booking_pembayaran_manual_dan_qr_check_in.md`. Harga, DP, kuota paket, dan status selalu dihitung di server, tidak dipercaya dari client. Batasi penyalahgunaan: maksimal booking `held` aktif per pengguna maupun per IP/subnet untuk akun tanpa riwayat `completed`, maksimal unggah ulang bukti, rate limit pembuatan booking, QR sekali pakai.
- **A07 Authentication Failures:** Google Sign-In diverifikasi di server (email terverifikasi). Password staf minimal 12 karakter dengan `Password::min(12)->uncompromised()`. Throttle dan lockout login, regenerasi sesi setelah login, invalidasi sesi saat logout, token reset password berumur pendek, pesan login yang generik (tidak membocorkan apakah email terdaftar). Siapkan 2FA untuk pemilik dan superadmin. Tidak ada akun default.
- **A08 Software or Data Integrity Failures:** tidak ada deserialisasi data tidak tepercaya. Payload queue tidak boleh berisi kode atau class dari input pengguna. Webhook pembayaran (jika nanti ada) wajib diverifikasi signature-nya. Pipeline deploy tidak bisa diubah dari branch yang tidak terproteksi.
- **A09 Security Logging and Alerting Failures:** catat login berhasil dan gagal, perubahan peran, validasi pembayaran (siapa, kapan, hasil), check-in, perubahan paket/langganan, akses ditolak (403), dan unggahan yang ditolak. Audit log bersifat append-only. Jangan pernah mencatat password, token, isi QR, atau isi bukti. Beri alert (email atau Telegram) untuk lonjakan login gagal, error 5xx, dan antrean job gagal.
- **A10 Mishandling of Exceptional Conditions:** handler exception global menampilkan pesan umum ke pengguna dan menyimpan detail di log, tanpa stack trace ke client. Prinsip fail closed: bila pengecekan otorisasi gagal atau error, aksi ditolak. Transaksi database selalu punya rollback. Pelanggaran constraint (double booking) ditangani eksplisit. Input tidak valid menghasilkan 422, bukan 500. Job gagal masuk `failed_jobs` dan dipantau. Ada batas ukuran request dan timeout.

**Upload file (bukti pembayaran dan foto venue)**

- **Allowlist ketat:** bukti bayar hanya JPG dan PNG (PDF belum diizinkan di Fase 1); foto venue JPG, PNG, dan WebP. SVG ditolak.
- **Validasi dari isi file,** bukan dari ekstensi atau `Content-Type` yang dikirim client (cek MIME lewat `finfo`, validasi `image` dan `mimes` Laravel, dan pastikan file benar-benar bisa dibuka sebagai gambar).
- **Batas ukuran:** bukti maksimal 2 MB, foto maksimal 5 MB, dimensi gambar maksimal sekitar 4000 piksel (mencegah decompression bomb). Selaraskan dengan batas web server dan PHP (`client_max_body_size`, `upload_max_filesize`, `post_max_size`).
- **Proses ulang gambar** (re-encode ke WebP atau JPEG) dan hapus metadata EXIF, termasuk koordinat GPS. Ini juga membuang payload yang disisipkan di dalam gambar.
- **Nama file dibuat server** (ULID atau UUID). Nama asli tidak dipakai sebagai path; bila disimpan, hanya sebagai metadata yang di-escape. Cegah path traversal.
- **Penyimpanan:** bukti di disk privat di luar web root. Foto venue di disk terpisah, dan folder upload tidak boleh mengeksekusi script (nonaktifkan PHP di folder itu).
- **Akses bukti** hanya lewat controller dengan Policy atau signed URL berumur pendek, dengan header `Content-Disposition` dan `X-Content-Type-Options: nosniff`. Bukti tidak pernah dilayani langsung sebagai file publik.
- **Rate limit** unggahan per pengguna dan batas jumlah file per booking. Bersihkan file yatim saat booking kedaluwarsa, sesuai kebijakan retensi per paket langganan (`proof_retention_days`).
- **Tes wajib:** file `.php` yang dinamai `.jpg`, MIME palsu, file melebihi batas, SVG, gambar ber-EXIF GPS (harus bersih setelah diproses), akses bukti oleh staf venue lain, dan nama file berisi `../`.

**Enkripsi data pribadi**

- **Dalam perjalanan:** HTTPS di semua halaman; koneksi ke database dan layanan lain memakai TLS.
- **Di level aplikasi:** enkripsi kolom data pribadi sensitif selain nama dan email (`users.phone`, `bookings.customer_phone`, `venue_payment_accounts.account_number`, dan catatan sensitif) dengan fitur `encrypted` cast Eloquent. Nama dan email sengaja tidak dienkripsi (keputusan produk). Jangan mengenkripsi kolom yang dipakai untuk constraint, relasi, filter, dan pengurutan.
- **Bukti bayar:** file dienkripsi sebelum ditulis ke disk privat dan didekripsi hanya saat diakses lewat controller dengan Policy.
- **Password** di-hash, bukan dienkripsi. **Token QR** disimpan sebagai hash bila tidak perlu ditampilkan ulang; jika riwayat booking perlu menampilkannya lagi, simpan terenkripsi.
- **Kunci:** `APP_KEY` hanya ada di environment atau secret manager, tidak di repo, dan tidak disimpan bersama backup datanya. Siapkan prosedur rotasi kunci.
- **Log, email, dan pesan error** tidak boleh memuat data pribadi dalam bentuk terbuka; samarkan jika perlu (misalnya `a***@gmail.com`, `0812-****-7890`).

## 15. Kualitas dan tes

- Tes wajib (Pest): double booking konkuren; hold kedaluwarsa; transisi status sah dan tidak sah; isolasi venue; penegakan kuota paket berlangganan (*Starter*, *Pro*, *Enterprise*); masa tenggang langganan H+7; QR sekali pakai dan milik venue yang benar; laporan menghitung dengan benar.
- Tidak ada `console.error` di browser, tidak ada mixed content, tidak ada API deprecated.

## 16. Fase pengerjaan

Kerjakan berurutan. Setiap fase diakhiri dengan: tes lulus, Pint bersih, checklist keamanan Bagian 14 terpenuhi untuk fitur fase itu, ringkasan perubahan, hasil Lighthouse (untuk fase yang menyentuh halaman publik), dan asumsi tercatat di `docs-agent/template_asumsi_sistem_keputusan_produk.md`.

- **F0 Fondasi:** proyek Laravel + PostgreSQL + Inertia Vue SSR + Tailwind (design tokens), layout publik dan dashboard, komponen dasar (Button, Input, Card, Skeleton, Toast), Lighthouse CI.
- **F1 Data dan akses:** migrasi (termasuk `subscription_plans` dan `subscriptions` berbasis `owner_id`), constraint PostgreSQL, role dan Policy, login Google (pembooking) dan login staf, isolasi venue dan keanggotaan banyak venue (pemilik dan staf), seeder, beserta tesnya.
- **F2 Landing page:** semua section (termasuk ringkasan paket *Starter*, *Pro*, *Enterprise*), efek, SEO, JSON-LD, sitemap, robots, `llms.txt`; capai target Lighthouse.
- **F3 Pencarian dan booking:** daftar venue, detail venue, slot kosong (termasuk pemilihan multi-jam), hold slot, tampilan rekening/QRIS tujuan, form booking, unggah bukti, pembatalan oleh pembooking, riwayat booking.
- **F4 Validasi dan check-in:** antrean validasi karyawan, QR dan email, halaman scan, pelunasan, booking OTS (dengan opsi langsung check-in), jadwal dan blokir slot.
- **F5 Dashboard pemilik dan superadmin:** pengaturan venue, lapangan, harga, kebijakan, rekening/QRIS (dengan batas kuota paket); akun karyawan (dengan batas kuota paket); modal pilih venue untuk pemilik dan staf dengan banyak venue; laporan harian dan bulanan per venue (plus ekspor CSV/Excel untuk *Pro*/*Enterprise*); manajemen paket & langganan pemilik oleh Superadmin beserta masa tenggang H+7.
- **F6 Notifikasi dan poles:** email pengingat H-1 dan 5 jam, notifikasi staf (email dan in-app via Laravel Notification; siap ekstensi WhatsApp di Fase 2), audit aksesibilitas, optimasi akhir.

Di luar lingkup Fase 1: payment gateway, CMS landing page, aplikasi mobile, ulasan dan rating, WhatsApp gateway, reschedule mandiri, laporan tahunan, peran Admin Sistem terpisah.

## 17. Cara kerja kamu

- Jika ada keputusan bisnis yang ambigu, tanyakan; jangan mengarang. Asumsi kecil dicatat di `docs-agent/template_asumsi_sistem_keputusan_produk.md`.
- Mulai tiap fase dengan rencana singkat (file yang akan dibuat atau diubah), lalu implementasi. Jangan mengerjakan fase berikutnya sebelum diminta.
- Jangan menambah fitur atau dependensi di luar dokumen ini. Utamakan kode sederhana yang mudah dibaca dan dites.
- Commit kecil per unit kerja dengan pesan yang jelas.