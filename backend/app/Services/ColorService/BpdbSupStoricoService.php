<?php
// backend/app/Services/ColorService/BpdbSupStoricoService.php
//
// Nguồn dữ liệu CHUNG mới (2026-07-21, "ĐÁNH GIÁ LẠI TOÀN BỘ TÍNH NĂNG BPDB SANG BPVN2025")
// — dùng cho cả: nâng cấp BpdbMaterialActivityService (số liệu khối lượng thật) VÀ tab
// "Cấp máy" mới (Mức A). KHÔNG dùng cho "Vận chuyển" — SUP_Storico chỉ ghi từ lúc bắt
// đầu định lượng, không có bằng chứng cho giai đoạn vận chuyển nguyên liệu tới máy.
//
// PHÁT HIỆN QUAN TRỌNG khi rà soát (2026-07-21): tài khoản bpdb_monitor_readonly đọc
// được CẢ database BPVN2025 (ngoài phạm vi 7 bảng BPDB đã tài liệu hóa trong
// create_bpdb_monitor_readonly.sql) — đã xác nhận trực tiếp với người phụ trách dự án
// trong phiên này, KHÔNG phải suy đoán. Việc đọc cross-database qua BpdbReadOnlyClient
// (`BPVN2025.dbo.SUP_Storico`) vẫn tuân thủ đúng giới hạn CHỈ SELECT của client (không
// đổi gì ở BpdbReadOnlyClient — client đã chặn ghi ở tầng code, không phụ thuộc database
// đích).
//
// BẰNG CHỨNG SUP_Storico LÀ CHI TIẾT THẬT CỦA CÙNG TASK trong SUP_Tasks (trace chéo thật
// 2026-07-21): TaskTitle="ES78452-0028 202607210811" (SUP_Tasks, WorkStartTime 09:22:11 ->
// FinishTime 09:38:03) khớp Lotto="#ES78452-0028" (SUP_Storico, Macchina=VD016, các dòng
// AC02/AC03 có Data_Inizio/Data_Fine nằm TRỌN trong khung giờ trên). Khác với
// SUP_TaskDetails.GramsDosed (đã xác nhận = 0 cho ~100% dữ liệu từ 11/2025), cột Dosato ở
// đây CÓ giá trị thật khác DaDosare (vd 150.003 yêu cầu vs 146.016 thực tế) và vẫn đang
// phát sinh dữ liệu mới liên tục (668 dòng/ngày tại thời điểm khảo sát).
//
// LƯU Ý: Macchina ở đây LÀ SẴN mã máy dạng text (vd "VD016"), KHÔNG phải GUID như
// SUP_Tasks.Machine — không cần tra registry DyeMachines để quy đổi. Có 2 mã máy rác
// "98"/"99" xuất hiện trong dữ liệu thật (rõ ràng không phải máy VD thật) — loại trừ
// tường minh, không âm thầm gộp vào kết quả.
//
// Data_Fine CHƯA BAO GIỜ NULL trong dữ liệu thật đã kiểm tra (0/52.686 dòng) — bảng này
// chỉ ghi nhận SAU KHI 1 dòng định lượng đã hoàn tất, không phải trạng thái "đang chạy"
// theo thời gian thực. Muốn biết "đang xử lý" phải kết hợp với SUP_Tasks.TaskStatus=30
// (xem BpdbMachineMonitoringService), KHÔNG suy diễn từ bảng này.

namespace App\Services\ColorService;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BpdbSupStoricoService
{
    private const CACHE_TTL = 45; // giây — cùng chiến lược MVP đã áp dụng cho BpdbMaterialActivityService

    /** Mã máy rác thấy trong dữ liệu thật — không phải máy VD (xem ghi chú đầu file). */
    private const EXCLUDED_MACHINE_CODES = ['98', '99'];

    public function __construct(private readonly BpdbReadOnlyClient $client)
    {
    }

