<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();



        $this->call(AdminUserSeeder::class);
        $this->call(MachinesAndTanksSeeder::class);
        $this->call(AlertRulesSeeder::class);
        $this->call(WorkstationsSeeder::class);
        $this->call(RecipesSeeder::class);
        $this->call(TroubleshootingKnowledgeBaseSeeder::class);
        $this->call(FoundationSeeder::class);
    }
}
