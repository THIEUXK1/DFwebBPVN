<?php
// backend/app/Services/ColorService/BpdbMachineMonitoringService.php
//
// Giám sát máy VD từ BPDB — chỉ đọc. Kết quả khảo sát (2026-07-20, xem
// colorservice-trace-report):
//   - KHÔNG có tín hiệu online/offline đáng tin cậy ở cấp TỪNG MÁY VD trong BPDB hay
//     BPVN2025. `PLCAlarms.dtRegistrazione` có timestamp thật nhưng chỉ ở cấp HỆ THỐNG/
//     LINE (Apparato: DLV_A11, DLV_A12, SCC_E12, SCC_E13, TANKS_N21, TRS — không phải
//     từng VD machine), và ngữ nghĩa chưa được xác nhận với người vận hành. `SUP_
//     StatiMacchine` không có cột thời gian nên không dùng làm heartbeat được.
//   - Do đó connectionStatus LUÔN trả về NOT_AVAILABLE cho từng máy — KHÔNG suy diễn
//     từ trạng thái task, đúng yêu cầu gốc.
//   - DyeVats KHÔNG có cột MachineId — không có đường liên kết DyeMachines->DyeVats
//     thật trong schema này (khác giả định ban đầu của tài liệu yêu cầu).
//
// Định danh máy: DyeMachines.MachineNo (vd "VD003") là mã máy VẬT LÝ thật — 1 MachineNo
// có NHIỀU dòng DyeMachines (mỗi dòng = 1 tổ hợp Machine+Tank+MứcNước, vd "VD03-4D-50").
// KHÔNG coi mỗi dòng DyeMachines là 1 máy riêng — phải gộp theo MachineNo.

namespace App\Services\ColorService;

