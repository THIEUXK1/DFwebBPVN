<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TroubleshootingKnowledgeBaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks for seeding
        if (DB::connection()->getDriverName() !== 'sqlite') { DB::statement('SET CONSTRAINTS ALL DEFERRED'); } else { DB::statement('PRAGMA defer_foreign_keys = ON'); }

        // 1. Seed Problems
        $problemsJson = file_get_contents(database_path('data/kb_problems.json'));
        $problems = json_decode($problemsJson, true);
        foreach ($problems as $p) {
            DB::table('problems')->updateOrInsert(
                ['id' => $p['ProblemID']],
                [
                    'problem_name' => $p['Problem'],
                    'severity' => $p['Severity'] ?? null,
                    'description' => $p['Description'] ?? null,
                    'is_active' => ($p['Active'] ?? 1) == 1
                ]
            );
        }

        // 2. Seed Processes
        $processesJson = file_get_contents(database_path('data/kb_processes.json'));
        $processes = json_decode($processesJson, true);
        foreach ($processes as $pr) {
            DB::table('processes')->updateOrInsert(
                ['id' => $pr['ProcessID']],
                [
                    'process_name' => $pr['Process']
                ]
            );
        }

        // 3. Seed Parameters
        $parametersJson = file_get_contents(database_path('data/kb_parameters.json'));
        $parameters = json_decode($parametersJson, true);
        foreach ($parameters as $param) {
            DB::table('parameters')->updateOrInsert(
                ['id' => $param['ParameterID']],
                [
                    'parameter_name' => $param['Parameter'],
                    'unit' => $param['Unit'] ?? null,
                    'lsl' => $param['LSL'] ?? null,
                    'usl' => $param['USL'] ?? null,
                    'lcl' => $param['LCL'] ?? null,
                    'ucl' => $param['UCL'] ?? null,
                    'score' => $param['SCORE'] ?? null,
                    'standard' => $param['Standard'] ?? null,
                    'is_active' => ($param['Active'] ?? 1) == 1
                ]
            );
        }

        // 4. Seed Causes
        $causesJson = file_get_contents(database_path('data/kb_causes.json'));
        $causes = json_decode($causesJson, true);
        foreach ($causes as $c) {
            DB::table('causes')->updateOrInsert(
                ['id' => $c['CauseID']],
                [
                    'cause_name' => $c['Cause'],
                    'category' => $c['Category'] ?? null,
                    
                    'temperature_rule' => $this->cleanRule($c['Temperature'] ?? null),
                    'steam_supply_pressure_rule' => $this->cleanRule($c['SteamSupplyPressure'] ?? null),
                    'air_pressure_rule' => $this->cleanRule($c['Air Pressure'] ?? null),
                    'speed_rule' => $this->cleanRule($c['Speed'] ?? null),
                    'dye_tank_dancer_rule' => $this->cleanRule($c['DyeTankDancer'] ?? null),
                    'out_steam_dancer_rule' => $this->cleanRule($c['OutSteamDancer'] ?? null),
                    'humidity_rule' => $this->cleanRule($c['Humidity'] ?? null),
                    'steam_dancer_rule' => $this->cleanRule($c['SteamDancer'] ?? null),
                    'washing_dancer_rule' => $this->cleanRule($c['WashingDancer'] ?? null),
                    'dryer_dancer_rule' => $this->cleanRule($c['DryerDancer'] ?? null),

                    'pr01' => ($c['PR01'] ?? 0) == 1,
                    'pr02' => ($c['PR02'] ?? 0) == 1,
                    'pr03' => ($c['PR03'] ?? 0) == 1,
                    'pr04' => ($c['PR04'] ?? 0) == 1,
                    'pr05' => ($c['PR05'] ?? 0) == 1,
                    'pr06' => ($c['PR06'] ?? 0) == 1,

                    'severity' => $c['Severity'] ?? null,
                    'verify_level' => $c['VerifyLevel'] ?? null,
                    'repair_priority' => $c['RepairPriority'] ?? null,
                    'is_active' => ($c['Active'] ?? 1) == 1
                ]
            );
        }

        // 5. Seed Rules
        $rulesJson = file_get_contents(database_path('data/kb_problem_causes.json'));
        $rules = json_decode($rulesJson, true);
        foreach ($rules as $r) {
            DB::table('problem_cause_rules')->updateOrInsert(
                [
                    'problem_id' => $r['ProblemID'],
                    'cause_id' => $r['CauseID']
                ],
                [
                    'cause_score' => $r['CauseScore']
                ]
            );
        }
    }

    private function cleanRule($val): ?string
    {
        if ($val === null || $val === '' || $val === 0 || $val === '0') {
            return null;
        }
        return (string)$val;
    }
}
