<?php
// backend/app/Services/ColorService/BpdbChemicalDemandService.php
//
// "NHU CẦU BƠM HÓA CHẤT" — đọc trực tiếp SUP_Tasks/SUP_TaskDetails/DyeMachines từ BPDB
// (chỉ đọc, tài khoản bpdb_monitor_readonly) để xác định máy VD nào đang cần/đang được
// cấp hóa chất. Độc lập với app.bpdb_task_links (bảng đó theo dõi các lệnh do WEB tạo ra;
// tab này theo dõi TOÀN BỘ nhu cầu thật trong BPDB bất kể nguồn gốc).
//
// PHÁT HIỆN QUAN TRỌNG khi xác minh query trước khi khóa (yêu cầu mục 4 "Cần xác minh
// điều kiện thực tế trước khi khóa query"): lọc thô "TaskStatus IN (10,20,30) AND
// IsDeleted=0 AND FinishTime IS NULL" trả về CẢ những task WAITING (10) tồn tại từ
// tháng 3/2026, thậm chí 10/2025 — rác/orphan chưa từng được ai xử lý, KHÔNG phải nhu
// cầu thật đang chờ. Test trực tiếp ngày 2026-07-20: filter thô trả 18 task, nhưng chỉ 4
// task được tạo trong 24h gần nhất. Do đó mỗi task đang hoạt động được phân vào 1 trong 3
// nhóm activityBucket — ACTIVE_RECENT / STUCK / ORPHANED_OLD (mục "Xử lý task orphan",
// yêu cầu bổ sung 2026-07-20) — dùng lại NGUYÊN VẸN ngưỡng "kẹt" của
// BpdbMachineMonitoringService::detectStuckWarning cho STUCK, cộng thêm 1 ngưỡng tuổi
// task riêng (cấu hình qua feature_flags, KHÔNG hard-code) cho ORPHANED_OLD.
//
// TÊN TRẠNG THÁI: dùng nhãn trung tính theo yêu cầu — KHÔNG khẳng định "đang bơm"/"đã bơm
// xong" vì BPDB không phân biệt được cân/hòa tan/bơm vật lý, và có bằng chứng thật
// (GramsDosed=0.0 dù đã có FinishTime) cho thấy TaskStatus=40 không chắc đã cấp xong hóa
// chất. displayStatus dùng AWAITING_PROCESSING(10)/TRANSITIONING(20)/PROCESSING(30)/
// ENDED(40)/CANCELLED(99|IsDeleted)/ERROR(có ErrorMsg, không bị hủy chính thức).
//
// Join SUP_Tasks <-> SUP_TaskDetails là theo TaskTitle (không có cột khóa ngoại theo Id
// giữa 2 bảng này trong schema — đã xác nhận bằng dữ liệu thật).

namespace App\Services\ColorService;

