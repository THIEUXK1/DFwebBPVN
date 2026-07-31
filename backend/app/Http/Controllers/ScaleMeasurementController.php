<?php

namespace App\Http\Controllers;

use App\Models\ScaleMeasurement;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScaleMeasurementController extends Controller
{
    /**
     * List completed scale measurements.
     */
    public function index(Request $request)
    {
        $query = ScaleMeasurement::query();

        if ($request->has('batch_id')) {
            $query->where('legacy_batch_id', $request->input('batch_id'));
        }

        if ($request->has('material_code')) {
            $query->where('dye_code', $request->input('material_code'));
        }

        $measurements = $query->orderBy('measured_at', 'desc')->get();
        return response()->json($measurements);
    }

    /**
     * Store a completed scale measurement from Weighing Station.
     */
    public function store(Request $request)
    {
        $request->validate([
            'legacy_batch_id' => 'required|string|max:100',
            'color' => 'required|string|max:100',
            'product_code' => 'required|string|max:100',
            'machine_code' => 'required|string|max:50',
            'dye_code' => 'required|string|max:100',
            'weight' => 'required|numeric',
            'process_code' => 'required|string|max:20',
            'material_type' => 'required|string|in:DYE,CHEMICAL',
        ]);

        $measurement = ScaleMeasurement::create([
            'legacy_source' => $request->input('legacy_source', 'WEB_WEIGH'),
            'legacy_id' => $request->input('legacy_id', time() + rand(1, 100000)),
            'legacy_batch_id' => $request->input('legacy_batch_id'),
            'color' => $request->input('color'),
            'product_code' => $request->input('product_code'),
            'machine_code' => $request->input('machine_code'),
            'level_code' => $request->input('level_code'),
            'rack_code' => $request->input('rack_code'),
            'dye_code' => $request->input('dye_code'),
            'weight' => $request->input('weight'),
            'process_code' => $request->input('process_code'),
            'material_type' => $request->input('material_type'),
            'measured_at' => Carbon::now(),
            'warehouse_done' => false,
        ]);

        return response()->json([
            'status' => 'SUCCESS',
            'message' => 'Scale measurement recorded successfully',
            'data' => $measurement
        ], 201);
    }

    /**
     * Tra cứu bán thành phẩm đã cân theo COLOR + CODE (+ số ngày trở về trước, tùy chọn).
     * Port từ VBA gốc `checkform.btnCheck_Click` (workbook semiauto-small-scale,
     * scaleform.btnCheck_Click → checkform.Show) — nguyên bản Access truy vấn phẳng
     * `SELECT DISTINCT batchID ... WHERE COLOR=? AND CODE=? [AND TIME>=today-N]`,
     * rồi loop từng batchID lấy chi tiết RACK/DYECODE/WEIGHT/PROCESS. Ở đây gom nhóm
     * trực tiếp theo `legacy_batch_id` vì schema `scale_measurements` đã phẳng hóa đủ
     * (mỗi dòng tự mang color/product_code, không cần tách dòng header/detail như Access).
     *
     * Cột `process_color` của schema mới chưa từng được ghi ở bất kỳ luồng nào (xem
     * p0-c-scale-algorithm.md) — nhãn ACCEPTED/REJECTED lấy từ
     * `WeighingJobItem::process_status` của item liên kết, tương đương đúng cột
     * `processColor` mà VBA btnSave_Click ghi xuống Access.
     */
    public function checker(Request $request)
    {
        $request->validate([
            'color' => 'required|string|max:100',
            'code' => 'required|string|max:100',
            'days_back' => 'sometimes|integer|min:0|max:3650',
        ]);

        $color = trim($request->input('color'));
        $code = trim($request->input('code'));

        $query = ScaleMeasurement::with('weighingJobItem')
            ->where('color', $color)
            ->where('product_code', $code);

        if ($request->filled('days_back')) {
            $query->where('measured_at', '>=', Carbon::now()->subDays((int) $request->input('days_back'))->startOfDay());
        }

        $measurements = $query->orderBy('measured_at', 'desc')->get();

        if ($measurements->isEmpty()) {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'KHONG CO DU LIEU',
                'data' => [],
            ]);
        }

        $batches = $measurements
            ->groupBy('legacy_batch_id')
            ->map(function ($items, $batchId) {
                $first = $items->first();
                return [
                    'batch_id' => $batchId,
                    'color' => $first->color,
                    'product_code' => $first->product_code,
                    'machine_code' => $first->machine_code,
                    'level_code' => $first->level_code,
                    'measured_at' => optional($first->measured_at)->toIso8601String(),
                    'items' => $items->map(function ($m) {
                        return [
                            'rack_code' => $m->rack_code,
                            'dye_code' => $m->dye_code,
                            'weight' => $m->weight !== null ? (float) $m->weight : null,
                            'process_code' => $m->process_code,
                            'process_status' => optional($m->weighingJobItem)->process_status ?? 'ACCEPTED',
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $batches,
        ]);
    }
}
