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
            CREATE TABLE subscription_plans (
                id smallserial PRIMARY KEY,
                code text NOT NULL UNIQUE,
                name text NOT NULL,
                price_monthly bigint NOT NULL CHECK (price_monthly >= 0),
                max_venues smallint,
                max_courts_per_venue smallint,
                max_staff_total smallint,
                max_payment_accounts_per_venue smallint,
                proof_retention_days smallint NOT NULL DEFAULT 90 CHECK (proof_retention_days > 0),
                can_export_reports boolean NOT NULL DEFAULT false,
                is_active boolean NOT NULL DEFAULT true,
                created_at timestamptz,
                updated_at timestamptz
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TABLE subscriptions (
                id bigserial PRIMARY KEY,
                owner_id bigint NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                plan_id smallint NOT NULL REFERENCES subscription_plans(id),
                starts_on date NOT NULL,
                ends_on date NOT NULL,
                is_active boolean NOT NULL DEFAULT true,
                max_venues smallint,
                max_courts_per_venue smallint,
                max_staff_total smallint,
                max_payment_accounts_per_venue smallint,
                proof_retention_days smallint NOT NULL DEFAULT 90,
                can_export_reports boolean NOT NULL DEFAULT false,
                note text,
                created_by bigint REFERENCES users(id),
                created_at timestamptz,
                updated_at timestamptz,
                CHECK (ends_on >= starts_on)
            )
        SQL);

        DB::statement('CREATE INDEX subscriptions_owner_idx ON subscriptions (owner_id, ends_on DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS subscriptions');
        DB::statement('DROP TABLE IF EXISTS subscription_plans');
    }
};
