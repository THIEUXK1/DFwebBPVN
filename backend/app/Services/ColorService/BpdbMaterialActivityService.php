<?php
// backend/app/Services/ColorService/BpdbMaterialActivityService.php
//
// "THỐNG KÊ HOẠT ĐỘNG NGUYÊN LIỆU THEO MÁY NHUỘM" — chỉ đọc BPDB.
//
// QUYẾT ĐỊNH HƯỚNG (2026-07-20, sau khi xác minh dữ liệu thật trước khi build — xem
// verification report cùng ngày): dashboard "tiêu hao theo khối lượng" như yêu cầu gốc
// KHÔNG khả thi cho vận hành hiện tại vì SUP_TaskDetails.DaDosare/GramsDosed (lượng yêu
// cầu/thực tế) đã = 0 cho 100% dữ liệu từ 11/2025 tới nay (chỉ có số liệu thật trong
// khoảng 03/2025-10/2025). Người dùng đã chọn hướng A: dashboard HOẠT ĐỘNG dựa trên SỐ
// LƯỢNG TASK hoàn thành theo máy/nhóm nguyên liệu — dùng CHỈ những trường luôn có dữ liệu
// thật (TaskStatus/FinishTime/Machine/DyeCode), KHÔNG bao giờ gọi đây là "tiêu hao" hay
// suy diễn khối lượng. weightDataCoveragePercent được TÍNH LẠI mỗi lần theo khoảng thời
// gian được chọn (không hard-code ngày 11/2025) — nếu người dùng chọn khoảng có dữ liệu
// khối lượng thật (vd 03-10/2025), tỉ lệ sẽ tự phản ánh đúng, không cần sửa code.
//
// DyeCode từ 11/2025 tới nay chủ yếu là mã NHÓM nguyên liệu (NYLON DYES, LUONG THUOC
// NHUOM, CATION DYES, THUOC NHUOM CATION, DISPERSE DYES...), KHÔNG phải mã hóa chất cụ
// thể — distinctMaterialCodeCount trong summary() giúp người dùng tự nhận biết mức độ
// chi tiết thực tế của khoảng thời gian đang xem, không giả định cố định.
//
// Join SUP_Tasks <-> SUP_TaskDetails theo TaskTitle (không có FK theo Id — đã xác nhận
// bằng dữ liệu thật, giống BpdbChemicalDemandService). Chỉ tính task TaskStatus=40 (đã
// kết thúc) và IsDeleted=0 — không suy diễn "hoàn thành" từ các trạng thái khác.
//
// CẬP NHẬT 2026-07-21 (sửa lại kết luận tuần trước): weightDataCoveragePercent ở trên
// tính từ SUP_TaskDetails.GramsDosed vẫn đúng là gần như luôn 0 — NHƯNG phát hiện thêm
// nguồn khác thật sự có số liệu khối lượng: BPVN2025.dbo.SUP_Storico (xem
// BpdbSupStoricoService, đã trace chéo xác nhận là chi tiết thật của CÙNG task ở đây,
// không phải hệ thống khác). getSummary()/getByMachine() giờ trả thêm khối `supStorico`
// lấy từ nguồn này — vẫn giữ nguyên các trường cũ (completedTaskCount/weightDataCoverage...)
// để không phá vỡ tab đã build tuần trước, chỉ BỔ SUNG số liệu khối lượng thật bên cạnh.

namespace App\Services\ColorService;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BpdbMaterialActivityService
{
    private const CACHE_TTL = 45; // giây — theo chiến lược MVP mục 14 (cache 30-60s, không hit BPDB liên tục)

    public function __construct(
        private readonly BpdbReadOnlyClient $client,
        private readonly BpdbMachineMonitoringService $machineService,
        private readonly BpdbSupStoricoService $supStoricoService,
    ) {
    }

