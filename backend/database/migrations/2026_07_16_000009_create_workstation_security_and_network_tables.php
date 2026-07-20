<?php

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
        // 1. Update app.workstations with missing fields
        Schema::table('workstations', function (Blueprint $table) {
            $table->string('workstation_type', 50)->nullable();
            $table->string('default_route', 100)->nullable();
            $table->string('hostname', 100)->nullable();
            $table->string('registered_ip', 45)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('registration_token_hash', 64)->nullable();
            $table->boolean('locked_to_type')->default(false);
            $table->string('version', 20)->default('1.0.0');
        });

        // Copy existing data from type/default_screen to workstation_type/default_route if any
        DB::statement('UPDATE workstations SET workstation_type = type, default_route = default_screen');

        // 2. Create app.workstation_allowed_actions
        Schema::create('workstation_allowed_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->string('action_code', 100);
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('cascade');
            $table->unique(['workstation_id', 'action_code']);
        });

        // 3. Create app.workstation_role_assignments
        Schema::create('workstation_role_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->bigInteger('role_id');
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->unique(['workstation_id', 'role_id']);
        });

        // 4. Create app.workstation_sessions
        Schema::create('workstation_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->uuid('user_id')->nullable();
            $table->timestampTz('login_at')->useCurrent();
            $table->timestampTz('logout_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('session_status', 30)->default('ACTIVE'); // ACTIVE, LOGGED_OUT, EXPIRED
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // 5. Create app.workstation_network_history
        Schema::create('workstation_network_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->string('ip_address', 45);
            $table->string('hostname', 100)->nullable();
            $table->string('network_segment', 100)->nullable();
            $table->timestampTz('first_seen_at')->useCurrent();
            $table->timestampTz('last_seen_at')->useCurrent();
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('cascade');
        });

        // 6. Create app.device_assignments
        Schema::create('device_assignments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('workstation_id');
            $table->string('device_id', 100);
            $table->string('assignment_type', 50); // SCALE, PRINTER, SCANNER, TANK_PLC
            $table->boolean('active')->default(true);
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestamps();

            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('cascade');
            $table->unique(['workstation_id', 'device_id', 'assignment_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_assignments');
        Schema::dropIfExists('workstation_network_history');
        Schema::dropIfExists('workstation_sessions');
        Schema::dropIfExists('workstation_role_assignments');
        Schema::dropIfExists('workstation_allowed_actions');

        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn([
                'workstation_type',
                'default_route',
                'hostname',
                'registered_ip',
                'last_ip',
                'registration_token_hash',
                'locked_to_type',
                'version'
            ]);
        });
    }
};
