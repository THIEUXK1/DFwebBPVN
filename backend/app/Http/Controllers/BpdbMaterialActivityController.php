<?php
// backend/app/Http/Controllers/BpdbMaterialActivityController.php
//
// "THỐNG KÊ HOẠT ĐỘNG NGUYÊN LIỆU THEO MÁY NHUỘM" — chỉ đọc, xem
// BpdbMaterialActivityService cho lý do đổi hướng từ "tiêu hao theo khối lượng" sang
// "hoạt động theo số lượng task" (2026-07-20).

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Services\ColorService\BpdbMaterialActivityService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class BpdbMaterialActivityController extends Controller
{
    public function summary(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getSummary($from, $to, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function byMachine(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getByMachine($from, $to);
        return response()->json($this->envelope($data, $from, $to));
    }

    public function byMaterial(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getByMaterial($from, $to, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function timeseries(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $granularity = $request->input('granularity', 'day');
        $data = $service->getTimeseries($from, $to, $granularity, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function detail(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getDetail(
            $from,
            $to,
            $request->input('machine_code'),
            $request->input('material_code'),
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 50)
        );
        return response()->json($this->envelope($data, $from, $to));
    }

    /** Xuất Excel — dùng lại đúng dữ liệu drill-down (mục 9 UI, "Xuất Excel/CSV"), giới hạn 5000 dòng/lần tránh treo BPDB. */
    public function exportDetail(Request $request, BpdbMaterialActivityService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getDetail(
            $from,
            $to,
            $request->input('machine_code'),
            $request->input('material_code'),
            1,
            5000
        );

        $rows = [];
        foreach ($data['rows'] as $t) {
            $lines = $t['materialLines'] ?: [['materialCode' => null, 'materialName' => null, 'unit' => null, 'requestedAmount' => null, 'actualAmount' => null, 'quantityDataAvailable' => false]];
            foreach ($lines as $l) {
                $rows[] = [
                    $t['taskId'],
                    $t['machineCode'],
                    $t['taskTitle'],
                    $t['finishTime'],
                    $l['materialCode'],
                    $l['materialName'],
                    $l['unit'],
                    $l['quantityDataAvailable'] ? $l['requestedAmount'] : null,
                    $l['quantityDataAvailable'] ? $l['actualAmount'] : null,
                ];
            }
        }

        $headings = [
            'Task ID', 'Máy', 'Task Title', 'Thời gian kết thúc',
            'Mã nguyên liệu (nhóm)', 'Tên nguyên liệu', 'Đơn vị',
            'Lượng yêu cầu (chỉ có nếu ghi nhận)', 'Lượng thực tế (chỉ có nếu ghi nhận)',
        ];

        return Excel::download(
            new ArrayExport($headings, $rows),
            'hoat-dong-nguyen-lieu-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx'
        );
    }

    private function resolveRange(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), 'Asia/Ho_Chi_Minh')->endOfDay()
            : Carbon::now('Asia/Ho_Chi_Minh')->endOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), 'Asia/Ho_Chi_Minh')->startOfDay()
            : (clone $to)->subDays(30)->startOfDay();

        return [$from, $to];
    }

    private function envelope(array $data, Carbon $from, Carbon $to): array
    {
        return array_merge($data, [
            'rangeFrom' => $from->toIso8601String(),
            'rangeTo' => $to->toIso8601String(),
            'source' => 'BPDB',
            'readOnly' => true,
            'metricBasis' => 'TASK_COUNT',
        ]);
    }
}
