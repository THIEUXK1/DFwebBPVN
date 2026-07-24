<?php
// backend/database/migrations/2026_07_17_000003_create_wave3_dispatch_qr_print_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. app.routing_decisions
        Schema::create('routing_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispatch_id')->nullable(); // references app.machine_dispatches
            $table->string('mode', 20); // 'LEGACY_EXACT'|'FIXED_D1'|'MANUAL_REVIEW'
            $table->text('route')->nullable();
            $table->string('matched_rule', 100)->nullable();
            $table->string('rule_version', 10)->nullable();
            $table->json('input_snapshot')->nullable();
            $table->json('warnings')->nullable();
            $table->boolean('needs_manual_review')->default(false);
            $table->timestampTz('decided_at')->useCurrent();
            $table->timestamps();
        });

        // 2. Add routing_decision_id and idempotency_key to app.machine_dispatches
        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->uuid('routing_decision_id')->nullable();
            $table->foreign('routing_decision_id')->references('id')->on('routing_decisions')->onDelete('set null');
        });

        if (!Schema::hasColumn('machine_dispatches', 'idempotency_key')) {
            Schema::table('machine_dispatches', function (Blueprint $table) {
                $table->string('idempotency_key', 255)->nullable()->unique();
            });
        }

        // 3. Complete app.routing_decisions foreign key to machine_dispatches
        Schema::table('routing_decisions', function (Blueprint $table) {
            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('cascade');
        });

        // 4. app.qr_payloads
        Schema::create('qr_payloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispatch_id');
            $table->string('payload_version', 10);
            $table->string('payload_type', 20); // 'DYE'|'CHEM'|'PROCESS'|'EXTRA'|'FB'
            $table->text('raw_payload');
            $table->string('payload_hash', 64);
            $table->uuid('routing_decision_id')->nullable();
            $table->string('template_version', 10)->nullable();
            $table->uuid('source_record_id')->nullable();
            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('cascade');
            $table->foreign('routing_decision_id')->references('id')->on('routing_decisions')->onDelete('set null');
            $table->unique(['dispatch_id', 'payload_type', 'payload_version']);
            $table->index('payload_hash');
        });

        // 5. app.dispatch_events
        Schema::create('dispatch_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dispatch_id');
            $table->string('event_type', 30); // 'CONFIRMED'|'SENT'|'CANCELLED'
            $table->text('color')->nullable();
            $table->text('code')->nullable();
            $table->bigInteger('machine_id')->nullable();
            $table->text('tank')->nullable();
            $table->text('level')->nullable();
            $table->text('confirm_1')->nullable();
            $table->text('confirm_2')->nullable();
            $table->boolean('is_sent')->nullable();
            $table->boolean('scale_checked')->nullable();
            $table->text('raw_qr_dye')->nullable();
            $table->text('raw_qr_chemical')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('actor_user_id')->nullable();
            $table->string('legacy_source', 50)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('cascade');
            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('set null');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 6. Update app.print_jobs with target details
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->uuid('dispatch_id')->nullable();
            $table->string('idempotency_key', 255)->nullable()->unique();
            $table->uuid('correlation_id')->nullable();
            $table->timestampTz('expiry')->nullable();
            $table->json('retry_policy')->nullable();

            $table->foreign('dispatch_id')->references('id')->on('machine_dispatches')->onDelete('set null');
        });

        // 7. app.print_attempts
        Schema::create('print_attempts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('print_job_id');
            $table->smallInteger('attempt_no');
            $table->string('status', 20); // 'RECEIVED'|'PRINTING'|'PRINTED'|'FAILED'|'REJECTED'|'EXPIRED'
            $table->uuid('device_id')->nullable();
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error_detail')->nullable();
            $table->timestamps();

            $table->foreign('print_job_id')->references('id')->on('print_jobs')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('set null');
            $table->unique(['print_job_id', 'attempt_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_attempts');

        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
            $table->dropColumn(['dispatch_id', 'idempotency_key', 'correlation_id', 'expiry', 'retry_policy']);
        });

        Schema::dropIfExists('dispatch_events');
        Schema::dropIfExists('qr_payloads');

        Schema::table('machine_dispatches', function (Blueprint $table) {
            $table->dropForeign(['routing_decision_id']);
            $table->dropColumn('routing_decision_id');
        });

        if (Schema::hasColumn('machine_dispatches', 'idempotency_key')) {
            Schema::table('machine_dispatches', function (Blueprint $table) {
                $table->dropColumn('idempotency_key');
            });
        }

        Schema::dropIfExists('routing_decisions');
    }
};
