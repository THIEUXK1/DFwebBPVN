<?php
// Ported from sql_migration/02_target_normalized_schema_postgresql.sql for local SQLite dev/testing.
// In the real Postgres deployment these base tables are created by that raw SQL script
// (outside Artisan); Laravel migrations only ALTER them afterward. SQLite has no
// separate bootstrap step, so this migration recreates them here, before every other
// migration, using cross-driver Schema Builder calls instead of Postgres-only DDL.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 100)->unique();
            $table->string('display_name', 200)->nullable();
            $table->text('password_hash');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 100)->unique();
            $table->string('name', 200)->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('tanks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->string('code', 100);
            $table->string('name', 200)->nullable();
            $table->foreign('machine_id')->references('id')->on('machines');
            $table->unique(['machine_id', 'code']);
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('legacy_batch_id')->nullable();
            $table->text('color')->nullable();
            $table->text('product_code')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->unsignedBigInteger('tank_id')->nullable();
            $table->text('level_code')->nullable();
            $table->string('status', 30)->default('NEW');
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('machine_id')->references('id')->on('machines');
            $table->foreign('tank_id')->references('id')->on('tanks');
            $table->unique(['legacy_batch_id', 'product_code', 'machine_id']);
        });

        Schema::create('machine_dispatches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('legacy_row_no');
            $table->bigInteger('legacy_id')->nullable();
            $table->uuid('batch_id')->nullable();
            $table->text('confirm_1')->nullable();
            $table->text('confirm_2')->nullable();
            $table->text('sending_value')->nullable();
            $table->text('sent_value')->nullable();
            $table->timestamp('confirmed_at_1')->nullable();
            $table->timestamp('confirmed_at_2')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_sent')->nullable();
            $table->boolean('scale_checked')->nullable();
            $table->text('raw_qr_dye')->nullable();
            $table->text('raw_qr_chemical')->nullable();
            $table->string('queue_state', 30);
            $table->string('source_table', 100);
            $table->uuid('locked_by')->nullable();
            $table->timestampTz('locked_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestampTz('created_at')->useCurrent();
            $table->foreign('batch_id')->references('id')->on('production_batches');
            $table->foreign('locked_by')->references('id')->on('users');
            $table->unique(['source_table', 'legacy_row_no']);
            $table->index('queue_state');
            $table->index('batch_id');
        });

        Schema::create('scale_measurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legacy_source', 30);
            $table->bigInteger('legacy_id');
            $table->text('legacy_batch_id')->nullable();
            $table->text('color')->nullable();
            $table->text('product_code')->nullable();
            $table->text('machine_code')->nullable();
            $table->text('level_code')->nullable();
            $table->text('rack_code')->nullable();
            $table->text('dye_code')->nullable();
            $table->decimal('weight', 18, 6)->nullable();
            $table->text('process_code')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->text('process_color')->nullable();
            $table->boolean('warehouse_done')->nullable();
            $table->timestamp('warehouse_time')->nullable();
            $table->string('material_type', 20);
            $table->timestampTz('imported_at')->useCurrent();
            $table->unique(['legacy_source', 'legacy_id']);
            $table->index('legacy_batch_id');
            $table->index('measured_at');
            $table->index(['material_type', 'dye_code']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->text('entity_id')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->string('client_ip')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('scale_measurements');
        Schema::dropIfExists('machine_dispatches');
        Schema::dropIfExists('production_batches');
        Schema::dropIfExists('tanks');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');
    }
};
