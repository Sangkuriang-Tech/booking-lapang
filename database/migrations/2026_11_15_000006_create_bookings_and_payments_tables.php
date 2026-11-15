<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE bookings (
                id bigserial PRIMARY KEY,
                ulid char(26) NOT NULL UNIQUE,
                code char(8) NOT NULL UNIQUE,
                venue_id bigint NOT NULL REFERENCES venues(id),
                court_id bigint NOT NULL,
                customer_id bigint REFERENCES users(id),
                customer_name text NOT NULL,
                customer_email text,
                customer_phone text,
                source text NOT NULL CHECK (source IN ('online', 'ots')),
                status text NOT NULL CHECK (status IN (
                    'held', 'awaiting_validation', 'confirmed', 'checked_in',
                    'awaiting_reupload', 'completed', 'rejected', 'expired', 'cancelled', 'no_show'
                )),
                starts_at timestamptz NOT NULL,
                ends_at timestamptz NOT NULL,
                play_date date NOT NULL,
                price_total bigint NOT NULL CHECK (price_total >= 0),
                payment_mode text NOT NULL CHECK (payment_mode IN ('dp', 'full')),
                dp_required bigint NOT NULL DEFAULT 0 CHECK (dp_required >= 0),
                paid_total bigint NOT NULL DEFAULT 0 CHECK (paid_total >= 0),
                hold_expires_at timestamptz,
                idempotency_key text,
                qr_token_hash char(64) UNIQUE,
                qr_token_enc text,
                qr_valid_from timestamptz,
                qr_valid_until timestamptz,
                qr_used_at timestamptz,
                qr_revoked_at timestamptz,
                confirmed_at timestamptz,
                checked_in_at timestamptz,
                completed_at timestamptz,
                cancelled_at timestamptz,
                status_reason text,
                created_by bigint REFERENCES users(id),
                created_at timestamptz,
                updated_at timestamptz,
                UNIQUE (id, venue_id),
                FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id),
                CHECK (ends_at > starts_at),
                CHECK (dp_required <= price_total),
                CHECK (paid_total <= price_total),
                CHECK (source = 'ots' OR customer_id IS NOT NULL),
                CHECK (status NOT IN ('held', 'awaiting_reupload') OR hold_expires_at IS NOT NULL)
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
                EXCLUDE USING gist (court_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
                WHERE (status IN ('held', 'awaiting_validation', 'awaiting_reupload', 'confirmed', 'checked_in'))
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_booking_block_overlap() RETURNS trigger AS $$
            BEGIN
                IF NEW.status IN ('held', 'awaiting_validation', 'awaiting_reupload', 'confirmed', 'checked_in') AND EXISTS (
                    SELECT 1 FROM schedule_blocks b
                    WHERE b.court_id = NEW.court_id
                      AND tstzrange(b.starts_at, b.ends_at, '[)') && tstzrange(NEW.starts_at, NEW.ends_at, '[)')
                ) THEN
                    RAISE EXCEPTION 'slot_blocked' USING ERRCODE = '23P01';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER bookings_block_check
                BEFORE INSERT OR UPDATE OF court_id, starts_at, ends_at, status ON bookings
                FOR EACH ROW EXECUTE FUNCTION prevent_booking_block_overlap()
        SQL);

        DB::statement('CREATE INDEX bookings_court_time_idx ON bookings (court_id, starts_at)');
        DB::statement('CREATE INDEX bookings_venue_status_idx ON bookings (venue_id, status, starts_at)');
        DB::statement('CREATE INDEX bookings_venue_date_idx ON bookings (venue_id, play_date)');
        DB::statement('CREATE INDEX bookings_customer_idx ON bookings (customer_id, starts_at DESC) WHERE customer_id IS NOT NULL');
        DB::statement("CREATE INDEX bookings_hold_expiry_idx ON bookings (hold_expires_at) WHERE status IN ('held', 'awaiting_reupload')");
        DB::statement('CREATE UNIQUE INDEX bookings_idempotency_uidx ON bookings (customer_id, idempotency_key) WHERE idempotency_key IS NOT NULL');

        DB::statement(<<<'SQL'
            CREATE TABLE payments (
                id bigserial PRIMARY KEY,
                ulid char(26) NOT NULL UNIQUE,
                booking_id bigint NOT NULL,
                venue_id bigint NOT NULL,
                kind text NOT NULL CHECK (kind IN ('dp', 'settlement', 'full')),
                method text NOT NULL CHECK (method IN ('transfer', 'qris', 'cash')),
                amount bigint NOT NULL CHECK (amount > 0),
                status text NOT NULL CHECK (status IN ('pending', 'approved', 'rejected')),
                proof_path text,
                proof_sha256 char(64),
                proof_mime text,
                proof_size integer,
                proof_deleted_at timestamptz,
                submitted_at timestamptz,
                reviewed_by bigint REFERENCES users(id),
                reviewed_at timestamptz,
                reject_reason text,
                recorded_by bigint REFERENCES users(id),
                note text,
                created_at timestamptz,
                updated_at timestamptz,
                FOREIGN KEY (booking_id, venue_id) REFERENCES bookings (id, venue_id),
                CHECK (status <> 'rejected' OR reject_reason IS NOT NULL)
            )
        SQL);

        DB::statement('CREATE INDEX payments_booking_idx ON payments (booking_id)');
        DB::statement('CREATE INDEX payments_proof_hash_idx ON payments (proof_sha256) WHERE proof_sha256 IS NOT NULL');
        DB::statement("CREATE INDEX payments_pending_idx ON payments (venue_id, submitted_at) WHERE status = 'pending'");

        DB::statement(<<<'SQL'
            CREATE TABLE booking_reminders (
                id bigserial PRIMARY KEY,
                booking_id bigint NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
                kind text NOT NULL CHECK (kind IN ('h_minus_1d', 'h_minus_5h')),
                send_at timestamptz NOT NULL,
                sent_at timestamptz,
                UNIQUE (booking_id, kind)
            )
        SQL);

        DB::statement('CREATE INDEX booking_reminders_due_idx ON booking_reminders (send_at) WHERE sent_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS booking_reminders');
        DB::statement('DROP TABLE IF EXISTS payments');
        DB::statement('DROP TRIGGER IF EXISTS bookings_block_check ON bookings');
        DB::statement('DROP FUNCTION IF EXISTS prevent_booking_block_overlap()');
        DB::statement('DROP TABLE IF EXISTS bookings');
    }
};
