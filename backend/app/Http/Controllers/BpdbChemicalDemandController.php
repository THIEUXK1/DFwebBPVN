<?php
// backend/app/Http/Controllers/BpdbChemicalDemandController.php
//
// "NHU CẦU BƠM HÓA CHẤT" — chỉ đọc, xem BpdbChemicalDemandService. Đặt dưới prefix
// /admin/bpdb (không phải /api/bpdb như tài liệu gợi ý) để nhất quán với các route BPDB
// khác đã có — cùng lý do đã ghi trong BpdbMachineController.

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Services\ColorService\BpdbChemicalDemandService;
use Illuminate\Support\Carbon;

class BpdbChemicalDemandController extends Controller
{
    public function index(BpdbChemicalDemandService $service)
    {
        $data = $service->getDemandList();
        return response()->json(array_merge(
            [
                'active' => $data['active'],
                'completedRecent' => $data['completedRecent'],
                'errorOrCancelled' => $data['errorOrCancelled'],
                'bpdbConnected' => $data['bpdbConnected'],
            ],
            $this->envelope($data['fetchedAt'])
        ));
    }

    public function summary(BpdbChemicalDemandService $service)
    {
        $summary = $service->getSummary();
        return response()->json(array_merge($summary, $this->envelope($summary['fetchedAt'])));
    }

    public function show(string $taskId, BpdbChemicalDemandService $service)
    {
        $detail = $service->getTaskDetail($taskId);
        if (!$detail) {
            return response()->json(['error' => 'TASK_NOT_FOUND'], 404);
        }
        return response()->json(array_merge(['data' => $detail], $this->envelope(now()->toIso8601String())));
    }

    private function envelope(string $lastSyncedAtIso): array
    {
        $lastSyncedAt = Carbon::parse($lastSyncedAtIso);
        $ageSeconds = now()->diffInSeconds($lastSyncedAt, true);

        $staleFlag = FeatureFlag::where('key', 'bpdb_stale_threshold_seconds')->first();
        $staleThreshold = $staleFlag && is_numeric($staleFlag->value) ? (int) $staleFlag->value : 60;

        return [
            'lastSyncedAt' => $lastSyncedAt->toIso8601String(),
            'dataAgeSeconds' => $ageSeconds,
            'source' => 'BPDB',
            'readOnly' => true,
            'stale' => $ageSeconds > $staleThreshold,
            'staleThresholdSeconds' => $staleThreshold,
        ];
    }
}
