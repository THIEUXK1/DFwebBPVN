<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // NOTE: the real 'users' table (uuid PK, username/password_hash) is created by
        // 2026_07_14_999999_create_sqlite_base_schema_tables.php, ported from
        // sql_migration/02_target_normalized_schema_postgresql.sql. The framework's
        // stock users table (id/name/email/password) is intentionally not created here
        // to avoid colliding with it now that Postgres schema separation (app.users vs
        // public.users) no longer applies under SQLite's single flat namespace.

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
