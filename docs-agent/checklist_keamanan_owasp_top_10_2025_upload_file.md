# Laporan & Checklist Keamanan (`SECURITY.md`)

Dokumen ini merupakan gerbang verifikasi keamanan berdasarkan **OWASP Top 10:2025** dan **Threat Model (`threat_model_alur_booking_pembayaran_manual_dan_qr_check_in.md`)**. **AI Coding Agent wajib memperbarui status checklist dan bukti tes Pest di setiap akhir fase.**

Legenda Status: `[ ]` Belum dikerjakan · `[~]` Sebagian (fase berikutnya) · `[x]` Terimplementasi & Lulus Tes Pest

## 1. Checklist OWASP Top 10:2025

| Kategori | Kontrol Wajib | Target Fase | Status | Bukti Tes / File Implementasi |
| --- | --- | --- | --- | --- |
| **A01: Broken Access Control** | Policy pada setiap model & aksi (default deny) | F1 | `[ ]` | |
| | Global scope `venue_id` & validasi server-side `venue_users` di setiap request | F1, F5 | `[ ]` | |
| | Penegakan kuota paket berlangganan (`max_venues`, `max_courts`, `max_staff`, `max_payment_accounts`) & masa tenggang H+7 di server | F1, F5 | `[ ]` | |
| | ID publik menggunakan ULID/slug, tanpa ID berurutan | F1, F3 | `[ ]` | |
| | `$fillable` eksplisit di semua model (tanpa `$guarded = []`) | F1 | `[ ]` | |
| **A02: Security Misconfiguration** | Security headers (HSTS, CSP, `nosniff`, `Referrer-Policy`, `frame-ancestors`) | F0 | `[ ]` | |
| | Cookie sesi `Secure`, `HttpOnly`, `SameSite=Lax/Strict` | F0 | `[ ]` | |
| | Seeder demo & pintasan login lokal dikunci hanya untuk `local`/`staging` | F0, F1 | `[ ]` | |
| **A03: Supply Chain Failures** | `composer.lock` & `package-lock.json` ter-commit; `composer audit` & `npm audit` bersih | F0–F6 | `[ ]` | |
| **A04: Cryptographic Failures** | Enkripsi Eloquent pada `phone`, `customer_phone`, `account_number`, `qr_token_enc` | F1, F3 | `[ ]` | |
| | Token QR menggunakan CSPRNG minimal 32 byte & disimpan sebagai hash SHA-256 | F4 | `[ ]` | |
| | Penyamaran PII (`a***@gmail.com`) pada log & pesan error | F1–F6 | `[ ]` | |
| **A05: Injection** | Query memakai Eloquent/parameter binding; allowlist untuk kolom sorting dinamis | F1–F5 | `[ ]` | |
| | Dilarang `v-html` & `{!! !!}` untuk input pengguna | F2–F5 | `[ ]` | |
| **A06: Insecure Design** | Kalkulasi harga, DP, kuota paket, dan transisi status 100% di server (`app/Actions`) | F3, F4, F5 | `[ ]` | |
| | Anti *slot hoarding*: batas hold per akun & per IP/subnet untuk user baru | F3 | `[ ]` | |
| | Rate limiting pada pembuatan booking, upload bukti, dan login | F1, F3 | `[ ]` | |
| **A07: Authentication Failures** | Verifikasi email Google Sign-In di server; password staf minimal 12 karakter (`uncompromised`) | F1 | `[ ]` | |
| | Throttle login, regenerasi sesi saat login, pesan error login generik | F1 | `[ ]` | |
| **A08: Data Integrity Failures** | Payload queue tidak memuat input mentah/class tidak tepercaya; idempotency key booking | F3, F6 | `[ ]` | |
| **A09: Logging & Alerting** | Tabel `audit_logs` & `booking_status_logs` bersifat *append-only* (trigger PostgreSQL) | F1 | `[ ]` | |
| | Pencatatan login gagal, perubahan peran, validasi bayar, ubah rekening, ubah langganan, dan 403 tanpa membocorkan token/PII | F1–F5 | `[ ]` | |
| **A10: Exceptional Conditions** | Exclusion constraint `bookings_no_overlap` + trigger blokir + `lockForUpdate()` menangani `23P01` secara ramah | F1, F3 | `[ ]` | |
| | Fail-closed pada otorisasi & rollback transaksi database | F1–F5 | `[ ]` | |

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

- **F0:** *(Belum dijalankan)*
- **F1:** *(Belum dijalankan)*
- **F2:** *(Belum dijalankan)*
- **F3:** *(Belum dijalankan)*
- **F4:** *(Belum dijalankan)*
- **F5:** *(Belum dijalankan)*
- **F6:** *(Belum dijalankan)*