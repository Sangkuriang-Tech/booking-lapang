# Skema Database F1: Aplikasi Booking Lapangan (PostgreSQL)

Diperbarui 15 Nov 2026 · @duta

Skema F1 terdiri dari **18 tabel aplikasi**, ditambah tabel bawaan Laravel (`sessions`, `jobs`, `failed_jobs`, `cache`, `password_reset_tokens`, `notifications`). Tiga jaminan intinya: slot tidak bisa bentrok, satu venue tidak bisa menyentuh data venue lain, dan log aksi sensitif tidak bisa diubah. DDL di bawah dijalankan lewat migration Laravel; bagian yang belum didukung schema builder (ekstensi, exclusion constraint, trigger) memakai `DB::statement`.

## Konvensi

- Kunci primer `bigserial`. ID untuk URL dan frontend memakai kolom `ulid char(26)`, tidak pernah ID berurutan.
- Uang: `bigint` rupiah tanpa desimal. Waktu: `timestamptz` (UTC). Tanggal main juga disimpan sebagai `play_date` (tanggal lokal venue) untuk laporan.
- Enum memakai `text` + `CHECK`, bukan tipe enum PostgreSQL, karena lebih mudah dimigrasi.
- Jam operasional dan band harga disimpan sebagai menit sejak 00:00; `end_min` boleh sampai 2880 agar bisa lewat tengah malam.
- Integritas lintas venue: pasangan `(id, venue_id)` dibuat unik di tabel induk, lalu tabel anak memakai foreign key komposit, sehingga `venue_id` tidak mungkin tidak konsisten dengan lapangannya.

## 1. Ekstensi dan fungsi bantu

```sql
CREATE EXTENSION IF NOT EXISTS btree_gist;

CREATE FUNCTION forbid_update_delete() RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION '% bersifat append-only', TG_TABLE_NAME;
END;
$$ LANGUAGE plpgsql;
```

## 2. Akun, paket berlangganan, dan venue