    public function getSummary(Carbon $from, Carbon $to, ?string $machineCode): array
    {
        return $this->cached('summary', $from, $to, $machineCode, null, function () use ($from, $to, $machineCode) {
            [$sql, $params] = $this->baseFilter($from, $to, $machineCode);

            $row = $this->client->select(
                "SELECT COUNT(*) as line_count,
                        COUNT(DISTINCT Macchina) as machine_count,
                        COUNT(DISTINCT ID_Prodotto) as product_count,
                        COUNT(DISTINCT Lotto) as lot_count,
                        SUM(DaDosare) as sum_requested,
                        SUM(Dosato) as sum_actual,
                        MIN(Data_Fine) as first_finish,
                        MAX(Data_Fine) as last_finish
                 FROM BPVN2025.dbo.SUP_Storico WHERE $sql",
                $params
            )[0] ?? null;

            $sumRequested = (float) ($row['sum_requested'] ?? 0);
            $sumActual = (float) ($row['sum_actual'] ?? 0);

            return [
                'dosingLineCount' => (int) ($row['line_count'] ?? 0),
                'machineCount' => (int) ($row['machine_count'] ?? 0),
                'distinctProductCount' => (int) ($row['product_count'] ?? 0),
                'lotCount' => (int) ($row['lot_count'] ?? 0),
                'sumRequestedGrams' => round($sumRequested, 2),
                'sumActualGrams' => round($sumActual, 2),
                // Chênh lệch yêu cầu/thực tế thật — KHÔNG phải hao hụt suy diễn, chỉ là hiệu số 2 cột thật.
                'sumDeviationGrams' => round($sumActual - $sumRequested, 2),
                'firstFinishTime' => $row['first_finish'] ?? null,
                'lastFinishTime' => $row['last_finish'] ?? null,
            ];
        });
    }

    public function getByMachine(Carbon $from, Carbon $to): array
    {
        return $this->cached('byMachine', $from, $to, null, null, function () use ($from, $to) {
            [$sql, $params] = $this->baseFilter($from, $to, null);

            $rows = $this->client->select(
                "SELECT Macchina,
                        COUNT(*) as line_count,
                        COUNT(DISTINCT Lotto) as lot_count,
                        SUM(DaDosare) as sum_requested,
                        SUM(Dosato) as sum_actual,
                        MAX(Data_Fine) as last_finish
                 FROM BPVN2025.dbo.SUP_Storico WHERE $sql
                 GROUP BY Macchina
                 ORDER BY COUNT(*) DESC",
                $params
            );

            return [
                'machines' => array_map(fn ($r) => [
                    'machineCode' => $r['Macchina'],
                    'dosingLineCount' => (int) $r['line_count'],
                    'lotCount' => (int) $r['lot_count'],
                    'sumRequestedGrams' => round((float) $r['sum_requested'], 2),
                    'sumActualGrams' => round((float) $r['sum_actual'], 2),
                    'lastFinishTime' => $r['last_finish'],
                ], $rows),
            ];
        });
    }

    public function getByMaterial(Carbon $from, Carbon $to, ?string $machineCode): array
    {
        return $this->cached('byMaterial', $from, $to, $machineCode, null, function () use ($from, $to, $machineCode) {
            [$sql, $params] = $this->baseFilter($from, $to, $machineCode);

            $rows = $this->client->select(
                "SELECT ID_Prodotto,
                        COUNT(*) as line_count,
                        COUNT(DISTINCT Macchina) as machine_count,
                        SUM(DaDosare) as sum_requested,
                        SUM(Dosato) as sum_actual
                 FROM BPVN2025.dbo.SUP_Storico WHERE $sql
                 GROUP BY ID_Prodotto
                 ORDER BY COUNT(*) DESC",
                $params
            );

            return [
                'materials' => array_map(fn ($r) => [
                    'materialCode' => $r['ID_Prodotto'],
                    'dosingLineCount' => (int) $r['line_count'],
                    'machineCount' => (int) $r['machine_count'],
                    'sumRequestedGrams' => round((float) $r['sum_requested'], 2),
                    'sumActualGrams' => round((float) $r['sum_actual'], 2),
                ], $rows),
            ];
        });
    }

