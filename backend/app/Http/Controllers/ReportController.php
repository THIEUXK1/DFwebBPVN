<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductionBatch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exports\ArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Resolve [from, to] Carbon range from request, defaulting to the last 30 days.
     */
    private function resolveRange(Request $request): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : (clone $to)->subDays(30)->startOfDay();

        return [$from, $to];
    }

    /**
     * Shift is not tracked as a column anywhere in the legacy or target schema (see open-questions.md
     * CH-BUS-002/CH-TECH-001). We derive it from time-of-day using the factory's standard 3-ca/8h pattern
     * so "sản lượng theo ca" can be reported; this is a documented assumption, not a confirmed business rule.
     */
    private function shiftCaseSql(string $column): string
    {
        $hourExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', $column) AS INTEGER)"
            : "EXTRACT(HOUR FROM $column)";

        return "CASE
            WHEN $hourExpr >= 6 AND $hourExpr < 14 THEN 'Ca 1 (06h-14h)'
            WHEN $hourExpr >= 14 AND $hourExpr < 22 THEN 'Ca 2 (14h-22h)'
            ELSE 'Ca 3 (22h-06h)'
        END";
    }

    /**
     * Loại vòng CÂN TAY khỏi báo cáo sản xuất (yêu cầu 2026-08-02).
     *
     * Cân tay là thợ dùng tạm cái cân, không quét đơn nào — không màu, không mã hàng, không định
     * mức. Để lọt vào báo cáo tiêu hao/dung sai thì mọi dòng đều là "thực cân X, định mức 0",
     * tức bịa ra một khoản vượt định mức 100% không có thật.
     *
     * Bản Eloquent tương đương là `ProductionBatch::khongPhaiCanTay()`; ở đây phải viết tay vì
     * báo cáo dùng query builder trần, scope của model không áp vào được.
     *
     * Dạng "NULL HOẶC không khớp" là bắt buộc: `legacy_batch_id` nullable, mà SQL cho
     * `NULL NOT LIKE '...'` ra NULL chứ không ra TRUE — dùng `not like` trần sẽ ném luôn mọi lô
     * không có mã cũ ra khỏi báo cáo.
     *
     * KHÔNG loại tiền tố "ADHOC-": lô đó đến từ một tem QR thật (chỉ là chưa khớp lô nào trong
     * Web) nên vẫn là việc sản xuất và vẫn phải được tính.
     */
    private function loaiCanTay($query, string $alias = 'pb'): void
    {
        $query->where(function ($q) use ($alias) {
            $q->whereNull("{$alias}.legacy_batch_id")
                ->orWhere("{$alias}.legacy_batch_id", 'not like', ProductionBatch::MANUAL_BATCH_PREFIX.'%');
        });
    }

    /**
     * Report 1: Tiêu hao thuốc nhuộm/hóa chất thực tế vs định mức (planned_weight vs actual_weight).
     */
    public function dyeConsumption(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $materialType = $request->input('material_type'); // DYE | CHEMICAL
        $machineId = $request->input('machine_id');

        $query = DB::table('weighing_job_items as wji')
            ->join('weighing_jobs as wj', 'wj.id', '=', 'wji.weighing_job_id')
            ->join('production_batches as pb', 'pb.id', '=', 'wj.production_batch_id')
            ->leftJoin('materials as m', 'm.code', '=', 'wji.material_code')
            ->where('wji.status', 'COMPLETED')
            ->whereBetween('wji.completed_at', [$from, $to]);
        $this->loaiCanTay($query);

        if ($materialType) {
            $query->where('wj.job_type', $materialType);
        }
        if ($machineId) {
            $query->where('pb.machine_id', $machineId);
        }

        $rows = (clone $query)
            ->select(
                'wji.material_code',
                DB::raw('COALESCE(m.name, wji.material_code) as material_name'),
                DB::raw("COALESCE(m.type, 'N/A') as material_type"),
                DB::raw('COUNT(*) as weigh_count'),
                DB::raw('SUM(wji.planned_weight) as planned_total'),
                DB::raw('SUM(wji.actual_weight) as actual_total')
            )
            ->groupBy('wji.material_code', 'm.name', 'm.type')
            ->orderByDesc(DB::raw('SUM(wji.actual_weight)'))
            ->get()
            ->map(function ($row) {
                $row->planned_total = round((float) $row->planned_total, 4);
                $row->actual_total = round((float) $row->actual_total, 4);
                $row->variance = round($row->actual_total - $row->planned_total, 4);
                $row->variance_pct = $row->planned_total > 0
                    ? round($row->variance / $row->planned_total * 100, 2)
                    : null;
                return $row;
            });

        $totals = [
            'planned_total' => round((float) $rows->sum('planned_total'), 4),
            'actual_total' => round((float) $rows->sum('actual_total'), 4),
        ];
        $totals['variance'] = round($totals['actual_total'] - $totals['planned_total'], 4);
        $totals['variance_pct'] = $totals['planned_total'] > 0
            ? round($totals['variance'] / $totals['planned_total'] * 100, 2)
            : null;

        if ($request->input('format') === 'xlsx') {
            return $this->exportExcel(
                'Bao_cao_tieu_hao_' . $from->format('Ymd') . '_' . $to->format('Ymd'),
                ['Mã vật tư', 'Tên vật tư', 'Loại', 'Số lần cân', 'Định mức (g)', 'Thực tế (g)', 'Chênh lệch (g)', 'Chênh lệch (%)'],
                $rows->map(fn ($r) => [$r->material_code, $r->material_name, $r->material_type, $r->weigh_count, $r->planned_total, $r->actual_total, $r->variance, $r->variance_pct])->toArray()
            );
        }
        if ($request->input('format') === 'pdf') {
            return $this->exportPdf(
                'Báo cáo tiêu hao thuốc nhuộm/hóa chất thực tế vs định mức',
                "Từ {$from->format('d/m/Y')} đến {$to->format('d/m/Y')}",
                ['Mã vật tư', 'Tên vật tư', 'Loại', 'Số lần cân', 'Định mức (g)', 'Thực tế (g)', 'Chênh lệch (g)', 'Chênh lệch (%)'],
                $rows->map(fn ($r) => [$r->material_code, $r->material_name, $r->material_type, $r->weigh_count, $r->planned_total, $r->actual_total, $r->variance, $r->variance_pct])->toArray(),
                'bao-cao-tieu-hao'
            );
        }

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'rows' => $rows,
                'totals' => $totals,
            ]
        ]);
    }

    /**
     * Report 2: Sai số dung sai cân bột màu/hóa chất và tỉ lệ KHÔNG ĐẠT.
     *
     * Từ 2026-07-30 (port y hệt VBA btnSave_Click) không còn luồng override có phê duyệt —
     * mọi lần cân đều lưu được, hệ thống chỉ gắn nhãn ĐẠT/KHÔNG ĐẠT. Vì vậy cột đếm ở đây
     * suy trực tiếp từ số cân thực tế so với dung sai đã snapshot trên item, thay cho cờ
     * `override_approved` (vĩnh viễn false) và trạng thái `OUT_OF_TOLERANCE` (không còn được
     * set nữa). Cùng công thức với WeighingJobItem::getProcessStatusAttribute.
     */
    public function toleranceStats(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $materialType = $request->input('material_type');
        $machineId = $request->input('machine_id');

        $base = DB::table('weighing_job_items as wji')
            ->join('weighing_jobs as wj', 'wj.id', '=', 'wji.weighing_job_id')
            ->join('production_batches as pb', 'pb.id', '=', 'wj.production_batch_id')
            ->leftJoin('materials as m', 'm.code', '=', 'wji.material_code')
            ->where('wji.status', 'COMPLETED')
            ->whereBetween('wji.completed_at', [$from, $to]);
        $this->loaiCanTay($base);

        if ($materialType) {
            $base->where('wj.job_type', $materialType);
        }
        if ($machineId) {
            $base->where('pb.machine_id', $machineId);
        }

        $deviationPctExpr = "AVG(ABS(wji.actual_weight - wji.planned_weight) / NULLIF(wji.planned_weight, 0) * 100)";
        $maxDeviationExpr = "MAX(ABS(wji.actual_weight - wji.planned_weight) / NULLIF(wji.planned_weight, 0) * 100)";
        // KHÔNG ĐẠT = số cân thực tế nằm ngoài [planned - tolerance_minus, planned + tolerance_plus]
        $rejectCountExpr = 'SUM(CASE WHEN wji.actual_weight < wji.planned_weight - wji.tolerance_minus'
            .' OR wji.actual_weight > wji.planned_weight + wji.tolerance_plus THEN 1 ELSE 0 END)';

        $byMaterial = (clone $base)
            ->select(
                'wji.material_code',
                DB::raw('COALESCE(m.name, wji.material_code) as material_name'),
                DB::raw('COUNT(*) as total_weighed'),
                DB::raw("$rejectCountExpr as reject_count"),
                DB::raw("$deviationPctExpr as avg_deviation_pct"),
                DB::raw("$maxDeviationExpr as max_deviation_pct")
            )
            ->groupBy('wji.material_code', 'm.name')
            ->orderByDesc(DB::raw($rejectCountExpr))
            ->get()
            ->map(function ($row) {
                $row->reject_rate_pct = $row->total_weighed > 0 ? round($row->reject_count / $row->total_weighed * 100, 2) : 0;
                $row->avg_deviation_pct = $row->avg_deviation_pct !== null ? round((float) $row->avg_deviation_pct, 3) : null;
                $row->max_deviation_pct = $row->max_deviation_pct !== null ? round((float) $row->max_deviation_pct, 3) : null;
                return $row;
            });

        $byMachine = (clone $base)
            ->join('machines as mac', 'mac.id', '=', 'pb.machine_id')
            ->select(
                'mac.code as machine_code',
                DB::raw('COUNT(*) as total_weighed'),
                DB::raw("$rejectCountExpr as reject_count")
            )
            ->groupBy('mac.code')
            ->orderByDesc(DB::raw($rejectCountExpr))
            ->get()
            ->map(function ($row) {
                $row->reject_rate_pct = $row->total_weighed > 0 ? round($row->reject_count / $row->total_weighed * 100, 2) : 0;
                return $row;
            });

        $totalWeighed = (int) $byMaterial->sum('total_weighed');
        $totalReject = (int) $byMaterial->sum('reject_count');

        $summary = [
            'total_weighed' => $totalWeighed,
            'total_reject' => $totalReject,
            'reject_rate_pct' => $totalWeighed > 0 ? round($totalReject / $totalWeighed * 100, 2) : 0,
        ];

        $exportHeaders = ['Mã vật tư', 'Tên vật tư', 'Số lần cân', 'Số lần Không đạt', 'Tỉ lệ Không đạt (%)', 'Sai số TB (%)', 'Sai số Max (%)'];
        $exportRows = $byMaterial->map(fn ($r) => [$r->material_code, $r->material_name, $r->total_weighed, $r->reject_count, $r->reject_rate_pct, $r->avg_deviation_pct, $r->max_deviation_pct])->toArray();

        if ($request->input('format') === 'xlsx') {
            return $this->exportExcel(
                'Bao_cao_dung_sai_' . $from->format('Ymd') . '_' . $to->format('Ymd'),
                $exportHeaders,
                $exportRows
            );
        }
        if ($request->input('format') === 'pdf') {
            return $this->exportPdf(
                'Báo cáo sai số dung sai cân và tỉ lệ Không đạt',
                "Từ {$from->format('d/m/Y')} đến {$to->format('d/m/Y')}",
                $exportHeaders,
                $exportRows,
                'bao-cao-dung-sai'
            );
        }

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'by_material' => $byMaterial,
                'by_machine' => $byMachine,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Report 3: Sản lượng máy nhuộm theo ngày/tháng/ca kíp sản xuất.
     * Uses feed_operations.completed_at as the "material actually fed into the dyeing machine" event,
     * and production_batches.cloth_weight as the output volume for that batch.
     */
    public function machineOutput(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);
        $groupBy = $request->input('group_by', 'day'); // day | month | shift
        $machineId = $request->input('machine_id');

        $query = DB::table('feed_operations as fo')
            ->join('production_batches as pb', 'pb.id', '=', 'fo.batch_id')
            ->join('machines as mac', 'mac.id', '=', 'pb.machine_id')
            ->whereNotNull('fo.completed_at')
            ->whereBetween('fo.completed_at', [$from, $to]);

        if ($machineId) {
            $query->where('mac.id', $machineId);
        }

        $periodExpr = match ($groupBy) {
            'month' => "TO_CHAR(fo.completed_at, 'YYYY-MM')",
            'shift' => $this->shiftCaseSql('fo.completed_at'),
            default => "TO_CHAR(fo.completed_at, 'YYYY-MM-DD')",
        };

        $rows = $query
            ->select(
                'mac.code as machine_code',
                DB::raw("$periodExpr as period"),
                DB::raw('COUNT(*) as batch_count'),
                DB::raw('SUM(COALESCE(pb.cloth_weight, 0)) as total_cloth_weight')
            )
            ->groupBy('mac.code', DB::raw($periodExpr))
            ->orderBy('period')
            ->orderBy('mac.code')
            ->get()
            ->map(function ($row) {
                $row->total_cloth_weight = round((float) $row->total_cloth_weight, 2);
                return $row;
            });

        $summary = [
            'total_batches' => (int) $rows->sum('batch_count'),
            'total_cloth_weight' => round((float) $rows->sum('total_cloth_weight'), 2),
            'group_by' => $groupBy,
        ];

        if ($request->input('format') === 'xlsx') {
            return $this->exportExcel(
                'Bao_cao_san_luong_' . $from->format('Ymd') . '_' . $to->format('Ymd'),
                ['Máy nhuộm', 'Kỳ báo cáo', 'Số lô hoàn tất', 'Tổng khối lượng vải (kg)'],
                $rows->map(fn ($r) => [$r->machine_code, $r->period, $r->batch_count, $r->total_cloth_weight])->toArray()
            );
        }
        if ($request->input('format') === 'pdf') {
            return $this->exportPdf(
                'Báo cáo sản lượng máy nhuộm',
                "Từ {$from->format('d/m/Y')} đến {$to->format('d/m/Y')} - Nhóm theo: $groupBy",
                ['Máy nhuộm', 'Kỳ báo cáo', 'Số lô hoàn tất', 'Tổng khối lượng vải (kg)'],
                $rows->map(fn ($r) => [$r->machine_code, $r->period, $r->batch_count, $r->total_cloth_weight])->toArray(),
                'bao-cao-san-luong'
            );
        }

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'rows' => $rows,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Report 4: Tỷ lệ sự cố và biểu đồ Pareto nguyên nhân hàng đầu.
     */
    public function troubleshootingPareto(Request $request)
    {
        [$from, $to] = $this->resolveRange($request);

        $casesQuery = DB::table('troubleshooting_cases as tc')
            ->whereBetween('tc.created_at', [$from, $to]);

        $totalCases = (clone $casesQuery)->count();
        $resolvedCases = (clone $casesQuery)->whereNotNull('tc.resolved_at')->count();

        $causeRows = (clone $casesQuery)
            ->join('causes as c', 'c.id', '=', 'tc.actual_cause_id')
            ->select('c.id as cause_id', 'c.cause_name', DB::raw('COUNT(*) as case_count'))
            ->groupBy('c.id', 'c.cause_name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $causeTotal = (int) $causeRows->sum('case_count');
        $cumulative = 0;
        $pareto = $causeRows->map(function ($row) use (&$cumulative, $causeTotal) {
            $pct = $causeTotal > 0 ? round($row->case_count / $causeTotal * 100, 2) : 0;
            $cumulative += $pct;
            return [
                'cause_id' => $row->cause_id,
                'cause_name' => $row->cause_name,
                'case_count' => $row->case_count,
                'pct' => $pct,
                'cumulative_pct' => round(min($cumulative, 100), 2),
            ];
        });

        $problemRows = (clone $casesQuery)
            ->join('case_problems as cp', 'cp.case_id', '=', 'tc.id')
            ->join('problems as p', 'p.id', '=', 'cp.problem_id')
            ->select('p.id as problem_id', 'p.problem_name', DB::raw('COUNT(*) as occurrence_count'))
            ->groupBy('p.id', 'p.problem_name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $summary = [
            'total_cases' => $totalCases,
            'resolved_cases' => $resolvedCases,
            'resolution_rate_pct' => $totalCases > 0 ? round($resolvedCases / $totalCases * 100, 2) : 0,
            'cases_with_confirmed_cause' => $causeTotal,
        ];

        if ($request->input('format') === 'xlsx') {
            return $this->exportExcel(
                'Bao_cao_pareto_su_co_' . $from->format('Ymd') . '_' . $to->format('Ymd'),
                ['Nguyên nhân', 'Số ca', 'Tỉ lệ (%)', 'Tỉ lệ tích lũy (%)'],
                $pareto->map(fn ($r) => [$r['cause_name'], $r['case_count'], $r['pct'], $r['cumulative_pct']])->toArray()
            );
        }
        if ($request->input('format') === 'pdf') {
            return $this->exportPdf(
                'Báo cáo Pareto nguyên nhân sự cố hàng đầu',
                "Từ {$from->format('d/m/Y')} đến {$to->format('d/m/Y')}",
                ['Nguyên nhân', 'Số ca', 'Tỉ lệ (%)', 'Tỉ lệ tích lũy (%)'],
                $pareto->map(fn ($r) => [$r['cause_name'], $r['case_count'], $r['pct'], $r['cumulative_pct']])->toArray(),
                'bao-cao-pareto-su-co'
            );
        }

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'pareto_causes' => $pareto,
                'by_problem' => $problemRows,
                'summary' => $summary,
            ]
        ]);
    }

    /**
     * Audit Log Explorer: tra cứu nhật ký thay đổi bất biến theo người dùng/hành động/thời gian.
     */
    public function auditLogs(Request $request)
    {
        $query = DB::table('audit_logs as al')
            ->leftJoin('users as u', 'u.id', '=', 'al.user_id')
            ->select('al.*', 'u.display_name as user_display_name', 'u.username');

        if ($request->filled('user_id')) {
            $query->where('al.user_id', $request->input('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('al.action', 'like', '%' . $request->input('action') . '%');
        }
        if ($request->filled('entity_type')) {
            $query->where('al.entity_type', $request->input('entity_type'));
        }
        if ($request->filled('from')) {
            $query->where('al.created_at', '>=', Carbon::parse($request->input('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('al.created_at', '<=', Carbon::parse($request->input('to'))->endOfDay());
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $paginated = $query->orderByDesc('al.created_at')->paginate($perPage);

        return response()->json([
            'status' => 'SUCCESS',
            'data' => $paginated
        ]);
    }

    /**
     * Distinct action/entity_type values to populate Audit Log Explorer filter dropdowns.
     */
    public function auditLogFilters()
    {
        $actions = DB::table('audit_logs')->select('action')->distinct()->orderBy('action')->pluck('action');
        $entityTypes = DB::table('audit_logs')->select('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type');

        return response()->json([
            'status' => 'SUCCESS',
            'data' => [
                'actions' => $actions,
                'entity_types' => $entityTypes,
            ]
        ]);
    }

    private function exportExcel(string $filename, array $headings, array $rows)
    {
        return Excel::download(new ArrayExport($headings, $rows), $filename . '.xlsx');
    }

    private function exportPdf(string $title, string $subtitle, array $headings, array $rows, string $filenameSlug)
    {
        $pdf = Pdf::loadView('reports.pdf', compact('title', 'subtitle', 'headings', 'rows'));
        return $pdf->download($filenameSlug . '-' . now()->format('Ymd_His') . '.pdf');
    }
}