```sql
CREATE TABLE users (
  id bigserial PRIMARY KEY,
  ulid char(26) NOT NULL UNIQUE,
  role text NOT NULL CHECK (role IN ('customer','staff','superadmin')),
  name text NOT NULL,
  email text NOT NULL,
  phone text,                              -- terenkripsi (encrypted cast)
  email_verified_at timestamptz,
  google_id text UNIQUE,
  password text,
  is_active boolean NOT NULL DEFAULT true,
  last_login_at timestamptz,
  remember_token text,
  created_at timestamptz, updated_at timestamptz,
  CHECK ((role = 'customer' AND google_id IS NOT NULL) OR (role <> 'customer' AND password IS NOT NULL))
);
CREATE UNIQUE INDEX users_email_lower_uidx ON users (lower(email));

CREATE TABLE subscription_plans (
  id smallserial PRIMARY KEY,
  code text NOT NULL UNIQUE,               -- 'starter', 'pro', 'enterprise'
  name text NOT NULL,                      -- 'Starter', 'Pro', 'Enterprise'
  price_monthly bigint NOT NULL CHECK (price_monthly >= 0),
  max_venues smallint,                     -- 1, 2, NULL (unlimited)
  max_courts_per_venue smallint,           -- 4, 12, NULL (unlimited)
  max_staff_total smallint,                -- 3, 10, 25 (total karyawan gabungan per akun owner)
  max_payment_accounts_per_venue smallint, -- 1, 3, NULL (unlimited)
  proof_retention_days smallint NOT NULL DEFAULT 90 CHECK (proof_retention_days > 0),
  can_export_reports boolean NOT NULL DEFAULT false,
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamptz, updated_at timestamptz
);

CREATE TABLE subscriptions (
  id bigserial PRIMARY KEY,
  owner_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  plan_id smallint NOT NULL REFERENCES subscription_plans(id),
  starts_on date NOT NULL,
  ends_on date NOT NULL,
  is_active boolean NOT NULL DEFAULT true,
  -- Snapshot kuota saat langganan aktif agar perubahan harga/kuota master paket tidak merusak pelanggan lama
  max_venues smallint,
  max_courts_per_venue smallint,
  max_staff_total smallint,
  max_payment_accounts_per_venue smallint,
  proof_retention_days smallint NOT NULL DEFAULT 90,
  can_export_reports boolean NOT NULL DEFAULT false,
  note text,
  created_by bigint REFERENCES users(id),
  created_at timestamptz, updated_at timestamptz,
  CHECK (ends_on >= starts_on)
);
CREATE INDEX subscriptions_owner_idx ON subscriptions (owner_id, ends_on DESC);

CREATE TABLE venues (
  id bigserial PRIMARY KEY,
  ulid char(26) NOT NULL UNIQUE,
  owner_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  slug text NOT NULL UNIQUE,
  name text NOT NULL,
  description text,
  city text NOT NULL,
  city_slug text NOT NULL,
  address text NOT NULL,
  latitude numeric(9,6), longitude numeric(9,6),
  contact_phone text,
  timezone text NOT NULL DEFAULT 'Asia/Jakarta',
  hold_minutes smallint NOT NULL DEFAULT 20 CHECK (hold_minutes BETWEEN 5 AND 120),
  dp_percent smallint NOT NULL DEFAULT 30 CHECK (dp_percent BETWEEN 0 AND 100),
  cancel_min_hours smallint NOT NULL DEFAULT 24 CHECK (cancel_min_hours >= 0),
  reupload_minutes smallint NOT NULL DEFAULT 60 CHECK (reupload_minutes BETWEEN 5 AND 1440),
  is_published boolean NOT NULL DEFAULT false,
  created_at timestamptz, updated_at timestamptz, deleted_at timestamptz
);
CREATE INDEX venues_search_idx ON venues (city_slug) WHERE is_published AND deleted_at IS NULL;
CREATE INDEX venues_owner_idx ON venues (owner_id) WHERE deleted_at IS NULL;

CREATE TABLE venue_users (
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role text NOT NULL CHECK (role IN ('owner','staff')),
  created_at timestamptz,
  PRIMARY KEY (venue_id, user_id)
);
CREATE INDEX venue_users_user_idx ON venue_users (user_id);

CREATE TABLE venue_payment_accounts (
  id bigserial PRIMARY KEY,
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  kind text NOT NULL CHECK (kind IN ('bank','qris')),
  bank_name text,
  account_number text,                     -- terenkripsi (encrypted cast)
  account_holder text,
  qris_image_path text,                    -- file privat
  is_active boolean NOT NULL DEFAULT true,
  visible_from timestamptz NOT NULL,       -- langsung now() jika rekening pertama venue; +24 jam jika perubahan/penambahan berikutnya
  created_by bigint REFERENCES users(id),
  created_at timestamptz, updated_at timestamptz,
  CHECK ((kind = 'bank' AND bank_name IS NOT NULL AND account_number IS NOT NULL AND account_holder IS NOT NULL)
      OR (kind = 'qris' AND qris_image_path IS NOT NULL))
);
CREATE INDEX venue_payment_accounts_venue_idx ON venue_payment_accounts (venue_id) WHERE is_active;
```

**Status Langganan & Masa Tenggang (*Grace Period*):**
- **Aktif:** `subscriptions.is_active = true` dan tanggal hari ini (zona venue) berada di antara `starts_on` dan `ends_on`, serta `venues.is_published = true`. Venue tampil di pencarian publik dan menerima booking baru.
- **Masa Tenggang (`H+1` s/d `H+7` setelah `ends_on`):** venue otomatis disembunyikan dari pencarian publik dan menolak booking online baru, namun pemilik/staf tetap dapat memvalidasi sisa pembayaran, melakukan scan QR check-in untuk booking lama, membuat OTS darurat, dan melihat laporan.
- **Nonaktif (`> H+7` setelah `ends_on` atau `is_active = false`):** pembuatan booking baru (online maupun OTS) dikunci sepenuhnya sampai langganan diperpanjang.

## 3. Lapangan, jadwal, dan harga

