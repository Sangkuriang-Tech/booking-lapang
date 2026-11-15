# Laporan & Checklist Keamanan (`SECURITY.md`)

Dokumen ini merupakan gerbang verifikasi keamanan berdasarkan **OWASP Top 10:2025** dan **Threat Model (`threat_model_alur_booking_pembayaran_manual_dan_qr_check_in.md`)**. **AI Coding Agent wajib memperbarui status checklist dan bukti tes Pest di setiap akhir fase.**

Legenda Status: `[ ]` Belum dikerjakan · `[~]` Sebagian (fase berikutnya) · `[x]` Terimplementasi & Lulus Tes Pest

## 1. Checklist OWASP Top 10:2025

| Kategori | Kontrol Wajib | Target Fase | Status | Bukti Tes / File Implementasi |
| --- | --- | --- | --- | --- |
| **A01: Broken Access Control** | Policy pada setiap model & aksi (default deny) | F1 | `[x]` | `app/Policies/*`, `tests/Feature/VenueIsolationTest.php` |
| | Global scope `venue_id` & validasi server-side `venue_users` di setiap request | F1, F5 | `[x]` | `app/Support/VenueContext.php`, `app/Models/Scopes/VenueScope.php`, `tests/Feature/VenueIsolationTest.php` |
| | Penegakan kuota paket berlangganan (`max_venues`, `max_courts`, `max_staff`, `max_payment_accounts`) & masa tenggang H+7 di server | F1, F5 | `[x]` | `app/Actions/Venue/CreateVenue.php`, `CreateCourt.php`, `AssignVenueUser.php`, `CreatePaymentAccount.php`, `tests/Feature/SubscriptionQuotaAndGracePeriodTest.php` |
| | ID publik menggunakan ULID/slug, tanpa ID berurutan | F1, F3 | `[~]` | `app/Models/Concerns/HasUlid.php`, kolom `ulid`/`slug` di migrasi F1 (rute publik di F3) |
| | `$fillable` eksplisit di semua model (tanpa `$guarded = []`) | F1 | `[x]` | Seluruh 18 model di `app/Models/` menggunakan `$fillable` eksplisit |
| **A02: Security Misconfiguration** | Security headers (HSTS, CSP, `nosniff`, `Referrer-Policy`, `frame-ancestors`) | F0 | `[x]` | `app/Http/Middleware/SecurityHeaders.php`, `tests/Feature/FoundationTest.php` |
| | Cookie sesi `Secure`, `HttpOnly`, `SameSite=Lax/Strict` | F0 | `[x]` | `config/session.php`, `.env.example`, `tests/Feature/FoundationTest.php` |
| | Seeder demo & pintasan login lokal dikunci hanya untuk `local`/`staging` | F0, F1 | `[x]` | `database/seeders/DatabaseSeeder.php`, `LocalDevelopmentSeeder.php` |
| **A03: Supply Chain Failures** | `composer.lock` & `package-lock.json` ter-commit; `composer audit` & `npm audit` bersih | F0–F6 | `[x]` | `composer.lock`, `package-lock.json`, `laravel/socialite` `v5.32.0` tanpa advisory |
| **A04: Cryptographic Failures** | Enkripsi Eloquent pada `phone`, `customer_phone`, `account_number`, `qr_token_enc` | F1, F3 | `[x]` | `User.php`, `Booking.php`, `VenuePaymentAccount.php`, `tests/Feature/SensitiveDataEncryptionTest.php` |
| | Token QR menggunakan CSPRNG minimal 32 byte & disimpan sebagai hash SHA-256 | F4 | `[ ]` | |
| | Penyamaran PII (`a***@gmail.com`) pada log & pesan error | F1–F6 | `[~]` | `app/Actions/Auth/AuthenticateStaff.php` (`maskEmail`), `tests/Feature/AuthenticationTest.php` |
| **A05: Injection** | Query memakai Eloquent/parameter binding; allowlist untuk kolom sorting dinamis | F1–F5 | `[~]` | Seluruh query F1 memakai Eloquent/parameter binding |
| | Dilarang `v-html` & `{!! !!}` untuk input pengguna | F2–F5 | `[~]` | Halaman publik F2 (`Home.vue`, `Venues/Index.vue`, `PublicLayout.vue`, komponen `Landing/*`) bebas `v-html`; `SeoController@sitemap` memakai `e()` |
| **A06: Insecure Design** | Kalkulasi harga, DP, kuota paket, dan transisi status 100% di server (`app/Actions`) | F3, F4, F5 | `[~]` | Penegakan kuota paket & validasi di `app/Actions/*` |
| | Anti *slot hoarding*: batas hold per akun & per IP/subnet untuk user baru | F3 | `[ ]` | |
| | Rate limiting pada pembuatan booking, upload bukti, dan login | F1, F3 | `[~]` | Throttle 5/menit pada `StaffAuthController`, `tests/Feature/AuthenticationTest.php` |
| **A07: Authentication Failures** | Verifikasi email Google Sign-In di server; password staf minimal 12 karakter (`uncompromised`) | F1 | `[x]` | `HandleGoogleCallback.php`, `tests/Feature/AuthenticationTest.php` |
| | Throttle login, regenerasi sesi saat login, pesan error login generik | F1 | `[x]` | `StaffAuthController.php`, `AuthenticateStaff.php`, `tests/Feature/AuthenticationTest.php` |
| **A08: Data Integrity Failures** | Payload queue tidak memuat input mentah/class tidak tepercaya; idempotency key booking | F3, F6 | `[ ]` | |
| **A09: Logging & Alerting** | Tabel `audit_logs` & `booking_status_logs` bersifat *append-only* (trigger PostgreSQL) | F1 | `[x]` | Trigger `forbid_update_delete()`, `tests/Feature/ConcurrencyBookingTest.php` |
| | Pencatatan login gagal, perubahan peran, validasi bayar, ubah rekening, ubah langganan, dan 403 tanpa membocorkan token/PII | F1–F5 | `[~]` | `RecordAuditLog.php` mencatat login, venue, court, venue_user, payment_account, schedule_block |
| **A10: Exceptional Conditions** | Exclusion constraint `bookings_no_overlap` + trigger blokir + `lockForUpdate()` menangani `23P01` secara ramah | F1, F3 | `[x]` | `HoldSlot.php`, `CreateScheduleBlock.php`, `tests/Feature/ConcurrencyBookingTest.php` |
| | Fail-closed pada otorisasi & rollback transaksi database | F1–F5 | `[x]` | `VenueScope.php`, `app/Policies/*`, `DB::transaction`, `tests/Feature/VenueIsolationTest.php` |