    public function getSummary(Carbon $from, Carbon $to, ?string $machineCode): array
    {
        return $this->cached('summary', $from, $to, $machineCode, null, function () use ($from, $to, $machineCode) {
            $machineIds = $this->resolveMachineIdFilter($machineCode);
            [$sql, $params] = $this->baseTaskFilter($from, $to, $machineIds);

            $taskRow = $this->client->select(
                "SELECT COUNT(*) as task_count, COUNT(DISTINCT Machine) as machine_count,
                        MIN(FinishTime) as first_finish, MAX(FinishTime) as last_finish
                 FROM dbo.SUP_Tasks WHERE $sql",
                $params
            )[0] ?? null;

            $taskCount = (int) ($taskRow['task_count'] ?? 0);
            $mappedMachineCount = $this->countMappedMachines($from, $to, $machineIds);

            [$joinSql, $joinParams] = $this->baseTaskFilter($from, $to, $machineIds, 't');
            $materialRow = $this->client->select(
                "SELECT COUNT(DISTINCT td.DyeCode) as material_code_count,
                        COUNT(*) as detail_row_count,
                        SUM(CASE WHEN td.GramsDosed > 0 THEN 1 ELSE 0 END) as rows_with_actual_qty,
                        SUM(CASE WHEN td.DaDosare > 0 THEN 1 ELSE 0 END) as rows_with_requested_qty
                 FROM dbo.SUP_Tasks t
                 JOIN dbo.SUP_TaskDetails td ON td.TaskTitle = t.TaskTitle
                 WHERE $joinSql",
                $joinParams
            )[0] ?? null;

            $detailRowCount = (int) ($materialRow['detail_row_count'] ?? 0);
            $rowsWithActualQty = (int) ($materialRow['rows_with_actual_qty'] ?? 0);

            return [
                'completedTaskCount' => $taskCount,
                'machineCount' => (int) ($taskRow['machine_count'] ?? 0),
                'mappedMachineCount' => $mappedMachineCount,
                'unmappedMachineTaskWarning' => $mappedMachineCount < (int) ($taskRow['machine_count'] ?? 0),
                'distinctMaterialCodeCount' => (int) ($materialRow['material_code_count'] ?? 0),
                'firstFinishTime' => $taskRow['first_finish'] ?? null,
                'lastFinishTime' => $taskRow['last_finish'] ?? null,
                // Tự tính lại theo khoảng thời gian đang xem — KHÔNG hard-code mốc 11/2025.
                'weightDataCoveragePercent' => $detailRowCount > 0
                    ? round($rowsWithActualQty / $detailRowCount * 100, 2)
                    : 0.0,
                'weightDataRowsAvailable' => $rowsWithActualQty,
                'weightDataRowsTotal' => $detailRowCount,
                // Số liệu khối lượng THẬT từ BPVN2025.SUP_Storico (mục "CẬP NHẬT 2026-07-21"
                // ở đầu file) — khác nguồn với các trường phía trên, để riêng 1 khối, không
                // trộn lẫn 2 nguồn vào cùng 1 con số.
                'supStorico' => $this->supStoricoService->getSummary($from, $to, $machineCode),
            ];
        });
    }

