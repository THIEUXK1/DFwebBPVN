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
        // 1. app.problems
        Schema::create('problems', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('problem_name', 255);
            $table->string('severity', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
        });

        // 2. app.causes
        Schema::create('causes', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('cause_name', 255);
            $table->string('category', 100)->nullable();
            
            // Physical parameter rules
            $table->string('temperature_rule', 20)->nullable();
            $table->string('steam_supply_pressure_rule', 20)->nullable();
            $table->string('air_pressure_rule', 20)->nullable();
            $table->string('speed_rule', 20)->nullable();
            $table->string('dye_tank_dancer_rule', 20)->nullable();
            $table->string('out_steam_dancer_rule', 20)->nullable();
            $table->string('humidity_rule', 20)->nullable();
            $table->string('steam_dancer_rule', 20)->nullable();
            $table->string('washing_dancer_rule', 20)->nullable();
            $table->string('dryer_dancer_rule', 20)->nullable();

            // Process stage active flags
            $table->boolean('pr01')->default(false);
            $table->boolean('pr02')->default(false);
            $table->boolean('pr03')->default(false);
            $table->boolean('pr04')->default(false);
            $table->boolean('pr05')->default(false);
            $table->boolean('pr06')->default(false);

            $table->string('severity', 50)->nullable();
            $table->string('verify_level', 50)->nullable();
            $table->string('repair_priority', 50)->nullable();
            $table->boolean('is_active')->default(true);
        });

        // 3. app.problem_cause_rules
        Schema::create('problem_cause_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('problem_id', 50);
            $table->string('cause_id', 50);
            $table->double('cause_score');

            $table->foreign('problem_id')->references('id')->on('problems')->onDelete('cascade');
            $table->foreign('cause_id')->references('id')->on('causes')->onDelete('cascade');
        });

        // 4. app.processes
        Schema::create('processes', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('process_name', 255);
        });

        // 5. app.parameters
        Schema::create('parameters', function (Blueprint $table) {
            $table->string('id', 50)->primary();
            $table->string('parameter_name', 255);
            $table->string('unit', 20)->nullable();
            $table->double('lsl')->nullable();
            $table->double('usl')->nullable();
            $table->double('lcl')->nullable();
            $table->double('ucl')->nullable();
            $table->double('score')->nullable();
            $table->double('standard')->nullable();
            $table->boolean('is_active')->default(true);
        });

        // 6. app.troubleshooting_cases
        Schema::create('troubleshooting_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('batch_id')->nullable();
            $table->string('stage_id', 50)->nullable();
            $table->string('status', 50)->default('OPEN'); // 'OPEN', 'ANALYZING', 'RECTIFYING', 'CLOSED'
            $table->uuid('reporter_id')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('actual_cause_id', 50)->nullable();
            $table->text('resolution_notes')->nullable();
            $table->integer('effectiveness_rating')->nullable(); // 1 to 5 stars

            $table->foreign('batch_id')->references('id')->on('production_batches')->onDelete('set null');
            $table->foreign('stage_id')->references('id')->on('processes')->onDelete('set null');
            $table->foreign('reporter_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('actual_cause_id')->references('id')->on('causes')->onDelete('set null');
        });

        // 7. app.case_problems
        Schema::create('case_problems', function (Blueprint $table) {
            $table->uuid('case_id');
            $table->string('problem_id', 50);
            $table->primary(['case_id', 'problem_id']);

            $table->foreign('case_id')->references('id')->on('troubleshooting_cases')->onDelete('cascade');
            $table->foreign('problem_id')->references('id')->on('problems')->onDelete('cascade');
        });

        // 8. app.case_evidences
        Schema::create('case_evidences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->string('parameter_id', 50);
            $table->double('actual_value')->nullable();
            $table->double('setpoint_value')->nullable();
            $table->string('status', 20)->nullable(); // 'LOW', 'HIGH', 'NORMAL'

            $table->foreign('case_id')->references('id')->on('troubleshooting_cases')->onDelete('cascade');
            $table->foreign('parameter_id')->references('id')->on('parameters')->onDelete('cascade');
        });

        // 9. app.case_recommendations
        Schema::create('case_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('case_id');
            $table->string('cause_id', 50);
            $table->double('score');
            $table->integer('rank');
            $table->text('recommendation_text')->nullable();

            $table->foreign('case_id')->references('id')->on('troubleshooting_cases')->onDelete('cascade');
            $table->foreign('cause_id')->references('id')->on('causes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_recommendations');
        Schema::dropIfExists('case_evidences');
        Schema::dropIfExists('case_problems');
        Schema::dropIfExists('troubleshooting_cases');
        Schema::dropIfExists('parameters');
        Schema::dropIfExists('processes');
        Schema::dropIfExists('problem_cause_rules');
        Schema::dropIfExists('causes');
        Schema::dropIfExists('problems');
    }
};
