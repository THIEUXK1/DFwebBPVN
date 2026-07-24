<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Root cause: 2026_07_15_150959_create_personal_access_tokens_table.php is Sanctum's unmodified
 * default stub — it uses $table->morphs('tokenable'), which types tokenable_id as an unsigned
 * bigint. This project's App\Models\User (app.users) has a UUID primary key, so every insert into
 * that column failed at runtime ("invalid input syntax for type bigint" — see laravel.log). The
 * table was evidently dropped by hand while attempting a UUID-compatible fix (per session-log.md's
 * Phase 3 note), but the fix was never captured back into a migration, and Laravel's migrations
 * table still lists the original migration as applied (batch 1), so a plain `php artisan migrate`
 * will not recreate it. This migration restores the table with $table->uuidMorphs('tokenable') so
 * tokenable_id can actually store a UUID, without touching the original (checked-elsewhere) stub
 * or the migrations bookkeeping row.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->uuidMorphs('tokenable');
                $table->text('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Intentionally does not drop the table: if this migration is ever rolled back on an
     * environment where the table already held valid tokens (i.e. it wasn't actually missing
     * there), dropping it would revoke every active session. Down is a no-op by design.
     */
    public function down(): void
    {
        // No-op — see class docblock.
    }
};
