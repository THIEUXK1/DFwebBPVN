<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$log = "";

try {
    $log .= "1. Dropping and recreating app and public schemas...\n";
    DB::statement('DROP SCHEMA IF EXISTS app CASCADE');
    DB::statement('CREATE SCHEMA app');
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');

    $log .= "2. Loading 02_target_normalized_schema_postgresql.sql...\n";
    $sqlPath = 'f:/DF/sql_migration/02_target_normalized_schema_postgresql.sql';
    if (file_exists($sqlPath)) {
        $sql = file_get_contents($sqlPath);
        DB::unprepared($sql);
        $log .= "   Loaded successfully.\n";
    } else {
        $log .= "   File not found!\n";
    }

    $log .= "3. Running migrations...\n";
    $exitCode = Artisan::call('migrate', ['--force' => true]);
    $log .= "   Exit Code: $exitCode\n";
    $log .= "   Migrations output:\n" . Artisan::output() . "\n";

    $log .= "4. Running seeders...\n";
    $exitCode = Artisan::call('db:seed', ['--force' => true]);
    $log .= "   Exit Code: $exitCode\n";
    $log .= "   Seeders output:\n" . Artisan::output() . "\n";

    $log .= "Success!\n";
} catch (\Exception $e) {
    $log .= "ERROR EXCEPTION: " . get_class($e) . ": " . $e->getMessage() . "\n";
    $log .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

file_put_contents(__DIR__ . '/scratch_out.txt', $log);
echo "Logged to scratch/scratch_out.txt\n";
