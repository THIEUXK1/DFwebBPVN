<?php

namespace App\Services;

use App\Models\Problem;
use App\Models\Cause;
use App\Models\ProblemCauseRule;
use App\Models\Process;
use App\Models\Parameter;

class InferenceService
{
    /**
     * Run diagnosis based on symptoms, process stage, and physical/environmental parameters.
     *
     * @param array $problemIds List of selected problem IDs
     * @param string $processId Selected process ID (e.g. 'PR03')
     * @param array $paramInputs Parameter values: ['Temperature' => ['actual' => 110, 'set' => 105], 'Humidity' => 'HIGH']
     * @return array Ranked recommendations
     */
    public function calculate(array $problemIds, string $processId, array $paramInputs): array
    {
        $dic = [];
        $breakdown = [];

        // 1. Load Problem Scores from Rules
        $rules = ProblemCauseRule::whereIn('problem_id', $problemIds)->get();
        foreach ($rules as $rule) {
            $cid = $rule->cause_id;
            if (!isset($dic[$cid])) {
                $dic[$cid] = 0;
                $breakdown[$cid] = [
                    'problem_score' => 0,
                    'process_score' => 0,
                    'parameter_score' => 0,
                    'details' => []
                ];
            }
            $dic[$cid] += $rule->cause_score;
            $breakdown[$cid]['problem_score'] += $rule->cause_score;
            $breakdown[$cid]['details'][] = "Khớp vấn đề {$rule->problem_id} (+{$rule->cause_score} điểm)";
        }

        // Only process causes that match the selected problems
        if (empty($dic)) {
            return [];
        }

        $activeCauseIds = array_keys($dic);
        $causes = Cause::whereIn('id', $activeCauseIds)->get()->keyBy('id');

        // 2. Apply Process Score
        // Community stages sequence: PR01, PR02, PR03, PR04, PR05, PR06
        $stages = ['PR01', 'PR02', 'PR03', 'PR04', 'PR05', 'PR06'];
        $selectedStageIdx = array_search($processId, $stages);
        if ($selectedStageIdx !== false) {
            $applicableStages = array_slice($stages, 0, $selectedStageIdx + 1);
            
            foreach ($activeCauseIds as $cid) {
                $cause = $causes[$cid] ?? null;
                if (!$cause) continue;

                $procScore = 0;
                foreach ($applicableStages as $stage) {
                    $col = strtolower($stage);
                    if ($cause->$col) {
                        $procScore += 2; // PROCESS_SCORE is 2 in VBA
                        $breakdown[$cid]['details'][] = "Có khả năng xảy ra tại công đoạn {$stage} (+2 điểm)";
                    }
                }
                $dic[$cid] += $procScore;
                $breakdown[$cid]['process_score'] += $procScore;
            }
        }

        // 3. Apply Parameter Score
        // Load parameters spec to check limits
        $paramSpecs = Parameter::where('is_active', true)->get()->keyBy('parameter_name');

        // Numeric mappings
        $numericMappings = [
            'Temperature' => 'temperature_rule',
            'Steam' => 'steam_supply_pressure_rule',
            'Air Pressure' => 'air_pressure_rule',
            'Speed' => 'speed_rule',
            'Dye Tank Dancer' => 'dye_tank_dancer_rule',
            'Steam Dancer' => 'out_steam_dancer_rule'
        ];

        // Categorical mappings
        $categoricalMappings = [
            'Humidity' => ['field' => 'humidity_rule', 'score' => 1.0],
            'Steam' => ['field' => 'steam_dancer_rule', 'score' => 1.0], // Combobox steam maps to SteamDancer in VBA
            'Washing' => ['field' => 'washing_dancer_rule', 'score' => 1.0],
            'Dryer' => ['field' => 'dryer_dancer_rule', 'score' => 1.0]
        ];

        foreach ($activeCauseIds as $cid) {
            $cause = $causes[$cid] ?? null;
            if (!$cause) continue;

            // Check numeric parameters
            foreach ($numericMappings as $paramName => $ruleField) {
                if (isset($paramInputs[$paramName]) && is_array($paramInputs[$paramName])) {
                    $actual = $paramInputs[$paramName]['actual'] ?? null;
                    $setpoint = $paramInputs[$paramName]['set'] ?? null;

                    if ($actual !== null && $setpoint !== null && is_numeric($actual) && is_numeric($setpoint)) {
                        $spec = $paramSpecs[$paramName] ?? null;
                        
                        // Default spec values if not found in parameters table
                        $lsl = $spec ? $spec->lsl : 0.0;
                        $usl = $spec ? $spec->usl : 0.0;
                        $weight = $spec ? $spec->score : 0.0;

                        $lslLimit = $setpoint - $lsl;
                        $uslLimit = $setpoint + $usl;

                        $status = 'NORMAL';
                        if ($actual < $lslLimit) {
                            $status = 'LOW';
                        } elseif ($actual > $uslLimit) {
                            $status = 'HIGH';
                        }

                        $ruleVal = $cause->$ruleField;
                        $pScore = $this->getParameterScore($status, $ruleVal, $weight);
                        
                        if ($pScore > 0) {
                            $dic[$cid] += $pScore;
                            $breakdown[$cid]['parameter_score'] += $pScore;
                            $breakdown[$cid]['details'][] = "Tham số {$paramName} lệch ({$status}) trùng với luật ({$ruleVal}) (+{$pScore} điểm)";
                        }
                    }
                }
            }

            // Check categorical parameters
            foreach ($categoricalMappings as $paramName => $config) {
                if (isset($paramInputs[$paramName]) && is_string($paramInputs[$paramName])) {
                    $value = $paramInputs[$paramName];
                    $ruleField = $config['field'];
                    $weight = $config['score'];

                    $ruleVal = $cause->$ruleField;
                    $pScore = $this->getParameterScore($value, $ruleVal, $weight);

                    if ($pScore > 0) {
                        $dic[$cid] += $pScore;
                        $breakdown[$cid]['parameter_score'] += $pScore;
                        $breakdown[$cid]['details'][] = "Thông số {$paramName} ({$value}) trùng khớp với luật ({$ruleVal}) (+{$pScore} điểm)";
                    }
                }
            }
        }

        // 4. Rank and Sort causes
        arsort($dic);

        $results = [];
        $rank = 1;
        foreach ($dic as $cid => $totalScore) {
            $cause = $causes[$cid] ?? null;
            if (!$cause) continue;

            $results[] = [
                'cause_id' => $cid,
                'cause_name' => $cause->cause_name,
                'category' => $cause->category,
                'severity' => $cause->severity,
                'verify_level' => $cause->verify_level,
                'repair_priority' => $cause->repair_priority,
                'score' => $totalScore,
                'rank' => $rank++,
                'breakdown' => $breakdown[$cid]
            ];
        }

        return $results;
    }

    /**
     * Check if parameter status matches the rule.
     */
    private function getParameterScore(string $status, ?string $kbValue, float $paramScore): float
    {
        if ($kbValue === null || $kbValue === '' || $kbValue === '0') {
            return 0.0;
        }

        $status = strtoupper(trim($status));
        $kbValue = strtoupper(trim($kbValue));

        switch ($kbValue) {
            case 'HIGH':
                return ($status === 'HIGH') ? $paramScore : 0.0;
            case 'LOW':
                return ($status === 'LOW') ? $paramScore : 0.0;
            case 'HIGH/LOW':
                return ($status !== 'NORMAL') ? $paramScore : 0.0;
            default:
                return 0.0;
        }
    }
}
