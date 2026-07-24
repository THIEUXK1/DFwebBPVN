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
        // 1. app.materials
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('name', 200);
            $table->string('type', 20); // 'DYE', 'CHEMICAL', 'ADDITIVE'
            $table->string('supplier', 150)->nullable();
            $table->decimal('stock_qty', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. app.water_configs
        Schema::create('water_configs', function (Blueprint $table) {
            $table->id();
            $table->string('machine_line', 50);
            $table->string('process_code', 20);
            $table->decimal('ratio_coefficient', 10, 4);
            $table->decimal('liquor_ratio', 10, 2);
            $table->timestamps();
            $table->unique(['machine_line', 'process_code']);
        });

        // 3. app.recipes
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('color_code', 100);
            $table->string('product_code', 100);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['color_code', 'product_code']);
        });

        // 4. app.recipe_versions
        Schema::create('recipe_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('recipe_id');
            $table->integer('version')->default(1);
            $table->string('status', 30)->default('ACTIVE'); // simplified status, no workflow needed
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->unique(['recipe_id', 'version']);
        });

        // 5. app.recipe_materials
        Schema::create('recipe_materials', function (Blueprint $table) {
            $table->id();
            $table->uuid('recipe_version_id');
            $table->string('material_code', 100);
            $table->decimal('concentration', 18, 6);
            $table->string('process_code', 20);
            $table->smallInteger('tank_number')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('recipe_version_id')->references('id')->on('recipe_versions')->onDelete('cascade');
            $table->foreign('material_code')->references('code')->on('materials');
        });

        // 6. app.process_parameters
        Schema::create('process_parameters', function (Blueprint $table) {
            $table->id();
            $table->uuid('recipe_version_id');
            $table->decimal('tension_value', 10, 4)->nullable();
            $table->decimal('water_ratio_override', 10, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('recipe_version_id')->references('id')->on('recipe_versions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_parameters');
        Schema::dropIfExists('recipe_materials');
        Schema::dropIfExists('recipe_versions');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('water_configs');
        Schema::dropIfExists('materials');
    }
};