## 2. Checklist Khusus Upload File & Threat Model

| ID / Kontrol | Deskripsi Mitigasi | Target Fase | Status | Bukti Tes Pest |
| --- | --- | --- | --- | --- |
| **Upload Bukti (T12)** | Validasi MIME dari isi file (`finfo`), bukan ekstensi/header client | F3 | `[ ]` | |
| | Allowlist ketat: bukti bayar hanya JPG/PNG (maks 2 MB); foto venue JPG/PNG/WebP (maks 5 MB) | F3, F5 | `[ ]` | |
| | Re-encode gambar & hapus seluruh metadata EXIF/GPS | F3, F5 | `[ ]` | |
| | Penamaan file ULID/UUID dari server (anti path traversal `../`) | F3, F5 | `[ ]` | |
| | Penyimpanan bukti terenkripsi di disk privat; diakses hanya via Controller + Policy (`nosniff`) | F3, F4 | `[ ]` | |
| **Bukti Ganda (T3)** | Simpan SHA-256 bukti & tampilkan peringatan ke staf jika hash pernah dipakai di booking lain | F3, F4 | `[ ]` | |
| **Rekening Tujuan (T10)** | Hanya Pemilik yang bisa ubah; wajib re-auth password; notifikasi email ke Pemilik & Superadmin | F5 | `[ ]` | |
| | Jeda tampil 24 jam (`visible_from`) untuk perubahan/penambahan rekening ke-2 dst. | F5 | `[ ]` | |
| **QR Check-in (T7, T8)** | Token acak 32 byte, sekali pakai (`qr_used_at`), valid hanya pada jendela H-60 menit s/d `ends_at` | F4 | `[ ]` | |

## 3. Riwayat Audit Per Fase (Diisi oleh Agent)

- **F0:** Selesai (2026-11-15). Header keamanan OWASP A02, konfigurasi cookie sesi (`HttpOnly`, `SameSite=Lax`), `composer audit` (0 advisories), `npm audit --omit=dev` (0 vulnerabilities), Pest (5/5 lulus), Pint (`passed`), dan Lighthouse CI 13.6.0 (Mobile: Perf 0.97, A11y 1.0, BP 1.0, SEO 1.0, Agentic Browsing 1.0; Desktop: 1.0 semua kategori) terverifikasi.
- **F1:** Selesai (2026-11-15). Migrasi PostgreSQL (18 tabel + `notifications`, `btree_gist`, `bookings_no_overlap`, trigger `prevent_booking_block_overlap()` & `forbid_update_delete()`), isolasi `venue_id` via `VenueScope` & `VenueContext`, Policy seluruh model, Google Sign-In & login staf dengan throttle, enkripsi kolom sensitif, serta penegakan kuota paket & masa tenggang H+7 terverifikasi oleh Pest dan Pint.
- **F2:** Selesai (2026-11-15). Landing page publik (`Home.vue` + 5 komponen `Landing/*` dengan `defineAsyncComponent`), `PublicLayout.vue`, `/venues`, `/sitemap.xml`, `/robots.txt`, `/llms.txt`, JSON-LD (`WebSite`, `Organization`, `BreadcrumbList`), tanpa Google Sign-In script di landing, Pest (26/26 lulus, 169 assertions), Pint (`passed`), dan Lighthouse CI 13.6.0 (Mobile: Perf 0.96, A11y 1.0, BP 1.0, SEO 1.0, CLS 0; Desktop: Perf 1.0, A11y 1.0, BP 1.0, SEO 1.0, CLS 0) terverifikasi.
- **F3:** *(Belum dijalankan)*
- **F4:** *(Belum dijalankan)*
- **F5:** *(Belum dijalankan)*
- **F6:** *(Belum dijalankan)*