    public function getTimeseries(Carbon $from, Carbon $to, string $granularity, ?string $machineCode): array
    {
        $granularity = $granularity === 'week' ? 'week' : 'day';

        return $this->cached('timeseries', $from, $to, $machineCode, $granularity, function () use ($from, $to, $machineCode, $granularity) {
            [$sql, $params] = $this->baseFilter($from, $to, $machineCode);

            $rows = $this->client->select(
                "SELECT CAST(Data_Fine AS DATE) as bucket_date, COUNT(*) as line_count, SUM(Dosato) as sum_actual
                 FROM BPVN2025.dbo.SUP_Storico WHERE $sql
                 GROUP BY CAST(Data_Fine AS DATE)
                 ORDER BY bucket_date",
                $params
            );

            if ($granularity === 'day') {
                return [
                    'granularity' => 'day',
                    'points' => array_map(fn ($r) => [
                        'periodStart' => (string) $r['bucket_date'],
                        'dosingLineCount' => (int) $r['line_count'],
                        'sumActualGrams' => round((float) $r['sum_actual'], 2),
                    ], $rows),
                ];
            }

            $weekly = [];
            foreach ($rows as $r) {
                $day = Carbon::parse((string) $r['bucket_date'], 'Asia/Ho_Chi_Minh')->startOfWeek(Carbon::MONDAY);
                $key = $day->toDateString();
                $weekly[$key] ??= ['count' => 0, 'actual' => 0.0];
                $weekly[$key]['count'] += (int) $r['line_count'];
                $weekly[$key]['actual'] += (float) $r['sum_actual'];
            }
            ksort($weekly);

            return [
                'granularity' => 'week',
                'points' => array_map(
                    fn ($k, $v) => ['periodStart' => $k, 'dosingLineCount' => $v['count'], 'sumActualGrams' => round($v['actual'], 2)],
                    array_keys($weekly),
                    array_values($weekly)
                ),
            ];
        });
    }

    /**
     * Dòng định lượng thật của 1 task cụ thể (yêu cầu 2026-07-21 — nâng cấp chi tiết task
     * ở "Nhu cầu bơm hóa chất") — khớp bằng Machine + Lô (suy từ TaskTitle) + khung giờ
     * [windowStart, windowEnd] của CHÍNH task đó.
     *
     * QUAN TRỌNG: Lotto trong SUP_Storico KHÔNG duy nhất theo task — cùng 1 lô có thể
     * redye/chạy lại nhiều lần cách nhau hàng tuần/tháng (xác nhận thật khi trace 2026-07-21:
     * lô "HS54289-L20032" có dòng từ 2026-04-26 tới 2026-07-21, nhiều máy khác nhau). Chỉ
     * lọc theo Lotto sẽ kéo nhầm dữ liệu của lần chạy KHÁC — BẮT BUỘC lọc thêm Machine +
     * khung giờ đúng của task đang xem (đã trace 5 task thật, khớp chính xác không cần
     * buffer lớn, chỉ thêm 5 phút phòng lệch giờ nhẹ giữa 2 server).
     */
    public function getLinesForTask(string $machineCode, string $taskLot, Carbon $windowStart, Carbon $windowEnd): array
    {
        $lot = ltrim(trim($taskLot), '#');
        $buffer = 5;
        $from = (clone $windowStart)->subMinutes($buffer);
        $to = (clone $windowEnd)->addMinutes($buffer);

        $rows = $this->client->select(
            "SELECT ID_Prodotto, Tank, DaDosare, Dosato, Data_Inizio, Data_Fine
             FROM BPVN2025.dbo.SUP_Storico
             WHERE Macchina = ? AND (Lotto = ? OR Lotto = ?) AND Data_Fine >= ? AND Data_Fine <= ?
             ORDER BY Data_Inizio",
            [$machineCode, $lot, '#' . $lot, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]
        );

        return array_map(fn ($r) => [
            'materialCode' => $r['ID_Prodotto'],
            'tank' => $r['Tank'],
            'requestedGrams' => round((float) $r['DaDosare'], 2),
            'actualGrams' => round((float) $r['Dosato'], 2),
            'startTime' => $r['Data_Inizio'],
            'finishTime' => $r['Data_Fine'],
        ], $rows);
    }

    /** Suy Lô từ TaskTitle BPDB (bỏ hậu tố 12 số ngày giờ tạo task ở cuối, nếu có). */
    public function deriveLotFromTaskTitle(string $taskTitle): string
    {
        return preg_replace('/\s+\d{12}$/', '', $taskTitle);
    }

