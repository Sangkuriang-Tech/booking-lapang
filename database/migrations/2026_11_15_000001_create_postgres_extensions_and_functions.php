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
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION forbid_update_delete() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% bersifat append-only', TG_TABLE_NAME;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS forbid_update_delete()');
        DB::statement('DROP EXTENSION IF EXISTS btree_gist');
    }
};