use App\Models\FeatureFlag;
use App\Models\JitRoutingRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BpdbChemicalDemandService
{
    private const CACHE_TTL = 4; // giây — cùng nhịp với BpdbMachineMonitoringService
    private const LAST_GOOD_KEY = 'bpdb_chemical_demand_last_good';

    public function __construct(
        private readonly BpdbReadOnlyClient $client,
        private readonly BpdbMachineMonitoringService $machineService,
        private readonly BpdbSupStoricoService $supStoricoService,
    ) {
    }

    /** Danh sách nhu cầu — cache 4s, không hammer BPDB mỗi lần frontend poll. */
    public function getDemandList(): array
    {
        return Cache::remember('bpdb_chemical_demand_list', self::CACHE_TTL, function () {
            return $this->computeDemandList();
        });
    }

    public function getSummary(): array
    {
        $list = $this->getDemandList();
        $active = $list['active'];

        $waitingMachines = [];
        $processingMachines = [];
        $tasksWaiting = 0;
        $tasksProcessing = 0;
        $longestWaitingSeconds = 0;
        $jitLoad = [];
        $stuckCount = 0;
        $orphanedCount = 0;

        foreach ($active as $t) {
            if ($t['displayStatus'] === 'AWAITING_PROCESSING') {
                $tasksWaiting++;
                $waitingMachines[$t['machineCode']] = true;
            } elseif (in_array($t['displayStatus'], ['PROCESSING', 'TRANSITIONING'], true)) {
                $tasksProcessing++;
                $processingMachines[$t['machineCode']] = true;
            }
            if ($t['waitingSeconds'] !== null) {
                $longestWaitingSeconds = max($longestWaitingSeconds, $t['waitingSeconds']);
            }
            if ($t['jitQueue']) {
                $jitLoad[$t['jitQueue']] = ($jitLoad[$t['jitQueue']] ?? 0) + 1;
            }
            if ($t['activityBucket'] === 'ORPHANED_OLD') {
                $orphanedCount++;
            } elseif ($t['activityBucket'] === 'STUCK') {
                $stuckCount++;
            }
        }

        arsort($jitLoad);
        $topJitQueue = array_key_first($jitLoad);

        return [
            'machinesWaiting' => count($waitingMachines),
            'machinesProcessing' => count($processingMachines),
            'tasksWaiting' => $tasksWaiting,
            'tasksProcessing' => $tasksProcessing,
            'completedToday' => $list['completedTodayCount'],
            'errorOrCancelledToday' => $list['errorOrCancelledTodayCount'],
            'longestWaitingSeconds' => $longestWaitingSeconds,
            'topJitQueue' => $topJitQueue,
            'topJitQueueLoad' => $topJitQueue ? $jitLoad[$topJitQueue] : 0,
            'stuckCount' => $stuckCount,
            'orphanedCount' => $orphanedCount,
            'orphanThresholdHours' => $this->flagInt('bpdb_demand_orphan_threshold_hours', 24),
            // Tương thích ngược cho cái tên cũ đã dùng ở FE trước bản này.
            'staleActiveCount' => $stuckCount + $orphanedCount,
            'fetchedAt' => $list['fetchedAt'],
            'bpdbConnected' => $list['bpdbConnected'],
        ];
    }

    /** Chi tiết 1 task — CHỈ gọi khi người dùng bấm mở (mục 5: không tải hết chi tiết từ đầu). */
    public function getTaskDetail(string $taskId): ?array
    {
        $rows = $this->client->select(
            "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
             FROM dbo.SUP_Tasks WHERE Id = ?",
            [$taskId]
        );

        if (empty($rows)) {
            return null;
        }
        $task = $rows[0];

        $registry = $this->machineService->getMachineRegistry();
        [$machineCode, $variant] = $this->findVariant($registry, $task['Machine']);

        // NÂNG CẤP 2026-07-21: thử lấy dòng định lượng THẬT từ BPVN2025.SUP_Storico trước —
        // SUP_TaskDetails.DyeCode/GramsDosed đã sụp thành 1 dòng "NYLON DYES"/0 cho hầu hết
        // task từ 11/2025 (không còn granularity theo từng hóa chất), nên đây không phải
        // "đối chiếu 2 nguồn" mà là THAY THẾ khi có bằng chứng thật, giữ SUP_TaskDetails làm
        // phương án dự phòng khi chưa có dữ liệu SUP_Storico (task còn đang chạy, hoặc lô
        // chưa từng ghi nhận ở BPVN2025).
        $storicoLines = [];
        if ($machineCode !== null) {
            $lot = $this->supStoricoService->deriveLotFromTaskTitle($task['TaskTitle']);
            $windowStart = Carbon::parse($task['WorkStartTime'] ?: $task['CreateTime'], 'Asia/Ho_Chi_Minh');
            $windowEnd = $task['FinishTime']
                ? Carbon::parse($task['FinishTime'], 'Asia/Ho_Chi_Minh')
                : now('Asia/Ho_Chi_Minh');
            $storicoLines = $this->supStoricoService->getLinesForTask($machineCode, $lot, $windowStart, $windowEnd);
        }

        if (!empty($storicoLines)) {
            $lines = array_values(array_map(function ($s, $i) {
                $requested = $s['requestedGrams'];
                $dosed = $s['actualGrams'];
                return [
                    'orderNum' => $i + 1,
                    'chemicalCode' => $s['materialCode'],
                    'chemicalName' => null, // SUP_Storico không có cột tên sản phẩm
                    'requestedAmount' => $requested,
                    'dosedAmount' => $dosed,
                    'unit' => 'g',
                    'batch' => null,
                    'lineStatusDerived' => $this->deriveLineStatus($requested, $dosed),
                ];
            }, $storicoLines, array_keys($storicoLines)));
            $chemicalLinesSource = 'SUP_Storico';
        } else {
            $details = $this->client->select(
                "SELECT OrderNum, TaskTitle, DyeCode, DyeName, DaDosare, GramsDosed, Unit, Batch, Extension
                 FROM dbo.SUP_TaskDetails WHERE TaskTitle = ? ORDER BY OrderNum",
                [$task['TaskTitle']]
            );

            $lines = array_map(function ($d) {
                $requested = (float) $d['DaDosare'];
                $dosed = (float) $d['GramsDosed'];
                return [
                    'orderNum' => $d['OrderNum'],
                    'chemicalCode' => $d['DyeCode'],
                    'chemicalName' => $d['DyeName'],
                    'requestedAmount' => $requested,
                    'dosedAmount' => $dosed,
                    'unit' => $d['Unit'],
                    'batch' => $d['Batch'],
                    'lineStatusDerived' => $this->deriveLineStatus($requested, $dosed),
                ];
            }, $details);
            $chemicalLinesSource = 'SUP_TaskDetails';
        }

        return [
            'taskId' => $task['Id'],
            'taskTitle' => $task['TaskTitle'],
            'machineCode' => $machineCode,
            'tank' => $variant ? $this->resolveTankCode($variant, $task['Machine']) : null,
            'taskStatus' => (int) $task['TaskStatus'],
            'displayStatus' => $this->mapDisplayStatus($task),
            'createdAt' => $task['CreateTime'],
            'workStartTime' => $task['WorkStartTime'] ?: null,
            'finishTime' => $task['FinishTime'] ?: null,
            'errorMessage' => $task['ErrorMsg'] ?: null,
            'chemicalLines' => $lines,
            // 'SUP_Storico' (số liệu thật) | 'SUP_TaskDetails' (thường =0, xem ghi chú trên) —
            // luôn khai báo rõ nguồn, không âm thầm trộn.
            'chemicalLinesSource' => $chemicalLinesSource,
        ];
    }

    /**
     * "Trạng thái từng dòng" (mục 5) — KHÔNG có cột status/error thật ở cả 2 nguồn dữ liệu.
     * Suy ra trạng thái từ so sánh yêu cầu vs thực tế — gắn rõ là GIÁ TRỊ SUY DIỄN.
     */
    private function deriveLineStatus(float $requested, float $dosed): string
    {
        return match (true) {
            $requested <= 0 && $dosed <= 0 => 'NO_DATA',
            $dosed <= 0 => 'PENDING',
            abs($dosed - $requested) < 0.01 => 'DOSED',
            default => 'PARTIAL_OR_DEVIATION',
        };
    }

    private function computeDemandList(): array
    {
        $fetchedAt = now();
        $active = [];
        $completedRecent = [];
        $errorOrCancelled = [];
        $completedTodayCount = 0;
        $errorOrCancelledTodayCount = 0;

        try {
            // Fetch registry BÊN TRONG try — nếu BPDB sập ngay từ query đầu tiên (vd sau khi
            // app vừa khởi động, chưa có gì trong cache 300s) thì vẫn phải rơi vào nhánh
            // catch bên dưới thay vì crash (Test 6).
            $registry = $this->machineService->getMachineRegistry();
            $allMachineIds = [];
            foreach ($registry as $variant) {
                foreach ($variant['variants'] as $v) {
                    $allMachineIds[] = $v['machine_id'];
                }
            }

            $placeholders = implode(',', array_fill(0, count($allMachineIds), '?'));
            $todayStart = now('Asia/Ho_Chi_Minh')->startOfDay()->format('Y-m-d H:i:s');
            $completedWindowMinutes = $this->flagInt('bpdb_demand_completed_window_minutes', 120);
            $cancelledWindowHours = $this->flagInt('bpdb_demand_cancelled_window_hours', 24);

            // 1) Đang hoạt động — đúng filter mục 4, đã xác minh cần thêm stuckWarning để
            //    phân biệt rác tồn đọng (xem ghi chú đầu file).
            $activeRows = $this->client->select(
                "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
                 FROM dbo.SUP_Tasks
                 WHERE Machine IN ($placeholders) AND TaskStatus IN (10, 20, 30) AND IsDeleted = 0 AND FinishTime IS NULL",
                $allMachineIds
            );

            // 2) Vừa hoàn thành — cửa sổ thời gian giới hạn (mặc định 2h), không kéo toàn bộ lịch sử.
            $completedRows = $this->client->select(
                "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
                 FROM dbo.SUP_Tasks
                 WHERE Machine IN ($placeholders) AND TaskStatus = 40 AND FinishTime >= ?
                 ORDER BY FinishTime DESC",
                [...$allMachineIds, now('Asia/Ho_Chi_Minh')->subMinutes($completedWindowMinutes)->format('Y-m-d H:i:s')]
            );

            // 3) Lỗi/hủy gần đây — TaskStatus=99 hoặc IsDeleted=1 hoặc có ErrorMsg.
            $cancelledRows = $this->client->select(
                "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
                 FROM dbo.SUP_Tasks
                 WHERE Machine IN ($placeholders)
                   AND (TaskStatus = 99 OR IsDeleted = 1 OR ErrorMsg IS NOT NULL)
                   AND CreateTime >= ?
                 ORDER BY CreateTime DESC",
                [...$allMachineIds, now('Asia/Ho_Chi_Minh')->subHours($cancelledWindowHours)->format('Y-m-d H:i:s')]
            );

            // Đếm cho dashboard "hôm nay" (mục 7) — đếm riêng, không giới hạn bởi cửa sổ 2h/24h ở trên.
            $completedTodayCount = (int) ($this->client->select(
                "SELECT COUNT(*) as cnt FROM dbo.SUP_Tasks WHERE Machine IN ($placeholders) AND TaskStatus = 40 AND FinishTime >= ?",
                [...$allMachineIds, $todayStart]
            )[0]['cnt'] ?? 0);

            $errorOrCancelledTodayCount = (int) ($this->client->select(
                "SELECT COUNT(*) as cnt FROM dbo.SUP_Tasks WHERE Machine IN ($placeholders) AND (TaskStatus = 99 OR IsDeleted = 1 OR ErrorMsg IS NOT NULL) AND CreateTime >= ?",
                [...$allMachineIds, $todayStart]
            )[0]['cnt'] ?? 0);

            // Số lượng hóa chất mỗi task — 1 query gộp theo TaskTitle, không N+1.
            $allTitles = array_unique(array_column([...$activeRows, ...$completedRows, ...$cancelledRows], 'TaskTitle'));
            $chemCounts = $this->batchChemicalCounts($allTitles);

            foreach ($activeRows as $row) {
                $active[] = $this->serializeDemandRow($row, $registry, $chemCounts, $fetchedAt);
            }
            foreach ($completedRows as $row) {
                $completedRecent[] = $this->serializeDemandRow($row, $registry, $chemCounts, $fetchedAt);
            }
            foreach ($cancelledRows as $row) {
                $errorOrCancelled[] = $this->serializeDemandRow($row, $registry, $chemCounts, $fetchedAt);
            }

            // Sắp xếp (mục 6): PROCESSING -> TRANSITIONING -> AWAITING_PROCESSING; trong mỗi
            // nhóm, chờ/xử lý lâu nhất trước (giúp lộ ngay task bị kẹt/tồn đọng lên đầu danh
            // sách thay vì chìm xuống dưới).
            $groupRank = fn (string $s) => match ($s) {
                'PROCESSING' => 0,
                'TRANSITIONING' => 1,
                'AWAITING_PROCESSING' => 2,
                default => 3,
            };
            usort($active, function ($a, $b) use ($groupRank) {
                $r = $groupRank($a['displayStatus']) <=> $groupRank($b['displayStatus']);
                if ($r !== 0) return $r;
                return ($b['waitingSeconds'] ?? 0) <=> ($a['waitingSeconds'] ?? 0);
            });

            $result = [
                'active' => $active,
                'completedRecent' => $completedRecent,
                'errorOrCancelled' => $errorOrCancelled,
                'completedTodayCount' => $completedTodayCount,
                'errorOrCancelledTodayCount' => $errorOrCancelledTodayCount,
                'fetchedAt' => $fetchedAt->toIso8601String(),
                'bpdbConnected' => true,
            ];

            Cache::put(self::LAST_GOOD_KEY, $result, now()->addMinutes(30));

            return $result;
        } catch (\Throwable $e) {
            Log::warning('BpdbChemicalDemandService: BPDB unavailable, falling back to last-good cache', [
                'error' => $e->getMessage(),
            ]);

            $lastGood = Cache::get(self::LAST_GOOD_KEY);
            if ($lastGood) {
                $lastGood['bpdbConnected'] = false;
                return $lastGood;
            }

            // Chưa từng có dữ liệu tốt nào để hiển thị — trả rỗng, không crash (Test 6).
            return [
                'active' => [],
                'completedRecent' => [],
                'errorOrCancelled' => [],
                'completedTodayCount' => 0,
                'errorOrCancelledTodayCount' => 0,
                'fetchedAt' => $fetchedAt->toIso8601String(),
                'bpdbConnected' => false,
            ];
        }
    }

    private function batchChemicalCounts(array $titles): array
    {
        if (empty($titles)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($titles), '?'));
        $rows = $this->client->select(
            "SELECT TaskTitle, COUNT(*) as cnt FROM dbo.SUP_TaskDetails WHERE TaskTitle IN ($placeholders) GROUP BY TaskTitle",
            array_values($titles)
        );
        $counts = [];
        foreach ($rows as $r) {
            $counts[$r['TaskTitle']] = (int) $r['cnt'];
        }
        return $counts;
    }

    private function serializeDemandRow(array $task, array $registry, array $chemCounts, Carbon $now): array
    {
        [$machineCode, $variant] = $this->findVariant($registry, $task['Machine']);
        $tankCode = $variant ? $this->resolveTankCode($variant, $task['Machine']) : null;

        $jitQueue = null;
        if ($machineCode && $tankCode) {
            $rule = JitRoutingRule::where('machine_code', $machineCode)
                ->where('tank_code', $tankCode)
                ->where('is_active', true)
                ->orderBy('priority')
                ->first();
            $jitQueue = $rule?->jit_queue_code;
        }

        $displayStatus = $this->mapDisplayStatus($task);
        $waitingSeconds = null;
        if ($displayStatus === 'AWAITING_PROCESSING' || $displayStatus === 'TRANSITIONING') {
            $waitingSeconds = $now->diffInSeconds(Carbon::parse($task['CreateTime'], 'Asia/Ho_Chi_Minh'), true);
        } elseif ($displayStatus === 'PROCESSING') {
            $anchor = $task['WorkStartTime'] ?: $task['CreateTime'];
            $waitingSeconds = $now->diffInSeconds(Carbon::parse($anchor, 'Asia/Ho_Chi_Minh'), true);
        }

        // Tuổi task tính từ CreateTime (KHÔNG phụ thuộc trạng thái hiện tại) — dùng riêng
        // để phân loại "tồn đọng cũ", khác waitingSeconds (neo theo mốc của trạng thái hiện
        // tại, dùng để hiển thị "thời gian chờ/xử lý").
        $ageSeconds = (int) round($now->diffInSeconds(Carbon::parse($task['CreateTime'], 'Asia/Ho_Chi_Minh'), true));
        $stuckWarning = $this->machineService->detectStuckWarning($task);
        $orphanThresholdSeconds = $this->flagInt('bpdb_demand_orphan_threshold_hours', 24) * 3600;

        // Phân loại mục "Xử lý task orphan": ORPHANED_OLD (ưu tiên cao nhất — vượt xa
        // ngưỡng "kẹt" thông thường, gần như chắc chắn là rác) > STUCK (vượt ngưỡng riêng
        // theo từng status) > ACTIVE_RECENT (bình thường). Ngưỡng đều cấu hình qua
        // app.feature_flags, không hard-code.
        $activityBucket = match (true) {
            $ageSeconds >= $orphanThresholdSeconds => 'ORPHANED_OLD',
            $stuckWarning !== null => 'STUCK',
            default => 'ACTIVE_RECENT',
        };

        return [
            'taskId' => $task['Id'],
            'machineCode' => $machineCode,
            'tank' => $tankCode,
            'waterLevel' => $variant ? $this->resolveWaterLevel($variant, $task['Machine']) : null,
            'jitQueue' => $jitQueue,
            'taskStatus' => (int) $task['TaskStatus'],
            'displayStatus' => $displayStatus,
            'createdAt' => $task['CreateTime'],
            'workStartTime' => $task['WorkStartTime'] ?: null,
            'finishTime' => $task['FinishTime'] ?: null,
            'waitingSeconds' => $waitingSeconds !== null ? (int) round($waitingSeconds) : null,
            'ageSeconds' => $ageSeconds,
            'activityBucket' => $activityBucket,
            'chemicalCount' => $chemCounts[$task['TaskTitle']] ?? 0,
            'errorMessage' => $task['ErrorMsg'] ?: null,
            'stuckWarning' => $stuckWarning,
        ];
    }

    /**
     * Nhãn TRUNG TÍNH theo yêu cầu — không khẳng định trạng thái bơm vật lý, chỉ mô tả
     * trạng thái task trong BPDB. Mục 3: TaskStatus=99 hoặc IsDeleted=1 -> CANCELLED,
     * ErrorMsg (nếu có) vẫn hiển thị kèm theo nhưng không đổi tên trạng thái thành ERROR.
     * ERROR chỉ dùng cho task KHÔNG bị hủy chính thức nhưng lại có ErrorMsg (bất thường).
     */
    private function mapDisplayStatus(array $task): string
    {
        $status = (int) $task['TaskStatus'];
        $isDeleted = (bool) ($task['IsDeleted'] ?? false);
        if ($status === 99 || $isDeleted) {
            return 'CANCELLED';
        }
        if (!empty($task['ErrorMsg'])) {
            return 'ERROR';
        }
        return match ($status) {
            10 => 'AWAITING_PROCESSING',  // "Chờ hệ thống xử lý" — KHÔNG dùng "chờ bơm"
            20 => 'TRANSITIONING',        // "Đang chuyển trạng thái"
            30 => 'PROCESSING',           // "Đang được hệ thống xử lý" — KHÔNG dùng "đang bơm"
            40 => 'ENDED',                // "Task đã kết thúc" — KHÔNG dùng "đã bơm xong"
            default => 'UNKNOWN',
        };
    }

    private function findVariant(array $registry, string $machineId): array
    {
        foreach ($registry as $code => $variant) {
            foreach ($variant['variants'] as $v) {
                if ($v['machine_id'] === $machineId) {
                    return [$code, $variant];
                }
            }
        }
        return [null, null];
    }

    private function resolveTankCode(array $variant, string $machineId): ?string
    {
        foreach ($variant['variants'] as $v) {
            if ($v['machine_id'] === $machineId) {
                return TankCodeMapper::toLetterCode($v['tank']);
            }
        }
        return null;
    }

    private function resolveWaterLevel(array $variant, string $machineId): ?string
    {
        foreach ($variant['variants'] as $v) {
            if ($v['machine_id'] === $machineId) {
                // MaxStorageContent là mức nước danh định của tổ hợp máy-tank này trong DyeMachines.
                return $v['max_storage_content'];
            }
        }
        return null;
    }

    private function flagInt(string $key, int $default): int
    {
        $flag = FeatureFlag::where('key', $key)->first();
        return $flag && is_numeric($flag->value) ? (int) $flag->value : $default;
    }
}
