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
            CREATE TABLE sports (
                id smallserial PRIMARY KEY,
                slug text NOT NULL UNIQUE,
                name text NOT NULL,
                is_active boolean NOT NULL DEFAULT true
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE court_categories (
                id bigserial PRIMARY KEY,
                venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
                name text NOT NULL,
                created_at timestamptz,
                updated_at timestamptz,
                UNIQUE (venue_id, name),
                UNIQUE (id, venue_id)
            )
        SQL);

        DB::statement(<<<'SQL'
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
                created_at timestamptz,
                updated_at timestamptz,
                deleted_at timestamptz,
                UNIQUE (id, venue_id),
                FOREIGN KEY (category_id, venue_id) REFERENCES court_categories (id, venue_id)
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX courts_name_uidx ON courts (venue_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX courts_venue_sport_idx ON courts (venue_id, sport_id) WHERE deleted_at IS NULL');

        DB::statement(<<<'SQL'
            CREATE TABLE venue_photos (
                id bigserial PRIMARY KEY,
                venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
                court_id bigint,
                path text NOT NULL,
                alt_text text NOT NULL,
                width smallint NOT NULL,
                height smallint NOT NULL,
                is_cover boolean NOT NULL DEFAULT false,
                sort_order smallint NOT NULL DEFAULT 0,
                created_at timestamptz,
                FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id) ON DELETE CASCADE
            )
        SQL);

        DB::statement('CREATE INDEX venue_photos_venue_idx ON venue_photos (venue_id, sort_order)');
        DB::statement('CREATE UNIQUE INDEX venue_photos_one_cover_uidx ON venue_photos (venue_id) WHERE is_cover AND court_id IS NULL');

        DB::statement(<<<'SQL'
            CREATE TABLE operating_hours (
                id bigserial PRIMARY KEY,
                venue_id bigint NOT NULL REFERENCES venues(id) ON DELETE CASCADE,
                weekday smallint NOT NULL CHECK (weekday BETWEEN 0 AND 6),
                opens_min smallint NOT NULL CHECK (opens_min BETWEEN 0 AND 1439),
                closes_min smallint NOT NULL CHECK (closes_min BETWEEN 1 AND 2880),
                is_closed boolean NOT NULL DEFAULT false,
                UNIQUE (venue_id, weekday),
                CHECK (is_closed OR closes_min > opens_min)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE price_rules (
                id bigserial PRIMARY KEY,
                court_id bigint NOT NULL REFERENCES courts(id) ON DELETE CASCADE,
                day_type text NOT NULL CHECK (day_type IN ('weekday', 'weekend')),
                start_min smallint NOT NULL CHECK (start_min BETWEEN 0 AND 1439),
                end_min smallint NOT NULL CHECK (end_min BETWEEN 1 AND 2880),
                price_per_hour bigint NOT NULL CHECK (price_per_hour > 0),
                created_at timestamptz,
                updated_at timestamptz,
                CHECK (end_min > start_min),
                EXCLUDE USING gist (court_id WITH =, day_type WITH =, int4range(start_min, end_min) WITH &&)
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE schedule_blocks (
                id bigserial PRIMARY KEY,
                venue_id bigint NOT NULL,
                court_id bigint NOT NULL,
                starts_at timestamptz NOT NULL,
                ends_at timestamptz NOT NULL,
                reason text NOT NULL,
                created_by bigint NOT NULL REFERENCES users(id),
                created_at timestamptz,
                updated_at timestamptz,
                FOREIGN KEY (court_id, venue_id) REFERENCES courts (id, venue_id) ON DELETE CASCADE,
                CHECK (ends_at > starts_at),
                EXCLUDE USING gist (court_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS schedule_blocks');
        DB::statement('DROP TABLE IF EXISTS price_rules');
        DB::statement('DROP TABLE IF EXISTS operating_hours');
        DB::statement('DROP TABLE IF EXISTS venue_photos');
        DB::statement('DROP TABLE IF EXISTS courts');
        DB::statement('DROP TABLE IF EXISTS court_categories');
        DB::statement('DROP TABLE IF EXISTS sports');
    }
};
