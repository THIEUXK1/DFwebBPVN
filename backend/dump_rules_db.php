<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
$rules = DB::table('app.problem_cause_rules')->get();
foreach ($rules as $r) {
    echo "ID: {$r->id}, P: {$r->problem_id}, C: {$r->cause_id}, Score: {$r->cause_score}\n";
}
