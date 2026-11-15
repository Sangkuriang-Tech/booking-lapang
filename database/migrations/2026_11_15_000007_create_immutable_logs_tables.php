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
            CREATE TABLE booking_status_logs (
                id bigserial PRIMARY KEY,
                booking_id bigint NOT NULL REFERENCES bookings(id),
                from_status text,
                to_status text NOT NULL,
                actor_id bigint REFERENCES users(id),
                actor_kind text NOT NULL CHECK (actor_kind IN ('customer', 'staff', 'owner', 'superadmin', 'system')),
                reason text,
                created_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX booking_status_logs_booking_idx ON booking_status_logs (booking_id, created_at)');

        DB::statement(<<<'SQL'
            CREATE TABLE audit_logs (
                id bigserial PRIMARY KEY,
                venue_id bigint REFERENCES venues(id),
                actor_id bigint REFERENCES users(id),
                action text NOT NULL,
                subject_type text,
                subject_id bigint,
                old_values jsonb,
                new_values jsonb,
                ip inet,
                user_agent text,
                created_at timestamptz NOT NULL DEFAULT now()
            )
        SQL);

        DB::statement('CREATE INDEX audit_logs_venue_idx ON audit_logs (venue_id, created_at DESC)');

        DB::statement(<<<'SQL'
            CREATE TRIGGER booking_status_logs_immutable
                BEFORE UPDATE OR DELETE ON booking_status_logs
                FOR EACH ROW EXECUTE FUNCTION forbid_update_delete()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_logs_immutable
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION forbid_update_delete()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs');
        DB::statement('DROP TRIGGER IF EXISTS booking_status_logs_immutable ON booking_status_logs');
        DB::statement('DROP TABLE IF EXISTS audit_logs');
        DB::statement('DROP TABLE IF EXISTS booking_status_logs');
    }
};