    public function getByMachine(Carbon $from, Carbon $to): array
    {
        return $this->cached('byMachine', $from, $to, null, null, function () use ($from, $to) {
            [$sql, $params] = $this->baseTaskFilter($from, $to, null);

            $taskRows = $this->client->select(
                "SELECT Machine, COUNT(*) as task_count, MIN(FinishTime) as first_finish, MAX(FinishTime) as last_finish
                 FROM dbo.SUP_Tasks WHERE $sql GROUP BY Machine",
                $params
            );

            [$joinSql, $joinParams] = $this->baseTaskFilter($from, $to, null, 't');
            $materialRows = $this->client->select(
                "SELECT t.Machine, COUNT(DISTINCT td.DyeCode) as material_code_count
                 FROM dbo.SUP_Tasks t JOIN dbo.SUP_TaskDetails td ON td.TaskTitle = t.TaskTitle
                 WHERE $joinSql GROUP BY t.Machine",
                $joinParams
            );
            $materialCountByMachineId = [];
            foreach ($materialRows as $r) {
                $materialCountByMachineId[$r['Machine']] = (int) $r['material_code_count'];
            }

            $index = $this->machineIdIndex();
            $rows = [];
            $unmappedTaskCount = 0;
            foreach ($taskRows as $r) {
                $machineId = $r['Machine'];
                $machineCode = $index[$machineId] ?? null;
                if ($machineCode === null) {
                    $unmappedTaskCount += (int) $r['task_count'];
                    continue;
                }
                $rows[] = [
                    'machineCode' => $machineCode,
                    'completedTaskCount' => (int) $r['task_count'],
                    'distinctMaterialCodeCount' => $materialCountByMachineId[$machineId] ?? 0,
                    'firstFinishTime' => $r['first_finish'],
                    'lastFinishTime' => $r['last_finish'],
                ];
            }

            usort($rows, fn ($a, $b) => $b['completedTaskCount'] <=> $a['completedTaskCount']);

            // Gắn thêm số liệu khối lượng thật theo mã máy (SUP_Storico dùng sẵn mã text
            // "VD016..." — không cần tra registry GUID như phía trên).
            $supStoricoByMachine = collect($this->supStoricoService->getByMachine($from, $to)['machines'])->keyBy('machineCode');
            foreach ($rows as &$row) {
                $s = $supStoricoByMachine->get($row['machineCode']);
                $row['supStorico'] = $s ? [
                    'dosingLineCount' => $s['dosingLineCount'],
                    'sumRequestedGrams' => $s['sumRequestedGrams'],
                    'sumActualGrams' => $s['sumActualGrams'],
                ] : null;
            }
            unset($row);

            return [
                'machines' => $rows,
                // Task có Machine GUID không khớp danh mục DyeMachines (LIKE 'VD%') — không âm
                // thầm loại bỏ, báo rõ để người dùng biết tổng không khớp 100%.
                'unmappedTaskCount' => $unmappedTaskCount,
            ];
        });
    }

    public function getByMaterial(Carbon $from, Carbon $to, ?string $machineCode): array
    {
        return $this->cached('byMaterial', $from, $to, $machineCode, null, function () use ($from, $to, $machineCode) {
            $machineIds = $this->resolveMachineIdFilter($machineCode);
            [$sql, $params] = $this->baseTaskFilter($from, $to, $machineIds, 't');

            $rows = $this->client->select(
                "SELECT td.DyeCode,
                        MIN(td.DyeName) as sample_name,
                        COUNT(DISTINCT td.DyeName) as distinct_name_count,
                        COUNT(DISTINCT t.Id) as task_count,
                        COUNT(DISTINCT t.Machine) as machine_count
                 FROM dbo.SUP_Tasks t JOIN dbo.SUP_TaskDetails td ON td.TaskTitle = t.TaskTitle
                 WHERE $sql
                 GROUP BY td.DyeCode
                 ORDER BY COUNT(DISTINCT t.Id) DESC",
                $params
            );

            return [
                'materials' => array_map(fn ($r) => [
                    'materialCode' => $r['DyeCode'],
                    'sampleName' => $r['sample_name'],
                    // >1 nghĩa là cùng 1 mã DyeCode từng gắn nhiều tên khác nhau trong khoảng
                    // thời gian này — bất thường đã ghi nhận khi xác minh (mục 8), hiển thị rõ
                    // thay vì chọn ngẫu nhiên 1 tên.
                    'multipleNamesWarning' => (int) $r['distinct_name_count'] > 1,
                    'completedTaskCount' => (int) $r['task_count'],
                    'machineCount' => (int) $r['machine_count'],
                ], $rows),
            ];
        });
    }