```sql
CREATE TABLE sports (
  id smallserial PRIMARY KEY,
  slug text NOT NULL UNIQUE,
  name text NOT NULL,
  is_active boolean NOT NULL DEFAULT true
);

CREATE TABLE court_categories (
  id bigserial PRIMARY KEY,
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  name text NOT NULL,
  created_at timestamptz, updated_at timestamptz,
  UNIQUE (venue_id, name),
  UNIQUE (id, venue_id)
);

CREATE TABLE courts (
  id bigserial PRIMARY KEY,
  ulid char(26) NOT NULL UNIQUE,
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  sport_id smallint NOT NULL REFERENCES sports(id),
  category_id bigint,
  name text NOT NULL,
  description text,
  is_active boolean NOT NULL DEFAULT true,
  sort_order smallint NOT NULL DEFAULT 0,
  created_at timestamptz, updated_at timestamptz, deleted_at timestamptz,
  UNIQUE (id, venue_id),
  FOREIGN KEY (category_id, venue_id) REFERENCES court_categories (id, venue_id)
);
CREATE UNIQUE INDEX courts_name_uidx ON courts (venue_id, name) WHERE deleted_at IS NULL;
CREATE INDEX courts_venue_sport_idx ON courts (venue_id, sport_id) WHERE deleted_at IS NULL;

CREATE TABLE venue_photos (
  id bigserial PRIMARY KEY,
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  court_id bigint,
  path text NOT NULL,
  alt_text text NOT NULL,
  width smallint NOT NULL,                 -- dimensi disimpan agar tidak ada layout shift
  height smallint NOT NULL,
  is_cover boolean NOT NULL DEFAULT false,
  sort_order smallint NOT NULL DEFAULT 0,
  created_at timestamptz,
  FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id) ON DELETE CASCADE
);
CREATE INDEX venue_photos_venue_idx ON venue_photos (venue_id, sort_order);
CREATE UNIQUE INDEX venue_photos_one_cover_uidx ON venue_photos (venue_id) WHERE is_cover AND court_id IS NULL;

CREATE TABLE operating_hours (
  id bigserial PRIMARY KEY,
  venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
  weekday smallint NOT NULL CHECK (weekday BETWEEN 0 AND 6),   -- 0 = Minggu
  opens_min smallint NOT NULL CHECK (opens_min BETWEEN 0 AND 1439),
  closes_min smallint NOT NULL CHECK (closes_min BETWEEN 1 AND 2880),
  is_closed boolean NOT NULL DEFAULT false,
  UNIQUE (venue_id, weekday),
  CHECK (is_closed OR closes_min > opens_min)
);

CREATE TABLE price_rules (
  id bigserial PRIMARY KEY,
  court_id bigint NOT NULL REFERENCES courts(id) ON DELETE CASCADE,
  day_type text NOT NULL CHECK (day_type IN ('weekday','weekend')),
  start_min smallint NOT NULL CHECK (start_min BETWEEN 0 AND 1439),
  end_min smallint NOT NULL CHECK (end_min BETWEEN 1 AND 2880),
  price_per_hour bigint NOT NULL CHECK (price_per_hour > 0),
  created_at timestamptz, updated_at timestamptz,
  CHECK (end_min > start_min),
  EXCLUDE USING gist (court_id WITH =, day_type WITH =, int4range(start_min, end_min) WITH &&)
);

CREATE TABLE schedule_blocks (
  id bigserial PRIMARY KEY,
  venue_id bigint NOT NULL,
  court_id bigint NOT NULL,
  starts_at timestamptz NOT NULL,
  ends_at timestamptz NOT NULL,
  reason text NOT NULL,
  created_by bigint NOT NULL REFERENCES users(id),
  created_at timestamptz, updated_at timestamptz,
  FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id) ON DELETE CASCADE,
  CHECK (ends_at > starts_at),
  EXCLUDE USING gist (court_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
);
```

## 4. Booking, pembayaran, dan pengingat