    /**
     * Khối "dosingEvidence" gọn cho panel tham khảo (menu Cấp máy) — CHỈ trả về đã có
     * hay chưa, nguồn, và mốc định lượng gần nhất trong khung giờ task. Không dùng để
     * tự động hoàn tất completeFeed() — chỉ hiển thị đối chiếu (yêu cầu 2026-07-21).
     */
    public function getDosingEvidence(string $machineCode, string $taskTitle, ?Carbon $windowStart, ?Carbon $windowEnd): array
    {
        $windowStart ??= now('Asia/Ho_Chi_Minh')->subHours(12);
        $windowEnd ??= now('Asia/Ho_Chi_Minh');

        $lot = $this->deriveLotFromTaskTitle($taskTitle);
        $lines = $this->getLinesForTask($machineCode, $lot, $windowStart, $windowEnd);

        $latestFinish = null;
        foreach ($lines as $line) {
            if ($line['finishTime'] !== null && ($latestFinish === null || $line['finishTime'] > $latestFinish)) {
                $latestFinish = $line['finishTime'];
            }
        }

        return [
            'available' => !empty($lines),
            'source' => 'BPVN2025.SUP_Storico',
            'lineCount' => count($lines),
            'latestDosingAt' => $latestFinish,
            'lines' => $lines,
        ];
    }

    /** Drill-down phân trang — CHỈ gọi khi người dùng mở chi tiết. */
    public function getDetail(Carbon $from, Carbon $to, ?string $machineCode, ?string $materialCode, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        [$sql, $params] = $this->baseFilter($from, $to, $machineCode, $materialCode);

        $total = (int) ($this->client->select(
            "SELECT COUNT(*) as cnt FROM BPVN2025.dbo.SUP_Storico WHERE $sql",
            $params
        )[0]['cnt'] ?? 0);

        $rows = $this->client->select(
            "SELECT Macchina, Tank, Lotto, ID_Prodotto, DaDosare, Dosato, Data_Inizio, Data_Fine
             FROM BPVN2025.dbo.SUP_Storico WHERE $sql
             ORDER BY Data_Fine DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            [...$params, $offset, $perPage]
        );

        return [
            'rows' => array_map(fn ($r) => [
                'machineCode' => $r['Macchina'],
                'tank' => $r['Tank'],
                'tankLetterCode' => is_numeric($r['Tank']) ? TankCodeMapper::toLetterCode((int) $r['Tank']) : null,
                'lot' => $r['Lotto'],
                'materialCode' => $r['ID_Prodotto'],
                'requestedGrams' => round((float) $r['DaDosare'], 2),
                'actualGrams' => round((float) $r['Dosato'], 2),
                'startTime' => $r['Data_Inizio'],
                'finishTime' => $r['Data_Fine'],
            ], $rows),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
    }

    /** @return array{0:string,1:array} */
    private function baseFilter(Carbon $from, Carbon $to, ?string $machineCode, ?string $materialCode = null): array
    {
        $excludedPlaceholders = implode(',', array_fill(0, count(self::EXCLUDED_MACHINE_CODES), '?'));
        $params = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'), ...self::EXCLUDED_MACHINE_CODES];
        $sql = "Data_Fine >= ? AND Data_Fine < ? AND Macchina NOT IN ($excludedPlaceholders)";

        if ($machineCode !== null) {
            $sql .= " AND Macchina = ?";
            $params[] = $machineCode;
        }
        if ($materialCode !== null) {
            $sql .= " AND ID_Prodotto = ?";
            $params[] = $materialCode;
        }

        return [$sql, $params];
    }

    private function cached(string $op, Carbon $from, Carbon $to, ?string $machineCode, ?string $extra, callable $compute): array
    {
        $key = sprintf(
            'bpdb_sup_storico_%s_%s_%s_%s_%s',
            $op,
            $from->format('YmdHis'),
            $to->format('YmdHis'),
            $machineCode ?? '_',
            $extra ?? '_'
        );
        return Cache::remember($key, self::CACHE_TTL, $compute);
    }
}
