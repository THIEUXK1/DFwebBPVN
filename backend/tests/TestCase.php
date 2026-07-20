<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabaseState;

abstract class TestCase extends BaseTestCase
{
    protected static $schemaCleaned = false;

    protected function setUp(): void
    {
        if (!$this->app) {
            $this->refreshApplication();
        }

        if (!static::$schemaCleaned) {
            if (config('database.default') === 'pgsql') {
                try {
                    // Drop and recreate app and public schemas
                    DB::statement('DROP SCHEMA IF EXISTS app CASCADE');
                    DB::statement('CREATE SCHEMA app');
                    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
                    DB::statement('CREATE SCHEMA public');
                    
                    // Run the base SQL script to create core target tables
                    $sqlPath = 'f:/DF/sql_migration/02_target_normalized_schema_postgresql.sql';
                    if (file_exists($sqlPath)) {
                        $sql = file_get_contents($sqlPath);
                        DB::unprepared($sql);
                    }
                    
                    // Run migrations
                    Artisan::call('migrate', ['--force' => true]);
                    
                    // Run seeders
                    Artisan::call('db:seed', ['--force' => true]);

                    // Mock workstations are seeded automatically by WorkstationsSeeder in test environment.
                    
                    // Prevent RefreshDatabase trait from wiping the database again
                    RefreshDatabaseState::$migrated = true;
                    
                    static::$schemaCleaned = true;
                } catch (\Exception $e) {
                    throw $e;
                }
            }
        }

        $this->setUpTraits();
    }
}