```sql
CREATE TABLE bookings (
  id bigserial PRIMARY KEY,
  ulid char(26) NOT NULL UNIQUE,
  code char(8) NOT NULL UNIQUE,            -- kode pendek untuk dicari staf
  venue_id bigint NOT NULL REFERENCES venues(id),
  court_id bigint NOT NULL,
  customer_id bigint REFERENCES users(id),  -- null untuk booking OTS
  customer_name text NOT NULL,
  customer_email text,
  customer_phone text,                      -- terenkripsi (encrypted cast)
  source text NOT NULL CHECK (source IN ('online','ots')),
  status text NOT NULL CHECK (status IN ('held','awaiting_validation','confirmed','checked_in',
                                         'awaiting_reupload','completed','rejected','expired','cancelled','no_show')),
  starts_at timestamptz NOT NULL,
  ends_at timestamptz NOT NULL,
  play_date date NOT NULL,                  -- tanggal lokal venue
  price_total bigint NOT NULL CHECK (price_total >= 0),
  payment_mode text NOT NULL CHECK (payment_mode IN ('dp','full')),
  dp_required bigint NOT NULL DEFAULT 0 CHECK (dp_required >= 0),
  paid_total bigint NOT NULL DEFAULT 0 CHECK (paid_total >= 0),
  hold_expires_at timestamptz,
  idempotency_key text,
  qr_token_hash char(64) UNIQUE,            -- sha256 dari token; dipakai saat scan
  qr_token_enc text,                        -- token terenkripsi, hanya untuk menampilkan ulang QR
  qr_valid_from timestamptz,
  qr_valid_until timestamptz,
  qr_used_at timestamptz,
  qr_revoked_at timestamptz,
  confirmed_at timestamptz, checked_in_at timestamptz, completed_at timestamptz, cancelled_at timestamptz,
  status_reason text,
  created_by bigint REFERENCES users(id),
  created_at timestamptz, updated_at timestamptz,
  UNIQUE (id, venue_id),
  FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id),
  CHECK (ends_at > starts_at),
  CHECK (dp_required <= price_total),
  CHECK (paid_total <= price_total),
  CHECK (source = 'ots' OR customer_id IS NOT NULL),
  CHECK (status NOT IN ('held','awaiting_reupload') OR hold_expires_at IS NOT NULL)
);

-- Anti double booking (inti sistem)
ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
  EXCLUDE USING gist (court_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
  WHERE (status IN ('held','awaiting_validation','awaiting_reupload','confirmed','checked_in'));

-- Booking tidak boleh menimpa blokir jadwal (memakai kode error yang sama dengan exclusion violation)
CREATE FUNCTION prevent_booking_block_overlap() RETURNS trigger AS $$
BEGIN
  IF NEW.status IN ('held','awaiting_validation','awaiting_reupload','confirmed','checked_in') AND EXISTS (
    SELECT 1 FROM schedule_blocks b
    WHERE b.court_id = NEW.court_id
      AND tstzrange(b.starts_at, b.ends_at, '[)') && tstzrange(NEW.starts_at, NEW.ends_at, '[)')
  ) THEN
    RAISE EXCEPTION 'slot_blocked' USING ERRCODE = '23P01';
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bookings_block_check
  BEFORE INSERT OR UPDATE OF court_id, starts_at, ends_at, status ON bookings
  FOR EACH ROW EXECUTE FUNCTION prevent_booking_block_overlap();

CREATE INDEX bookings_court_time_idx ON bookings (court_id, starts_at);
CREATE INDEX bookings_venue_status_idx ON bookings (venue_id, status, starts_at);
CREATE INDEX bookings_venue_date_idx ON bookings (venue_id, play_date);
CREATE INDEX bookings_customer_idx ON bookings (customer_id, starts_at DESC) WHERE customer_id IS NOT NULL;
CREATE INDEX bookings_hold_expiry_idx ON bookings (hold_expires_at) WHERE status IN ('held','awaiting_reupload');
CREATE UNIQUE INDEX bookings_idempotency_uidx ON bookings (customer_id, idempotency_key) WHERE idempotency_key IS NOT NULL;

CREATE TABLE payments (
  id bigserial PRIMARY KEY,
  ulid char(26) NOT NULL UNIQUE,
  booking_id bigint NOT NULL,
  venue_id bigint NOT NULL,
  kind text NOT NULL CHECK (kind IN ('dp','settlement','full')),
  method text NOT NULL CHECK (method IN ('transfer','qris','cash')),
  amount bigint NOT NULL CHECK (amount > 0),
  status text NOT NULL CHECK (status IN ('pending','approved','rejected')),
  proof_path text,                          -- file terenkripsi di disk privat
  proof_sha256 char(64),                    -- deteksi bukti yang dipakai ulang
  proof_mime text,
  proof_size integer,
  proof_deleted_at timestamptz,             -- diisi job retensi
  submitted_at timestamptz,
  reviewed_by bigint REFERENCES users(id),
  reviewed_at timestamptz,
  reject_reason text,
  recorded_by bigint REFERENCES users(id),  -- staf yang mencatat pelunasan atau pembayaran OTS
  note text,
  created_at timestamptz, updated_at timestamptz,
  FOREIGN KEY (booking_id, venue_id) REFERENCES bookings (id, venue_id),
  CHECK (status <> 'rejected' OR reject_reason IS NOT NULL)
);
CREATE INDEX payments_booking_idx ON payments (booking_id);
CREATE INDEX payments_proof_hash_idx ON payments (proof_sha256) WHERE proof_sha256 IS NOT NULL;
CREATE INDEX payments_pending_idx ON payments (venue_id, submitted_at) WHERE status = 'pending';

CREATE TABLE booking_reminders (
  id bigserial PRIMARY KEY,
  booking_id bigint NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  kind text NOT NULL CHECK (kind IN ('h_minus_1d','h_minus_5h')),
  send_at timestamptz NOT NULL,
  sent_at timestamptz,
  UNIQUE (booking_id, kind)
);
CREATE INDEX booking_reminders_due_idx ON booking_reminders (send_at) WHERE sent_at IS NULL;
```