    public function getTimeseries(Carbon $from, Carbon $to, string $granularity, ?string $machineCode): array
    {
        $granularity = $granularity === 'week' ? 'week' : 'day';

        return $this->cached('timeseries', $from, $to, $machineCode, $granularity, function () use ($from, $to, $machineCode, $granularity) {
            $machineIds = $this->resolveMachineIdFilter($machineCode);
            [$sql, $params] = $this->baseTaskFilter($from, $to, $machineIds);

            $rows = $this->client->select(
                "SELECT CAST(FinishTime AS DATE) as bucket_date, COUNT(*) as task_count
                 FROM dbo.SUP_Tasks WHERE $sql
                 GROUP BY CAST(FinishTime AS DATE)
                 ORDER BY bucket_date",
                $params
            );

            if ($granularity === 'day') {
                return [
                    'granularity' => 'day',
                    'points' => array_map(fn ($r) => [
                        'periodStart' => (string) $r['bucket_date'],
                        'completedTaskCount' => (int) $r['task_count'],
                    ], $rows),
                ];
            }

            // Tuần = Thứ 2 - Chủ nhật theo giờ Asia/Ho_Chi_Minh (đúng yêu cầu mục 6) — gộp lại
            // ở PHP thay vì SQL Server (đơn giản, dễ kiểm chứng, số dòng nhỏ vì đã group theo ngày).
            $weekly = [];
            foreach ($rows as $r) {
                $day = Carbon::parse((string) $r['bucket_date'], 'Asia/Ho_Chi_Minh')->startOfWeek(Carbon::MONDAY);
                $key = $day->toDateString();
                $weekly[$key] = ($weekly[$key] ?? 0) + (int) $r['task_count'];
            }
            ksort($weekly);

            return [
                'granularity' => 'week',
                'points' => array_map(
                    fn ($k, $v) => ['periodStart' => $k, 'completedTaskCount' => $v],
                    array_keys($weekly),
                    array_values($weekly)
                ),
            ];
        });
    }

