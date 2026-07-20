<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WS-001: extends app.workstations with the fields needed to drive a single-purpose kiosk UI
 * (allowed_actions for nav/action filtering, default_screen for auto-routing on login), and binds
 * app.users to a workstation so a station account's công đoạn is known at login time instead of
 * being picked per-browser via localStorage. Per user decision: accounts are created per công đoạn
 * (station-scoped logins) and Admin is responsible for assigning them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workstations', function (Blueprint $table) {
            $table->json('allowed_actions')->nullable();
            $table->string('default_screen', 100)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('workstation_id')->nullable();
            $table->foreign('workstation_id')->references('id')->on('workstations')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['workstation_id']);
            $table->dropColumn('workstation_id');
        });

        Schema::table('workstations', function (Blueprint $table) {
            $table->dropColumn(['allowed_actions', 'default_screen']);
        });
    }
};