use App\Models\FeatureFlag;
use App\Models\JitRoutingRule;
use App\Services\ColorService\TankCodeMapper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BpdbMachineMonitoringService
{
    private const REGISTRY_CACHE_TTL = 300; // 5 phút — danh mục máy đổi rất chậm
    private const STATUS_CACHE_TTL = 4;     // giây — tránh mỗi trình duyệt tự query BPDB

    public function __construct(private readonly BpdbReadOnlyClient $client)
    {
    }

    /**
     * Danh mục máy VD — cache 5 phút, 1 query duy nhất, dùng chung cho mọi request khác.
     * Trả về: [machineNo => ['machine_id_variants' => [...], 'display_name' => ..., 'variants' => [...]]]
     */
    public function getMachineRegistry(): array
    {
        return Cache::remember('bpdb_machine_registry', self::REGISTRY_CACHE_TTL, function () {
            $rows = $this->client->select(
                "SELECT MachineId, MachineNo, MachineName, Tank, MaxStorageContent
                 FROM dbo.DyeMachines
                 WHERE MachineNo LIKE 'VD%'
                 ORDER BY MachineNo, Tank"
            );

            $registry = [];
            foreach ($rows as $row) {
                $no = trim($row['MachineNo']);
                $registry[$no] ??= [
                    'machine_code' => $no,
                    'display_name' => $no,
                    'variants' => [],
                ];
                $registry[$no]['variants'][] = [
                    'machine_id' => $row['MachineId'],
                    'machine_name' => $row['MachineName'],
                    'tank' => $row['Tank'],
                    'max_storage_content' => $row['MaxStorageContent'],
                ];
            }

            return $registry;
        });
    }

    /**
     * Trạng thái tổng hợp toàn bộ máy VD — 1 query batch cho task, không loop theo máy.
     */
    public function getAllMachineStatuses(): array
    {
        return Cache::remember('bpdb_machine_statuses', self::STATUS_CACHE_TTL, function () {
            return $this->computeAllMachineStatuses();
        });
    }

    public function getStatusSummary(): array
    {
        $statuses = $this->getAllMachineStatuses();
        $machines = $statuses['machines'];

        $summary = [
            'total' => count($machines),
            'processing' => 0,
            'waiting' => 0,
            'transitioning' => 0,
            'idle' => 0,
            'completedRecently' => 0,
            'error' => 0,
            'cancelledRecent' => 0,
            'unknown' => 0,
            // Đúng yêu cầu: KHÔNG có dữ liệu heartbeat cấp máy -> null, không bịa số.
            'online' => null,
            'offline' => null,
            'connectionMonitoringAvailable' => false,
            'bpdbLastSyncedAt' => $statuses['fetched_at'],
            'bpdbConnected' => $statuses['bpdb_connected'],
        ];

        foreach ($machines as $m) {
            switch ($m['operationalStatus']) {
                case 'PROCESSING': $summary['processing']++; break;
                case 'WAITING': $summary['waiting']++; break;
                case 'TRANSITIONING': $summary['transitioning']++; break;
                case 'IDLE': $summary['idle']++; break;
                case 'COMPLETED_RECENTLY': $summary['completedRecently']++; break;
                case 'ERROR': $summary['error']++; break;
                case 'CANCELLED': $summary['cancelledRecent']++; break;
                default: $summary['unknown']++;
            }
        }

        return $summary;
    }

    /** Chi tiết 1 máy — CHỈ gọi khi người dùng mở máy (không nằm trong batch list). */
    public function getMachineDetail(string $machineCode): ?array
    {
        $registry = $this->getMachineRegistry();
        if (!isset($registry[$machineCode])) {
            return null;
        }

        $variant = $registry[$machineCode];
        $machineIds = array_column($variant['variants'], 'machine_id');
        $placeholders = implode(',', array_fill(0, count($machineIds), '?'));

        $recentTasks = $this->client->select(
            "SELECT TOP 20 Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
             FROM dbo.SUP_Tasks WHERE Machine IN ($placeholders) ORDER BY CreateTime DESC",
            $machineIds
        );

        $historyRows = $this->client->select(
            "SELECT TOP 10 ID, TaskTitle, CreateTime, FinishTime, DyeCode, DyeName, DaDosare, GramsDosed, Machine
             FROM dbo.SUP_HistoryRecords WHERE Machine LIKE ? ORDER BY FinishTime DESC",
            [$machineCode . '%']
        );

        $status = $this->reduceMachineStatus($machineCode, $variant, $recentTasks);

        return [
            'machineCode' => $machineCode,
            'displayName' => $variant['display_name'],
            'variants' => $variant['variants'],
            'status' => $status,
            'recentTasks' => $recentTasks,
            'recentHistory' => $historyRows,
        ];
    }

    /**
     * Dữ liệu cho biểu đồ Gantt "Máy VD" (mục yêu cầu 2026-07-29) — 1 nhóm (group) mỗi
     * Máy VD, 1 thanh (item) mỗi task đã THỰC SỰ bắt đầu (có WorkStartTime) trong
     * khoảng thời gian lọc. Task còn ở WAITING (chưa có WorkStartTime) không có mốc bắt
     * đầu thật nên KHÔNG vẽ thanh (đúng nguyên tắc "chỉ hiển thị dữ liệu có bằng chứng",
     * không suy diễn/bịa thời điểm bắt đầu).
     *
     * Mã màu/mã hàng lấy bằng cách tách TaskTitle (format "{color}-{code} {yyyymmddhhmm}"
     * — cùng quy tắc đã xác nhận tại BpdbTaskMatcherService/BpdbSupStoricoService::
     * deriveLotFromTaskTitle) tại dấu '-' ĐẦU TIÊN. Nếu bản thân mã màu chứa dấu '-', kết
     * quả tách sẽ sai — hiển thị nguyên taskTitle gốc trong tooltip để người dùng tự đối
     * chiếu khi nghi ngờ.
     */
    public function getGanttTimeline(?string $from, ?string $to): array
    {
        $registry = $this->getMachineRegistry();

        // Mỗi Máy VD là 1 group cha (nestedGroups), mỗi Tank thật của máy đó là 1 group
        // con riêng — 1 machine_id (DyeMachines.MachineId) LUÔN thuộc đúng 1 tank, dùng
        // trực tiếp làm khóa tra group con, không cần đoán/gộp.
        $machineIdToTankGroup = [];
        $groups = [];
        $order = 0;

        foreach ($registry as $code => $variant) {
            $tankGroupIds = [];
            $seenTankGroups = [];

            foreach ($variant['variants'] as $v) {
                $tankLabel = TankCodeMapper::toLetterCode($v['tank']) ?? ('Tank #' . $v['tank']);
                $tankGroupId = $code . '::' . $tankLabel;

                if (!isset($seenTankGroups[$tankGroupId])) {
                    $seenTankGroups[$tankGroupId] = true;
                    $tankGroupIds[] = $tankGroupId;
                    $groups[] = ['id' => $tankGroupId, 'content' => $tankLabel, 'order' => ++$order];
                }

                $machineIdToTankGroup[$v['machine_id']] = $tankGroupId;
            }

            $groups[] = [
                'id' => $code,
                'content' => $variant['display_name'],
                'nestedGroups' => $tankGroupIds,
                'order' => ++$order,
            ];
        }

        $allMachineIds = array_keys($machineIdToTankGroup);

        $fromDt = $from ? Carbon::parse($from, 'Asia/Ho_Chi_Minh')->startOfDay() : now('Asia/Ho_Chi_Minh')->subDays(7)->startOfDay();
        $toDt = $to ? Carbon::parse($to, 'Asia/Ho_Chi_Minh')->endOfDay() : now('Asia/Ho_Chi_Minh')->endOfDay();

        $bpdbConnected = true;
        $items = [];

        try {
            $placeholders = implode(',', array_fill(0, count($allMachineIds), '?'));
            $rows = $this->client->select(
                "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg
                 FROM dbo.SUP_Tasks
                 WHERE Machine IN ($placeholders)
                   AND WorkStartTime IS NOT NULL
                   AND WorkStartTime BETWEEN ? AND ?
                 ORDER BY WorkStartTime ASC",
                [...$allMachineIds, $fromDt->format('Y-m-d H:i:s'), $toDt->format('Y-m-d H:i:s')]
            );

            $now = now();
            foreach ($rows as $row) {
                $tankGroupId = $machineIdToTankGroup[$row['Machine']] ?? null;
                if ($tankGroupId === null) {
                    continue;
                }

                $isDeleted = (bool) ($row['IsDeleted'] ?? false);
                $status = (int) $row['TaskStatus'];
                $uncompleted = empty($row['FinishTime']) && $status !== 99 && !$isDeleted;
                $end = $uncompleted ? $now : Carbon::parse($row['FinishTime'], 'Asia/Ho_Chi_Minh');

                [$color, $productCode] = $this->splitColorProductFromTitle($row['TaskTitle']);

                $items[] = [
                    'id' => $row['Id'],
                    'group' => $tankGroupId,
                    'taskTitle' => $row['TaskTitle'],
                    'color' => $color,
                    'productCode' => $productCode,
                    'taskStatus' => $status,
                    'isDeleted' => $isDeleted,
                    'uncompleted' => $uncompleted,
                    'errorMessage' => $row['ErrorMsg'] ?: null,
                    'start' => Carbon::parse($row['WorkStartTime'], 'Asia/Ho_Chi_Minh')->toIso8601String(),
                    'end' => $end->toIso8601String(),
                ];
            }
        } catch (\Throwable $e) {
            $bpdbConnected = false;
            Log::warning('BpdbMachineMonitoringService: BPDB unavailable, cannot build Gantt timeline', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'groups' => $groups,
            'items' => $items,
            'totalRecords' => count($items),
            'bpdb_connected' => $bpdbConnected,
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    /** @return array{0: ?string, 1: ?string} [color, productCode] — null nếu không tách được. */
    private function splitColorProductFromTitle(?string $title): array
    {
        if (!$title) {
            return [null, null];
        }
        $lot = preg_replace('/\s+\d{12}$/', '', $title);
        $parts = explode('-', $lot, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }
        return [trim($parts[0]), trim($parts[1])];
    }

    /** Timeline hoạt động — chỉ đọc khi người dùng yêu cầu, giới hạn khoảng thời gian. */
    public function getMachineTimeline(string $machineCode, ?string $from, ?string $to, int $limit = 50): array
    {
        $registry = $this->getMachineRegistry();
        if (!isset($registry[$machineCode])) {
            return [];
        }

        $machineIds = array_column($registry[$machineCode]['variants'], 'machine_id');
        $placeholders = implode(',', array_fill(0, count($machineIds), '?'));

        // BPDB (SQL Server) chạy giờ Việt Nam (UTC+7), lệch với PHP/app chạy UTC — xác
        // nhận thật 2026-07-20 (PHP now()=08:04 UTC, GETDATE() BPDB=15:04). Mặc định
        // from/to phải theo giờ Việt Nam, không phải now() UTC (nếu không sẽ cắt mất
        // đúng 7 giờ dữ liệu mới nhất).
        $from = $from ?: now('Asia/Ho_Chi_Minh')->subDays(2)->format('Y-m-d H:i:s');
        $to = $to ?: now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s');

        return $this->client->select(
            "SELECT TOP " . max(1, min($limit, 200)) . " Id, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg
             FROM dbo.SUP_Tasks
             WHERE Machine IN ($placeholders) AND CreateTime BETWEEN ? AND ?
             ORDER BY CreateTime DESC",
            [...$machineIds, $from, $to]
        );
    }

    private function computeAllMachineStatuses(): array
    {
        $registry = $this->getMachineRegistry();
        $allMachineIds = [];
        foreach ($registry as $variant) {
            foreach ($variant['variants'] as $v) {
                $allMachineIds[] = $v['machine_id'];
            }
        }

        $fetchedAt = now();
        $bpdbConnected = true;
        $tasksByMachineId = [];

        try {
            $placeholders = implode(',', array_fill(0, count($allMachineIds), '?'));
            // 1 query batch cho TOÀN BỘ máy — không loop. Lấy active (10/20/30) +
            // hoạt động trong 24h gần nhất để xác định COMPLETED_RECENTLY/CANCELLED.
            $rows = $this->client->select(
                "SELECT Id, Machine, TaskTitle, TaskStatus, IsDeleted, CreateTime, WorkStartTime, FinishTime, ErrorMsg, DisSystemName
                 FROM dbo.SUP_Tasks
                 WHERE Machine IN ($placeholders)
                   AND (TaskStatus IN (10, 20, 30) OR CreateTime >= ?)
                 ORDER BY Machine, CreateTime DESC",
                [...$allMachineIds, now('Asia/Ho_Chi_Minh')->subHours(24)->format('Y-m-d H:i:s')]
            );

            foreach ($rows as $row) {
                $tasksByMachineId[$row['Machine']] ??= [];
                $tasksByMachineId[$row['Machine']][] = $row;
            }
        } catch (\Throwable $e) {
            // BPDB mất kết nối — KHÔNG được chuyển hết máy thành Offline (yêu cầu Test 7).
            // Trả về danh sách máy với UNKNOWN, giữ nguyên registry.
            $bpdbConnected = false;
            Log::warning('BpdbMachineMonitoringService: BPDB unavailable, returning UNKNOWN for all machines', [
                'error' => $e->getMessage(),
            ]);
        }

        $machines = [];
        foreach ($registry as $machineCode => $variant) {
            $tasksForThisMachine = [];
            foreach ($variant['variants'] as $v) {
                foreach ($tasksByMachineId[$v['machine_id']] ?? [] as $t) {
                    $tasksForThisMachine[] = $t;
                }
            }

            $machines[] = $bpdbConnected
                ? $this->reduceMachineStatus($machineCode, $variant, $tasksForThisMachine)
                : $this->unknownStatus($machineCode, $variant);
        }

        return [
            'machines' => $machines,
            'fetched_at' => $fetchedAt->toIso8601String(),
            'bpdb_connected' => $bpdbConnected,
        ];
    }

    /**
     * Quy tắc chọn task hiện tại (mục 6 tài liệu yêu cầu):
     * PROCESSING > TRANSITIONING > WAITING > hoàn thành gần nhất > hủy gần nhất.
     * Cùng mức ưu tiên: WorkStartTime mới nhất -> CreateTime mới nhất -> Id lớn nhất.
     */
    private function reduceMachineStatus(string $machineCode, array $variant, array $tasks): array
    {
        if (empty($tasks)) {
            return $this->buildResult($machineCode, $variant, 'IDLE', null, null, 0, 0, null);
        }

        $priorityRank = fn (array $t) => match (true) {
            (int) $t['TaskStatus'] === 30 && !($t['IsDeleted'] ?? false) => 0, // PROCESSING
            (int) $t['TaskStatus'] === 20 => 1, // TRANSITIONING
            (int) $t['TaskStatus'] === 10 => 2, // WAITING
            (int) $t['TaskStatus'] === 40 => 3, // COMPLETED_RECENTLY
            (int) $t['TaskStatus'] === 99 || ($t['IsDeleted'] ?? false) => 4, // CANCELLED
            default => 5, // UNKNOWN
        };

        usort($tasks, function ($a, $b) use ($priorityRank) {
            $rankDiff = $priorityRank($a) <=> $priorityRank($b);
            if ($rankDiff !== 0) return $rankDiff;

            $aStart = $a['WorkStartTime'] ?? $a['CreateTime'];
            $bStart = $b['WorkStartTime'] ?? $b['CreateTime'];
            $timeDiff = strcmp((string) $bStart, (string) $aStart); // mới nhất trước
            if ($timeDiff !== 0) return $timeDiff;

            return strcmp((string) $b['Id'], (string) $a['Id']);
        });

        $primary = $tasks[0];
        $rank = $priorityRank($primary);
        $warnings = $this->detectStuckWarning($primary);

        $operationalStatus = match ($rank) {
            0 => 'PROCESSING',
            1 => 'TRANSITIONING',
            2 => 'WAITING',
            3 => (int) $primary['TaskStatus'] === 40 && !empty($primary['FinishTime']) ? 'COMPLETED_RECENTLY' : 'UNKNOWN',
            4 => 'CANCELLED',
            default => 'UNKNOWN',
        };

        if (!empty($primary['ErrorMsg'])) {
            $operationalStatus = 'ERROR';
        }

        $activeTasks = array_filter($tasks, fn ($t) => in_array((int) $t['TaskStatus'], [10, 20, 30], true) && !($t['IsDeleted'] ?? false));
        $waitingTasks = array_filter($tasks, fn ($t) => (int) $t['TaskStatus'] === 10 && !($t['IsDeleted'] ?? false));

        $lastCompleted = collect($tasks)->first(fn ($t) => (int) $t['TaskStatus'] === 40 && !empty($t['FinishTime']));

        return $this->buildResult(
            $machineCode,
            $variant,
            $operationalStatus,
            $primary,
            $lastCompleted,
            count($activeTasks),
            count($waitingTasks),
            $tasks[0]['CreateTime'] ?? null,
            $warnings
        );
    }

    /**
     * Ngưỡng cấu hình qua app.feature_flags (Admin sửa được, KHÔNG hard-code) — mục 10.
     * Mặc định hợp lý nếu Admin chưa cấu hình.
     */
    private function stuckThresholdMinutes(string $key, int $default): int
    {
        $flag = FeatureFlag::where('key', $key)->first();
        return $flag && is_numeric($flag->value) ? (int) $flag->value : $default;
    }

    /**
     * Public: dùng chung cho BpdbChemicalDemandService (mục "NHU CẦU BƠM HÓA CHẤT") để
     * không lặp lại ngưỡng/logic phát hiện task bị kẹt — một nguồn sự thật duy nhất.
     */
    public function detectStuckWarning(array $task): ?array
    {
        $status = (int) $task['TaskStatus'];
        $isDeleted = (bool) ($task['IsDeleted'] ?? false);
        $now = now();

        if ($status === 10) {
            // absolute=true BẮT BUỘC — Carbon 3.x (Laravel 12) đổi mặc định diffInX() sang
            // giá trị CÓ DẤU (âm khi mốc so sánh nằm trong quá khứ, luôn đúng ở đây), khác
            // Carbon 2.x. Thiếu tham số này khiến $minutes luôn âm và điều kiện >= $threshold
            // KHÔNG BAO GIỜ đúng — phát hiện khi verify dữ liệu thật (task orphan 268 ngày
            // vẫn không bị gắn cảnh báo). Lỗi này tồn tại từ trước, ảnh hưởng luôn cả tính
            // năng "task bị kẹt" đã báo cáo hoàn tất trước đó — đã sửa tại nguồn duy nhất này.
            $minutes = $now->diffInMinutes(Carbon::parse($task['CreateTime'], 'Asia/Ho_Chi_Minh'), true);
            $threshold = $this->stuckThresholdMinutes('bpdb_stuck_waiting_minutes', 30);
            if ($minutes >= $threshold) {
                return ['code' => 'WAITING_TOO_LONG', 'minutes' => $minutes, 'threshold' => $threshold];
            }
        } elseif ($status === 20) {
            $minutes = $now->diffInMinutes(Carbon::parse($task['CreateTime'], 'Asia/Ho_Chi_Minh'), true);
            $threshold = $this->stuckThresholdMinutes('bpdb_stuck_transition_minutes', 5);
            if ($minutes >= $threshold) {
                return ['code' => 'TRANSITION_STUCK', 'minutes' => $minutes, 'threshold' => $threshold];
            }
        } elseif ($status === 30) {
            if ($isDeleted) {
                return ['code' => 'ABORTED_OR_STALE', 'minutes' => null, 'threshold' => null];
            }
            $anchor = $task['WorkStartTime'] ?: $task['CreateTime'];
            $minutes = $now->diffInMinutes(Carbon::parse($anchor, 'Asia/Ho_Chi_Minh'), true);
            $threshold = $this->stuckThresholdMinutes('bpdb_stuck_processing_minutes', 60);
            if ($minutes >= $threshold) {
                return ['code' => 'PROCESSING_TOO_LONG', 'minutes' => $minutes, 'threshold' => $threshold];
            }
        } elseif ($status === 40 && empty($task['FinishTime'])) {
            return ['code' => 'DATA_INCONSISTENT', 'minutes' => null, 'threshold' => null];
        } elseif ($status === 99 && !$isDeleted) {
            return ['code' => 'DATA_INCONSISTENT', 'minutes' => null, 'threshold' => null];
        }

        return null;
    }

    private function buildResult(string $machineCode, array $variant, string $operationalStatus, ?array $current, ?array $lastCompleted, int $activeCount, int $waitingCount, ?string $lastActivityAt, ?array $warning = null): array
    {
        return [
            'machineCode' => $machineCode,
            'displayName' => $variant['display_name'],
            'operationalStatus' => $operationalStatus,
            // Không có nguồn heartbeat cấp máy đáng tin cậy (xem ghi chú đầu file) —
            // LUÔN NOT_AVAILABLE, không suy diễn từ task.
            'connectionStatus' => 'NOT_AVAILABLE',
            'activeTaskCount' => $activeCount,
            'waitingTaskCount' => $waitingCount,
            'currentTask' => $current ? $this->serializeTask($current, $variant) : null,
            'lastCompletedTask' => $lastCompleted ? [
                'taskId' => $lastCompleted['Id'],
                'taskTitle' => $lastCompleted['TaskTitle'],
                'finishTime' => $lastCompleted['FinishTime'],
            ] : null,
            'lastActivityAt' => $lastActivityAt,
            'dataAgeSeconds' => $lastActivityAt ? now()->diffInSeconds(Carbon::parse($lastActivityAt, 'Asia/Ho_Chi_Minh'), true) : null,
            'stuckWarning' => $warning,
        ];
    }

    private function unknownStatus(string $machineCode, array $variant): array
    {
        return [
            'machineCode' => $machineCode,
            'displayName' => $variant['display_name'],
            'operationalStatus' => 'UNKNOWN',
            'connectionStatus' => 'NOT_AVAILABLE',
            'activeTaskCount' => 0,
            'waitingTaskCount' => 0,
            'currentTask' => null,
            'lastCompletedTask' => null,
            'stuckWarning' => null,
            'lastActivityAt' => null,
            'dataAgeSeconds' => null,
        ];
    }

    private function serializeTask(array $t, array $variant): array
    {
        $tank = null;
        foreach ($variant['variants'] as $v) {
            if ($v['machine_id'] === $t['Machine']) {
                $tank = $v['tank'];
                break;
            }
        }

        // JIT queue không nằm trong dữ liệu BPDB task — tra từ quy tắc đã port VBA
        // (app.jit_routing_rules) theo Machine+Tank, không phải suy đoán. DyeMachines.Tank
        // lưu số thuần (1/2/3/4) — quy đổi qua TankCodeMapper (helper DUY NHẤT, không lặp
        // logic ở nơi khác — mục 7 "YÊU CẦU KHÓA MODULE GIÁM SÁT BPDB").
        $tankCode = TankCodeMapper::toLetterCode($tank);
        $jitQueue = null;
        $mappingError = null;

        if ($tankCode === null) {
            $mappingError = $tank !== null ? "Không quy đổi được Tank số '{$tank}' sang mã chữ" : null;
        } else {
            $rule = JitRoutingRule::where('machine_code', $variant['machine_code'])
                ->where('tank_code', $tankCode)
                ->where('is_active', true)
                ->orderBy('priority')
                ->first();
            $jitQueue = $rule?->jit_queue_code;

            if ($jitQueue === null) {
                $mappingError = "Không tìm thấy quy tắc jit_routing_rules cho {$variant['machine_code']}+{$tankCode}";
                Log::warning('BpdbMachineMonitoringService: JIT routing lookup miss', [
                    'machine_code' => $variant['machine_code'],
                    'tank_code' => $tankCode,
                ]);
            }
        }

        return [
            'taskId' => $t['Id'],
            'taskTitle' => $t['TaskTitle'],
            'taskStatus' => (int) $t['TaskStatus'],
            'tank' => $tankCode ?? $tank,
            'jitQueue' => $jitQueue,
            'jitMappingError' => $mappingError,
            'workStartTime' => $t['WorkStartTime'] ?: null,
            'finishTime' => $t['FinishTime'] ?: null,
            'errorMessage' => $t['ErrorMsg'] ?: null,
            'disSystemName' => $t['DisSystemName'] ?? null,
        ];
    }
}