    /** Drill-down phân trang — CHỈ gọi khi người dùng mở chi tiết, không tải hết (mục 5 nguyên tắc chung của module BPDB). */
    public function getDetail(Carbon $from, Carbon $to, ?string $machineCode, ?string $materialCode, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 200));
        $offset = ($page - 1) * $perPage;

        $machineIds = $this->resolveMachineIdFilter($machineCode);
        [$sql, $params] = $this->baseTaskFilter($from, $to, $machineIds);

        $totalRow = $this->client->select("SELECT COUNT(*) as cnt FROM dbo.SUP_Tasks WHERE $sql", $params)[0] ?? null;
        $total = (int) ($totalRow['cnt'] ?? 0);

        $tasks = $this->client->select(
            "SELECT Id, Machine, TaskTitle, FinishTime, WorkStartTime, CreateTime
             FROM dbo.SUP_Tasks WHERE $sql
             ORDER BY FinishTime DESC
             OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
            [...$params, $offset, $perPage]
        );

        $index = $this->machineIdIndex();
        $titles = array_values(array_unique(array_column($tasks, 'TaskTitle')));
        $detailsByTitle = $this->batchTaskDetails($titles, $materialCode);

        $rows = [];
        foreach ($tasks as $t) {
            $lines = $detailsByTitle[$t['TaskTitle']] ?? [];
            if ($materialCode !== null && empty($lines)) {
                // Task này không có dòng khớp materialCode đang lọc — bỏ khỏi trang kết quả.
                continue;
            }
            $rows[] = [
                'taskId' => $t['Id'],
                'machineCode' => $index[$t['Machine']] ?? null,
                'taskTitle' => $t['TaskTitle'],
                'createdAt' => $t['CreateTime'],
                'workStartTime' => $t['WorkStartTime'] ?: null,
                'finishTime' => $t['FinishTime'],
                'materialLines' => $lines,
            ];
        }

        return [
            'rows' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
    }

    // ---- helpers ----

    private function batchTaskDetails(array $titles, ?string $materialCodeFilter): array
    {
        if (empty($titles)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($titles), '?'));
        $rows = $this->client->select(
            "SELECT TaskTitle, DyeCode, DyeName, DaDosare, GramsDosed, Unit
             FROM dbo.SUP_TaskDetails WHERE TaskTitle IN ($placeholders) ORDER BY TaskTitle, OrderNum",
            array_values($titles)
        );

        $byTitle = [];
        foreach ($rows as $r) {
            if ($materialCodeFilter !== null && $r['DyeCode'] !== $materialCodeFilter) {
                continue;
            }
            $requested = (float) $r['DaDosare'];
            $dosed = (float) $r['GramsDosed'];
            $byTitle[$r['TaskTitle']] ??= [];
            $byTitle[$r['TaskTitle']][] = [
                'materialCode' => $r['DyeCode'],
                'materialName' => $r['DyeName'],
                'unit' => $r['Unit'],
                'requestedAmount' => $requested,
                'actualAmount' => $dosed,
                // Chỉ đúng khi CÓ số liệu — không suy diễn "0 = chưa cấp" vì 98%+ dòng trong dữ
                // liệu gần đây là 0 do KHÔNG được ghi nhận, không phải do thực sự bằng 0.
                'quantityDataAvailable' => $requested > 0 || $dosed > 0,
            ];
        }
        return $byTitle;
    }

    private function countMappedMachines(Carbon $from, Carbon $to, ?array $machineIds): int
    {
        [$sql, $params] = $this->baseTaskFilter($from, $to, $machineIds);
        $rows = $this->client->select("SELECT DISTINCT Machine FROM dbo.SUP_Tasks WHERE $sql", $params);
        $index = $this->machineIdIndex();
        $mapped = 0;
        foreach ($rows as $r) {
            if (isset($index[$r['Machine']])) {
                $mapped++;
            }
        }
        return $mapped;
    }

    /** @return array{0:string,1:array} */
    private function baseTaskFilter(Carbon $from, Carbon $to, ?array $machineIds, string $alias = ''): array
    {
        $col = $alias !== '' ? "$alias." : '';
        $params = [];
        $sql = "{$col}TaskStatus = 40 AND {$col}IsDeleted = 0 AND {$col}FinishTime >= ? AND {$col}FinishTime < ?";
        $params[] = $from->format('Y-m-d H:i:s');
        $params[] = $to->format('Y-m-d H:i:s');

        if ($machineIds !== null) {
            if (empty($machineIds)) {
                // machineCode được lọc nhưng không khớp máy nào trong registry — trả điều kiện
                // luôn sai thay vì bỏ qua filter (tránh âm thầm trả về TOÀN BỘ máy).
                $sql .= " AND 1 = 0";
            } else {
                $placeholders = implode(',', array_fill(0, count($machineIds), '?'));
                $sql .= " AND {$col}Machine IN ($placeholders)";
                array_push($params, ...$machineIds);
            }
        }

        return [$sql, $params];
    }

    private function resolveMachineIdFilter(?string $machineCode): ?array
    {
        if ($machineCode === null) {
            return null;
        }
        $registry = $this->machineService->getMachineRegistry();
        if (!isset($registry[$machineCode])) {
            return [];
        }
        return array_column($registry[$machineCode]['variants'], 'machine_id');
    }

    private function machineIdIndex(): array
    {
        $registry = $this->machineService->getMachineRegistry();
        $index = [];
        foreach ($registry as $code => $variant) {
            foreach ($variant['variants'] as $v) {
                $index[$v['machine_id']] = $code;
            }
        }
        return $index;
    }

    private function cached(string $op, Carbon $from, Carbon $to, ?string $machineCode, ?string $extra, callable $compute): array
    {
        $key = sprintf(
            'bpdb_material_activity_%s_%s_%s_%s_%s',
            $op,
            $from->format('YmdHis'),
            $to->format('YmdHis'),
            $machineCode ?? '_',
            $extra ?? '_'
        );
        return Cache::remember($key, self::CACHE_TTL, $compute);
    }
}
