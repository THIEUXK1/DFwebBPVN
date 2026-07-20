<?php

namespace App\Http\Controllers;

use App\Models\Problem;
use App\Models\Process;
use App\Models\Parameter;
use App\Models\TroubleshootingCase;
use App\Models\CaseEvidence;
use App\Models\CaseRecommendation;
use App\Services\InferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TroubleshootingController extends Controller
{
    private InferenceService $inferenceService;

    public function __construct(InferenceService $inferenceService)
    {
        $this->inferenceService = $inferenceService;
    }

    public function indexProblems()
    {
        $problems = Problem::where('is_active', true)->orderBy('id')->get();
        return response()->json(['status' => 'SUCCESS', 'data' => $problems]);
    }

    public function indexProcesses()
    {
        $processes = Process::orderBy('id')->get();
        return response()->json(['status' => 'SUCCESS', 'data' => $processes]);
    }

    public function indexParameters()
    {
        $parameters = Parameter::where('is_active', true)->orderBy('id')->get();
        return response()->json(['status' => 'SUCCESS', 'data' => $parameters]);
    }

    public function indexCases()
    {
        $cases = TroubleshootingCase::with(['batch', 'stage', 'reporter', 'actualCause'])
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['status' => 'SUCCESS', 'data' => $cases]);
    }

    public function showCase($id)
    {
        $case = TroubleshootingCase::with(['batch', 'stage', 'reporter', 'actualCause', 'problems', 'evidences.parameter', 'recommendations.cause'])
            ->findOrFail($id);
        return response()->json(['status' => 'SUCCESS', 'data' => $case]);
    }

    public function diagnose(Request $request)
    {
        $request->validate([
            'batch_id' => 'nullable|uuid',
            'stage_id' => 'required|string|exists:processes,id',
            'problems' => 'required|array|min:1',
            'problems.*' => 'string|exists:problems,id',
            'parameters' => 'required|array'
        ]);

        $batchId = $request->input('batch_id');
        $stageId = $request->input('stage_id');
        $problems = $request->input('problems');
        $paramInputs = $request->input('parameters');

        // Run diagnosis
        $recommendations = $this->inferenceService->calculate($problems, $stageId, $paramInputs);

        // Save diagnosis case in transaction
        $case = DB::transaction(function () use ($batchId, $stageId, $problems, $paramInputs, $recommendations) {
            $case = TroubleshootingCase::create([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'batch_id' => $batchId,
                'stage_id' => $stageId,
                'status' => 'OPEN',
                'reporter_id' => Auth::id(),
                'created_at' => Carbon::now()
            ]);

            // Save selected problems
            $case->problems()->sync($problems);

            // Save parameter evidences
            $paramSpecs = Parameter::where('is_active', true)->get()->keyBy('parameter_name');
            foreach ($paramInputs as $paramName => $val) {
                $spec = $paramSpecs[$paramName] ?? null;
                if (!$spec) continue;

                $actual = null;
                $setpoint = null;
                $status = 'NORMAL';

                if (is_array($val)) {
                    $actual = $val['actual'] ?? null;
                    $setpoint = $val['set'] ?? null;

                    if ($actual !== null && $setpoint !== null && is_numeric($actual) && is_numeric($setpoint)) {
                        $lslLimit = $setpoint - $spec->lsl;
                        $uslLimit = $setpoint + $spec->usl;
                        if ($actual < $lslLimit) {
                            $status = 'LOW';
                        } elseif ($actual > $uslLimit) {
                            $status = 'HIGH';
                        }
                    }
                } else if (is_string($val)) {
                    $status = $val; // Categorical parameters directly hold status
                }

                CaseEvidence::create([
                    'case_id' => $case->id,
                    'parameter_id' => $spec->id,
                    'actual_value' => $actual,
                    'setpoint_value' => $setpoint,
                    'status' => $status
                ]);
            }

            // Save top 10 recommendations
            $topRecs = array_slice($recommendations, 0, 10);
            foreach ($topRecs as $rec) {
                CaseRecommendation::create([
                    'case_id' => $case->id,
                    'cause_id' => $rec['cause_id'],
                    'score' => $rec['score'],
                    'rank' => $rec['rank'],
                    'recommendation_text' => "Độ ưu tiên sửa chữa: {$rec['repair_priority']}. Mức xác thực: {$rec['verify_level']}."
                ]);
            }

            return $case;
        });

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'case_id' => $case->id,
                'recommendations' => $recommendations
            ]
        ], 201);
    }

    public function resolveCase(Request $request, $id)
    {
        $request->validate([
            'actual_cause_id' => 'required|string|exists:causes,id',
            'resolution_notes' => 'nullable|string',
            'effectiveness_rating' => 'required|integer|min:1|max:5'
        ]);

        $case = TroubleshootingCase::findOrFail($id);
        $case->status = 'CLOSED';
        $case->actual_cause_id = $request->input('actual_cause_id');
        $case->resolution_notes = $request->input('resolution_notes');
        $case->effectiveness_rating = $request->input('effectiveness_rating');
        $case->resolved_at = Carbon::now();
        $case->save();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $case->load(['actualCause'])
        ]);
    }
}
