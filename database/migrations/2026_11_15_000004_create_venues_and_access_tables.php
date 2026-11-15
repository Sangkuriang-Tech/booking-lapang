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
                latitude numeric(9,6),
                longitude numeric(9,6),
                contact_phone text,
                timezone text NOT NULL DEFAULT 'Asia/Jakarta',
                hold_minutes smallint NOT NULL DEFAULT 20 CHECK (hold_minutes BETWEEN 5 AND 120),
                dp_percent smallint NOT NULL DEFAULT 30 CHECK (dp_percent BETWEEN 0 AND 100),
                cancel_min_hours smallint NOT NULL DEFAULT 24 CHECK (cancel_min_hours >= 0),
                reupload_minutes smallint NOT NULL DEFAULT 60 CHECK (reupload_minutes BETWEEN 5 AND 1440),
                is_published boolean NOT NULL DEFAULT false,
                created_at timestamptz,
                updated_at timestamptz,
                deleted_at timestamptz
            )
        SQL);

        DB::statement('CREATE INDEX venues_search_idx ON venues (city_slug) WHERE is_published AND deleted_at IS NULL');
        DB::statement('CREATE INDEX venues_owner_idx ON venues (owner_id) WHERE deleted_at IS NULL');

        DB::statement(<<<'SQL'
            CREATE TABLE venue_users (
                venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
                user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                role text NOT NULL CHECK (role IN ('owner', 'staff')),
                created_at timestamptz,
                PRIMARY KEY (venue_id, user_id)
            )
        SQL);

        DB::statement('CREATE INDEX venue_users_user_idx ON venue_users (user_id)');

        DB::statement(<<<'SQL'
            CREATE TABLE venue_payment_accounts (
                id bigserial PRIMARY KEY,
                venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
                kind text NOT NULL CHECK (kind IN ('bank', 'qris')),
                bank_name text,
                account_number text,
                account_holder text,
                qris_image_path text,
                is_active boolean NOT NULL DEFAULT true,
                visible_from timestamptz NOT NULL,
                created_by bigint REFERENCES users(id),
                created_at timestamptz,
                updated_at timestamptz,
                CHECK (
                    (kind = 'bank' AND bank_name IS NOT NULL AND account_number IS NOT NULL AND account_holder IS NOT NULL)
                    OR (kind = 'qris' AND qris_image_path IS NOT NULL)
                )
            )
        SQL);

        DB::statement('CREATE INDEX venue_payment_accounts_venue_idx ON venue_payment_accounts (venue_id) WHERE is_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS venue_payment_accounts');
        DB::statement('DROP TABLE IF EXISTS venue_users');
        DB::statement('DROP TABLE IF EXISTS venues');
    }
};