## 5. Log yang tidak bisa diubah

```sql
CREATE TABLE booking_status_logs (
  id bigserial PRIMARY KEY,
  booking_id bigint NOT NULL REFERENCES bookings(id),
  from_status text,
  to_status text NOT NULL,
  actor_id bigint REFERENCES users(id),
  actor_kind text NOT NULL CHECK (actor_kind IN ('customer','staff','owner','superadmin','system')),
  reason text,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX booking_status_logs_booking_idx ON booking_status_logs (booking_id, created_at);

CREATE TABLE audit_logs (
  id bigserial PRIMARY KEY,
  venue_id bigint REFERENCES venues(id),
  actor_id bigint REFERENCES users(id),
  action text NOT NULL,                     -- contoh: payment_account.changed, subscription.updated
  subject_type text,
  subject_id bigint,
  old_values jsonb,                         -- tanpa data sensitif
  new_values jsonb,
  ip inet,
  user_agent text,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX audit_logs_venue_idx ON audit_logs (venue_id, created_at DESC);

CREATE TRIGGER booking_status_logs_immutable BEFORE UPDATE OR DELETE ON booking_status_logs
  FOR EACH ROW EXECUTE FUNCTION forbid_update_delete();
CREATE TRIGGER audit_logs_immutable BEFORE UPDATE OR DELETE ON audit_logs
  FOR EACH ROW EXECUTE FUNCTION forbid_update_delete();
```

## Transisi status booking

Semua transisi lewat satu Action yang mengubah `bookings` dan menulis `booking_status_logs` dalam transaksi yang sama. Status akhir: `completed`, `expired`, `cancelled`, `rejected`, `no_show`. Hanya lima status yang mengunci slot: `held`, `awaiting_validation`, `awaiting_reupload`, `confirmed`, dan `checked_in`.

| Dari | Ke | Dipicu oleh | Syarat |
| --- | --- | --- | --- |
| (baru) | `held` | Pembooking | Slot kosong, langganan venue aktif, sudah login, di bawah batas hold aktif per pengguna maupun per IP/subnet |
| (baru, OTS) | `confirmed` | Staf | Slot kosong, langganan venue aktif atau dalam masa tenggang H+7, pembayaran dicatat |
| (baru, OTS) | `checked_in` | Staf | Pelanggan walk-in langsung masuk lapangan saat itu juga; pembayaran dicatat |
| `held` | `awaiting_validation` | Pembooking | Bukti diunggah sebelum `hold_expires_at` |
| `held` | `expired` | Sistem | `hold_expires_at` terlewati |
| `held` | `cancelled` | Pembooking | Tidak ada |
| `awaiting_validation` | `confirmed` | Staf | Bukti valid; buat token QR dan jadwal pengingat |
| `awaiting_validation` | `awaiting_reupload` | Staf | Minta bukti baru; alasan wajib; `hold_expires_at` diisi sekarang + `reupload_minutes` (default 60 menit) |
| `awaiting_validation` | `rejected` | Staf | Tolak langsung (misalnya bukti palsu); alasan wajib; slot dilepas |
| `awaiting_validation` | `cancelled` | Pembooking atau staf | Sesuai kebijakan venue |
| `awaiting_reupload` | `awaiting_validation` | Pembooking | Bukti baru diunggah sebelum `hold_expires_at` |
| `awaiting_reupload` | `rejected` | Sistem | `hold_expires_at` terlewati tanpa bukti baru; slot dilepas |
| `awaiting_reupload` | `cancelled` | Pembooking | Tidak ada |
| `confirmed` | `checked_in` | Staf | Scan QR valid di dalam jendela `qr_valid_from` sampai `qr_valid_until`; pelunasan dicatat bila DP |
| `confirmed` | `cancelled` | Pembooking atau staf | Sesuai `cancel_min_hours`; token dicabut |
| `confirmed` | `no_show` | Sistem atau staf | `ends_at` terlewati tanpa check-in |
| `checked_in` | `completed` | Staf atau sistem | Setelah `ends_at` (otomatis oleh scheduler jika staf lupa menandai selesai) |

