<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
                ADD COLUMN ulid char(26) NOT NULL UNIQUE,
                ADD COLUMN role text NOT NULL DEFAULT 'customer',
                ADD COLUMN phone text,
                ADD COLUMN google_id text UNIQUE,
                ADD COLUMN is_active boolean NOT NULL DEFAULT true,
                ADD COLUMN last_login_at timestamptz,
                ALTER COLUMN password DROP NOT NULL,
                ALTER COLUMN email_verified_at TYPE timestamptz,
                ALTER COLUMN created_at TYPE timestamptz,
                ALTER COLUMN updated_at TYPE timestamptz,
                ADD CONSTRAINT users_role_check CHECK (role IN ('customer', 'staff', 'superadmin')),
                ADD CONSTRAINT users_auth_provider_check CHECK (
                    (role = 'customer' AND google_id IS NOT NULL)
                    OR (role <> 'customer' AND password IS NOT NULL)
                )
        SQL);

        DB::statement('CREATE UNIQUE INDEX users_email_lower_uidx ON users (lower(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_email_lower_uidx');

        DB::statement(<<<'SQL'
            ALTER TABLE users
                DROP CONSTRAINT IF EXISTS users_auth_provider_check,
                DROP CONSTRAINT IF EXISTS users_role_check,
                DROP COLUMN IF EXISTS last_login_at,
                DROP COLUMN IF EXISTS is_active,
                DROP COLUMN IF EXISTS google_id,
                DROP COLUMN IF EXISTS phone,
                DROP COLUMN IF EXISTS role,
                DROP COLUMN IF EXISTS ulid
        SQL);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
            $table->unique('email');
        });
    }
};
