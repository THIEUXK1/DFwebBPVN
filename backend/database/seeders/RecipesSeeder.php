<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use App\Models\RecipeMaterial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecipesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed referenced materials first
        $materials = [
            ['code' => 'R2011A', 'name' => 'Red Active Dye R2011A', 'type' => 'DYE'],
            ['code' => 'Y1008A', 'name' => 'Yellow Dye Y1008A', 'type' => 'DYE'],
            ['code' => 'VN62', 'name' => 'Chemical VN62', 'type' => 'CHEMICAL'],
            ['code' => 'Y1005G', 'name' => 'Yellow Dye Y1005G', 'type' => 'DYE'],
            ['code' => 'AC77', 'name' => 'Chemical AC77', 'type' => 'CHEMICAL'],
            ['code' => 'Y1115', 'name' => 'Yellow Dye Y1115', 'type' => 'DYE'],
            ['code' => 'AC78', 'name' => 'Chemical AC78', 'type' => 'CHEMICAL'],
        ];

        foreach ($materials as $m) {
            DB::table('materials')->updateOrInsert(['code' => $m['code']], $m);
        }

        // Clean tables
        RecipeMaterial::query()->delete();
        RecipeVersion::query()->delete();
        Recipe::query()->delete();

        // 1. Recipe 1: RED-ACTIVE
        $r1Id = (string) Str::uuid();
        $rv1Id = (string) Str::uuid();
        
        Recipe::create([
            'id' => $r1Id,
            'color_code' => 'RED-ACTIVE',
            'product_code' => 'COTTON-90',
            'description' => 'Công thức nhuộm đỏ hoạt tính cho vải Cotton',
        ]);
        RecipeVersion::create([
            'id' => $rv1Id,
            'recipe_id' => $r1Id,
            'version' => 1,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv1Id,
            'material_code' => 'R2011A',
            'concentration' => 2.10,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 100.00
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv1Id,
            'material_code' => 'Y1008A',
            'concentration' => 0.50,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 100.00
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv1Id,
            'material_code' => 'VN62',
            'concentration' => 1.50,
            'process_code' => 'S',
            'tank_number' => 2,
            'temperature' => 60.00
        ]);

        // 2. Recipe 2: YELLOW-M2GL
        $r2Id = (string) Str::uuid();
        $rv2Id = (string) Str::uuid();
        
        Recipe::create([
            'id' => $r2Id,
            'color_code' => 'YELLOW-M2GL',
            'product_code' => 'POLY-80',
            'description' => 'Công thức nhuộm vàng Polyester',
        ]);
        RecipeVersion::create([
            'id' => $rv2Id,
            'recipe_id' => $r2Id,
            'version' => 1,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv2Id,
            'material_code' => 'Y1005G',
            'concentration' => 1.80,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 98.00
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv2Id,
            'material_code' => 'AC77',
            'concentration' => 2.00,
            'process_code' => 'S',
            'tank_number' => 2,
            'temperature' => 60.00
        ]);

        // 3. Recipe 3: ORANGE-XLF
        $r3Id = (string) Str::uuid();
        $rv3Id = (string) Str::uuid();
        
        Recipe::create([
            'id' => $r3Id,
            'color_code' => 'ORANGE-XLF',
            'product_code' => 'NYLON-70',
            'description' => 'Công thức nhuộm cam Nylon XLF',
        ]);
        RecipeVersion::create([
            'id' => $rv3Id,
            'recipe_id' => $r3Id,
            'version' => 1,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv3Id,
            'material_code' => 'Y1115',
            'concentration' => 3.20,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 102.00
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv3Id,
            'material_code' => 'R2011A',
            'concentration' => 0.80,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 102.00
        ]);

        // 4. Recipe 4: BLACK-DIRECT
        $r4Id = (string) Str::uuid();
        $rv4Id = (string) Str::uuid();
        
        Recipe::create([
            'id' => $r4Id,
            'color_code' => 'BLACK-DIRECT',
            'product_code' => 'COTTON-100',
            'description' => 'Công thức nhuộm đen trực tiếp',
        ]);
        RecipeVersion::create([
            'id' => $rv4Id,
            'recipe_id' => $r4Id,
            'version' => 1,
            'status' => 'ACTIVE',
            'approved_at' => now(),
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv4Id,
            'material_code' => 'Y1008A',
            'concentration' => 1.20,
            'process_code' => 'P',
            'tank_number' => 1,
            'temperature' => 95.00
        ]);
        RecipeMaterial::create([
            'recipe_version_id' => $rv4Id,
            'material_code' => 'AC78',
            'concentration' => 4.50,
            'process_code' => 'S',
            'tank_number' => 2,
            'temperature' => 60.00
        ]);

        $this->command->info('Successfully seeded 4 production recipes with versions & materials.');
    }
}