## Dijaga database dan dijaga Action

**Dijaga database (tidak bisa dilanggar oleh bug aplikasi):**

- Dua booking aktif tidak bisa tumpang tindih di lapangan yang sama; dua blokir jadwal juga tidak; booking tidak bisa menimpa blokir.
- Band harga satu lapangan tidak bisa tumpang tindih.
- `venue_id` selalu konsisten dengan lapangan (foreign key komposit), sehingga data tidak bisa tercampur antar venue lewat relasi.
- Nominal tidak negatif, DP tidak melebihi total, total terbayar tidak melebihi total.
- Token QR unik, idempotency key unik per pengguna, log status dan audit tidak bisa diubah atau dihapus.

**Dijaga Action (perlu tes):**

- **Penegakan Kuota Paket Berlangganan (`subscription_plans` / `subscriptions`):**
  - `CreateVenue`: hitung venue aktif milik `owner_id` (`deleted_at IS NULL`); tolak jika `>= max_venues`.
  - `CreateCourt`: hitung lapangan aktif milik venue (`deleted_at IS NULL`); tolak jika `>= max_courts_per_venue`.
  - `CreateStaff` / `AssignVenueUser`: hitung total karyawan unik (`role = 'staff'`) di seluruh venue milik `owner_id`; tolak jika `>= max_staff_total`.
  - `CreatePaymentAccount`: hitung rekening/QRIS aktif milik venue; tolak jika `>= max_payment_accounts_per_venue`.
- Validitas transisi status (tabel di atas) dan penulisan log pada setiap transisi.
- Harga dihitung dari `price_rules` per jam (memecah di batas band untuk durasi 1 jam maupun multi-jam), DP dari `dp_percent`, dan `play_date` dari zona venue. Nilai dari client tidak dipercaya.
- `paid_total` selalu sama dengan jumlah `payments` berstatus `approved`.
- **Konkurensi Booking vs Blokir Jadwal:** Baik `HoldSlot`, `CreateOtsBooking`, maupun `CreateScheduleBlock` **wajib** mengunci baris lapangan (`Court::where('id', $courtId)->lockForUpdate()->first()`) di dalam transaksi agar celah balapan antara trigger dan insert tertutup sempurna. Ubah kode error `23P01` menjadi pesan ramah.
- Batas booking `held` aktif per pengguna dan pembatasan berbasis IP/subnet untuk mencegah *slot hoarding* multi-akun.
- Kebijakan pembatalan dan pengecualian jeda `visible_from` (rekening pertama langsung tampil `now()`, perubahan/penambahan rekening berikutnya menunggu 24 jam).
- Isolasi data: global scope `venue_id` dan Policy tetap wajib; foreign key komposit hanya lapisan kedua. Untuk pemilik atau staf dengan banyak venue (`venue_users`), venue aktif disimpan di sesi dan divalidasi ke `venue_users` pada setiap request; venue dipilih lewat modal setelah login (daftarnya hanya venue milik pengguna itu menurut tabel keanggotaan; otomatis jika hanya satu), dan semua laporan dibuat per venue aktif. Pemilik terakhir sebuah venue (`venue_users.role = 'owner'`) tidak boleh dihapus atau diturunkan perannya.

## Kolom yang dienkripsi

