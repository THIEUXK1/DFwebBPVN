<?php
// backend/database/migrations/2026_07_17_000002_create_wave2_chemical_call_tables.php

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
        // 1. app.machine_chemical_channels (if not exists)
        if (!Schema::hasTable('machine_chemical_channels')) {
            Schema::create('machine_chemical_channels', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('machine_id');
                $table->integer('channel_number');
                $table->string('chemical_code', 100);
                $table->boolean('is_active')->default(true);
                $table->bigInteger('legacy_id')->nullable();
                $table->timestamps();

                $table->foreign('machine_id')->references('id')->on('machines')->onDelete('cascade');
                $table->unique(['machine_id', 'channel_number']);
            });
        }

        // 2. app.chemical_call_requests
        Schema::create('chemical_call_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->bigInteger('channel_id');
            $table->bigInteger('machine_id')->nullable();
            $table->string('status', 20)->default('CREATED');
            $table->timestampTz('requested_at')->nullable();
            $table->uuid('requested_by_user_id')->nullable();
            $table->bigInteger('requested_by_workstation_id')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by_user_id')->nullable();
            $table->timestampTz('confirmed_at')->nullable();
            $table->uuid('confirmed_by_user_id')->nullable();
            $table->bigInteger('confirmed_by_workstation_id')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->string('idempotency_key', 255)->unique();
            $table->integer('row_version')->default(1);
            $table->string('legacy_source', 50)->nullable();
            $table->bigInteger('legacy_id')->nullable();
            $table->timestamps();

            $table->foreign('channel_id')->references('id')->on('machine_chemical_channels')->onDelete('cascade');
            $table->foreign('machine_id')->references('id')->on('machines')->onDelete('set null');
            $table->foreign('requested_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('requested_by_workstation_id')->references('id')->on('workstations')->onDelete('set null');
            $table->foreign('acknowledged_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('confirmed_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('confirmed_by_workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });

        // Add partial unique index: only 1 active request per channel
        DB::statement("CREATE UNIQUE INDEX uq_channel_active_order ON chemical_call_requests (channel_id) WHERE status IN ('CREATED', 'ORDERED', 'ACKNOWLEDGED')");

        // 3. app.chemical_call_request_events
        Schema::create('chemical_call_request_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('request_id');
            $table->string('event_type', 30);
            $table->timestampTz('occurred_at')->useCurrent();
            $table->uuid('actor_user_id')->nullable();
            $table->bigInteger('actor_workstation_id')->nullable();
            $table->string('before_status', 20)->nullable();
            $table->string('after_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('chemical_call_requests')->onDelete('cascade');
            $table->foreign('actor_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('actor_workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chemical_call_request_events');
        Schema::dropIfExists('chemical_call_requests');
        Schema::dropIfExists('machine_chemical_channels');
    }
};
