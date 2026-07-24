<?php
// backend/app/Http/Controllers/BpdbMachineFeedingController.php
//
// "CẤP MÁY" (2026-07-21) — Mức A, chỉ đọc BPVN2025.dbo.SUP_Storico qua BpdbSupStoricoService
// (xem ghi chú đầu file service đó). Có bằng chứng thật: Máy, Tank, Lô, mã sản phẩm, lượng
// yêu cầu/thực tế KHÁC NHAU, thời gian bắt đầu/kết thúc riêng từng dòng định lượng.

namespace App\Http\Controllers;

use App\Exports\ArrayExport;
use App\Services\ColorService\BpdbSupStoricoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class BpdbMachineFeedingController extends Controller
{
    public function summary(Request $request, BpdbSupStoricoService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getSummary($from, $to, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function byMachine(Request $request, BpdbSupStoricoService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getByMachine($from, $to);
        return response()->json($this->envelope($data, $from, $to));
    }

    public function byMaterial(Request $request, BpdbSupStoricoService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $data = $service->getByMaterial($from, $to, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function timeseries(Request $request, BpdbSupStoricoService $service)
    {
        [$from, $to] = $this->resolveRange($request);
        $granularity = $request->input('granularity', 'day');
        $data = $service->getTimeseries($from, $to, $granularity, $request->input('machine_code'));
        return response()->json($this->envelope($data, $from, $to));
    }

    public function detail(Request $request, BpdbSupStoricoService $service)
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

    /** Xuất Excel — giới hạn 5000 dòng/lần tránh treo truy vấn cross-database. */
    public function exportDetail(Request $request, BpdbSupStoricoService $service)
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

        $rows = array_map(fn ($r) => [
            $r['machineCode'], $r['tankLetterCode'] ?? $r['tank'], $r['lot'], $r['materialCode'],
            $r['requestedGrams'], $r['actualGrams'], $r['startTime'], $r['finishTime'],
        ], $data['rows']);

        $headings = ['Máy', 'Tank', 'Lô', 'Mã sản phẩm', 'Lượng yêu cầu (g)', 'Lượng thực tế (g)', 'Bắt đầu', 'Kết thúc'];

        return Excel::download(
            new ArrayExport($headings, $rows),
            'cap-may-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.xlsx'
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
            'source' => 'BPVN2025.SUP_Storico',
            'readOnly' => true,
            'evidenceLevel' => 'A',
        ]);
    }
}