- `users.phone` & `bookings.customer_phone`: `encrypted` cast Eloquent.
- `venue_payment_accounts.account_number`: `encrypted` cast Eloquent.
- `bookings.qr_token_enc`: terenkripsi; lookup saat scan memakai `qr_token_hash`.
- File bukti bayar (`payments.proof_path`): dienkripsi sebelum ditulis ke disk privat.
- `customer_name`, `customer_email`, dan `users.name`, `users.email` sengaja tidak dienkripsi (keputusan produk), sehingga bisa dipakai untuk login dan pencarian.

## Query dan job inti

- **Slot kosong:** ambil `operating_hours` untuk hari itu, bagi menjadi slot satu jam, buang yang beririsan dengan booking berstatus terkunci dan `schedule_blocks`, lalu hitung harga dari `price_rules`. Muat per tanggal. Ketersediaan di layar hanya petunjuk; kebenaran akhir ditentukan constraint saat booking dibuat.
- **Job terjadwal tiap menit:**
  1. Ubah `held` yang `hold_expires_at`-nya terlewati menjadi `expired` (melepas slot).
  2. Ubah `awaiting_reupload` yang `hold_expires_at`-nya terlewati menjadi `rejected` (melepas slot).
  3. Ubah `checked_in` yang `ends_at`-nya terlewati menjadi `completed`.
  4. Ubah `confirmed` yang `ends_at`-nya terlewati tanpa check-in menjadi `no_show`.
  Tulis log status per baris dengan `actor_kind = 'system'`. Pakai pembatasan jumlah baris per putaran (`limit`) dan `lockForUpdate()` agar aman bila job berjalan ganda.
- **Antrean validasi staf:** `payments` berstatus `pending` per `venue_id` (sudah ada partial index). Booking `awaiting_reupload` tampil sebagai menunggu bukti baru.
- **Pengingat:** job memproses `booking_reminders` yang `send_at`-nya tiba dan `sent_at`-nya kosong; `UNIQUE (booking_id, kind)` mencegah pengingat ganda.
- **Laporan harian dan bulanan:** kelompokkan per `play_date`; pendapatan dari `payments` berstatus `approved`, jumlah booking per status dari `bookings`.
- **Retensi bukti:** job harian menghapus file bukti yang lebih lama dari batas retensi paket langganan (`subscriptions.proof_retention_days`, default 90 hari) setelah `play_date`, mengosongkan `proof_path`, dan mengisi `proof_deleted_at`.

## Data contoh untuk seeder

Semua nilai di bawah adalah data dummy untuk pengembangan dan tes, bukan data asli Antic Futsal Center. Seeder hanya boleh berjalan di lingkungan lokal dan staging. Tanggal booking dihitung relatif terhadap tanggal seeder dijalankan.

**Paket Berlangganan (`subscription_plans`):**

| Code | Name | Harga/Bulan | Max Venue | Max Lapangan/Venue | Max Karyawan Total | Max Rekening/Venue | Retensi Bukti | Ekspor Laporan |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `starter` | Starter | 150.000 | 1 | 4 | 3 | 1 | 30 hari | `false` |
| `pro` | Pro | 350.000 | 2 | 12 | 10 | 3 | 90 hari | `true` |
| `enterprise` | Enterprise | 750.000 | `NULL` | `NULL` | 25 | `NULL` | 180 hari | `true` |

**Olahraga (`sports`):** futsal, mini-soccer, padel, basket, badminton.

**Akun Pemilik & Langganan:**
- `owner@example.test` berlangganan paket **Pro** aktif selama 12 bulan (maksimal 2 venue, 12 lapangan/venue, 10 karyawan gabungan), sehingga dapat memiliki Venue 1 dan Venue 2 sekaligus.

**Venue 1: Antic Futsal Center (contoh).** Pemilik `owner@example.test`, slug `antic-futsal-center-contoh`, kota `Kota Contoh`, zona waktu Asia/Jakarta, `is_published = true`, `hold_minutes` 20, `dp_percent` 30, `reupload_minutes` 60. Kategori lapangan: Indoor dan Outdoor.

| Lapangan | Olahraga | Kategori |
| --- | --- | --- |
| Mini Soccer A | mini-soccer | Outdoor |
| Mini Soccer B | mini-soccer | Outdoor |
| Futsal 1 | futsal | Indoor |
| Padel 1 | padel | Indoor |
| Basket 1 | basket | Indoor |

Jam operasional: Senin sampai Jumat 08.00 sampai 23.00; Sabtu dan Minggu 07.00 sampai 01.00 hari berikutnya (sengaja melewati tengah malam untuk menguji `closes_min` di atas 1440).

Harga per jam dalam rupiah (dummy, berbeda tiap lapangan). Hari kerja: siang 08.00 sampai 17.00, malam 17.00 sampai 23.00. Akhir pekan: siang 07.00 sampai 17.00, malam 17.00 sampai 01.00.

| Lapangan | Hari kerja siang | Hari kerja malam | Akhir pekan siang | Akhir pekan malam |
| --- | --- | --- | --- | --- |
| Mini Soccer A | 250.000 | 350.000 | 300.000 | 400.000 |
| Mini Soccer B | 220.000 | 320.000 | 270.000 | 370.000 |
| Futsal 1 | 120.000 | 180.000 | 150.000 | 200.000 |
| Padel 1 | 150.000 | 250.000 | 200.000 | 300.000 |
| Basket 1 | 100.000 | 150.000 | 130.000 | 180.000 |

**Venue 2: Arena Contoh Dua.** Pemilik `owner@example.test`, kota `Kota Contoh`, zona waktu Asia/Makassar (menguji format waktu per venue), `is_published = false` (menguji tampilan venue belum dipublikasikan / nonaktif dan penutupan booking baru). Lapangan: Futsal Utama (futsal, Indoor) dan Badminton 1 (badminton, Indoor). Jam 09.00 sampai 22.00 setiap hari. Harga satu band per jenis hari: futsal Rp130.000 (hari kerja) dan Rp190.000 (akhir pekan); badminton Rp60.000 dan Rp80.000.

**Akun contoh lainnya** (email memakai domain `example.test`, kata sandi hanya untuk pengembangan lokal):

| Akun | Peran Global | Keanggotaan (`venue_users`) | Tujuan tes |
| --- | --- | --- | --- |
| `superadmin@example.test` | `superadmin` | semua | Akses lintas venue, kelola paket & langganan |
| `owner@example.test` | `staff` | Venue 1 (`owner`) & Venue 2 (`owner`) | Modal pilih venue tampil, kuota paket Pro |
| `staff1@example.test` | `staff` | Venue 1 (`staff`) | Modal dilewati |
| `staff2@example.test` | `staff` | Venue 1 (`staff`) & Venue 2 (`staff`) | Modal tampil |
| `customer1@example.test`, `customer2@example.test` | `customer` | tidak ada | Alur booking; `google_id` & `phone` diisi dummy |

**Rekening tujuan Venue 1 (dummy):** Bank Contoh, nomor `0000000000`, atas nama Antic Futsal Center (contoh), ditambah satu gambar QRIS placeholder (`visible_from` diisi waktu lampau agar langsung tampil).

**Booking contoh di Venue 1.** Satu booking per status, ditambah satu booking OTS dan satu slot yang sengaja dikosongkan untuk tes konkurensi (Mini Soccer A, besok 21.00).

| Status | Lapangan | Waktu | Catatan |
| --- | --- | --- | --- |
| `held` | Mini Soccer A | besok 19.00 | `hold_expires_at` 20 menit dari seeder |
| `awaiting_validation` | Mini Soccer B | besok 20.00 | Ada bukti dummy, DP 30% |
| `awaiting_reupload` | Futsal 1 | besok 18.00 | Alasan: bukti buram; batas 60 menit |
| `confirmed` (DP) | Padel 1 | lusa 19.00 | DP disetujui, sisa belum lunas, token QR aktif |
| `confirmed` (lunas) | Basket 1 | lusa 20.00 | Lunas |
| `checked_in` | Mini Soccer A | hari ini, jam yang sedang berjalan | Sudah scan QR |
| `completed` | Futsal 1 | kemarin 19.00 |  |
| `rejected` | Padel 1 | kemarin 20.00 | Alasan: bukti tidak sah |
| `expired` | Basket 1 | kemarin 18.00 |  |
| `cancelled` | Mini Soccer B | 2 hari lalu |  |
| `no_show` | Mini Soccer A | 2 hari lalu |  |
| `confirmed` (OTS) | Mini Soccer B | lusa 19.00 | Dibuat staf, dibayar tunai |