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
use App\Models\MesBatchCompletion;
use App\Services\ColorService\TankCodeMapper;
use App\Support\MachineCodeNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BpdbMachineMonitoringService
{
    private const REGISTRY_CACHE_TTL = 300; // 5 phút — danh mục máy đổi rất chậm
    private const STATUS_CACHE_TTL = 4;     // giây — tránh mỗi trình duyệt tự query BPDB

    // Mốc "máy nhàn rỗi từ bao giờ" — query gộp riêng vì query trạng thái chính chỉ lấy
    // task active + 24h gần nhất, không đủ để biết máy IDLE đã trống bao lâu. Cache dài
    // hơn (60s) vì độ chính xác từng giây không có ý nghĩa với máy đang trống, trong khi
    // aggregate 30 ngày đắt hơn nhiều query trạng thái.
    private const IDLE_LOOKBACK_DAYS = 30;
    private const LAST_ACTIVITY_CACHE_TTL = 60;

    // Tổng số mẻ theo mã màu-mã hàng (getLotRunTotal): quét toàn bộ lịch sử nên đắt, mà
    // con số này gần như đứng yên (chỉ +1 khi có mẻ mới cùng mã chạy) -> cache dài 5 phút.
    private const LOT_TOTAL_CACHE_TTL = 300;

    // Gantt là response NẶNG NHẤT hệ thống (đo 2026-08-04: 375KB, 492ms) và mỗi màn hình
    // treo tường tự gọi lại mỗi 30 giây, nên N màn hình = N lần nện BPDB cho cùng một dữ
    // liệu. Cache ngắn để các màn hình dùng chung 1 lần query; 15s vẫn thấp hơn nhiều
    // ngưỡng "stale" mặc định 60s của envelope nên không làm dữ liệu bị coi là cũ.
    private const GANTT_CACHE_TTL = 15;

    // Cửa sổ ghép giờ kết thúc MES với thanh Gantt: một thanh (task pha thuốc, mốc
    // WorkStartTime) khớp với mẻ MES cùng máy+màu+mã hàng có beginTime gần nhất trong ±12h.
    // Cùng (máy, màu, mã hàng) hiếm khi chạy 2 lần trong 12h nên cửa sổ rộng vẫn không nhầm;
    // rộng để dung sai lệch giữa mốc pha (BPDB) và mốc bắt đầu nhuộm (MES).
    private const MES_MATCH_WINDOW_MINUTES = 720;

    // Máy KHÔNG vẽ trên biểu đồ Gantt (yêu cầu 2026-08-05: ẩn VD001). Lọc ở ĐÂY chứ không
    // lọc ở getMachineRegistry(): danh mục máy còn dùng chung cho màn hình trạng thái máy và
    // cho phần đếm "Số lần đánh mẫu theo máy" — ẩn ở danh mục sẽ làm mất luôn cả những chỗ
    // đó, vượt quá phạm vi yêu cầu. Ẩn tại đây thì cả hàng máy lẫn mọi mẻ của nó đều không
    // được trả về, không tốn băng thông vô ích như lọc ở trình duyệt.
    private const GANTT_HIDDEN_MACHINES = ['VD001'];

    public function __construct(
        private readonly BpdbReadOnlyClient $client,
        private readonly ColorCodePalette $palette,
    ) {
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
        $cacheKey = 'bpdb_gantt_timeline:' . md5(($from ?? '') . '|' . ($to ?? ''));

        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        $result = $this->buildGanttTimeline($from, $to);

        // KHÔNG cache lúc BPDB lỗi: nếu không, một cú rớt mạng thoáng qua sẽ khiến mọi màn
        // hình báo mất kết nối thêm 15 giây nữa dù BPDB đã trở lại.
        if ($result['bpdb_connected']) {
            Cache::put($cacheKey, $result, self::GANTT_CACHE_TTL);
        }

        return $result;
    }

    private function buildGanttTimeline(?string $from, ?string $to): array
    {
        $registry = $this->getMachineRegistry();

        // Mỗi Máy VD là 1 group cha (nestedGroups), mỗi Tank thật của máy đó là 1 group
        // con riêng — 1 machine_id (DyeMachines.MachineId) LUÔN thuộc đúng 1 tank, dùng
        // trực tiếp làm khóa tra group con, không cần đoán/gộp.
        $machineIdToTankGroup = [];
        // machine_id (DyeMachines) -> mã máy vật lý (VD003...) — cần để ghép với MES theo máy.
        $machineIdToCode = [];
        $groups = [];
        $order = 0;

        foreach ($registry as $code => $variant) {
            if (in_array($code, self::GANTT_HIDDEN_MACHINES, true)) {
                continue;
            }

            $tankGroupIds = [];
            $seenTankGroups = [];

            // Dựng sẵn đủ 4 tank mặc định 1A/2B/3C/4D cho MỌI máy, kể cả máy thực tế chỉ
            // có 1-2 tank (yêu cầu 2026-08-02) — hàng tank không có máy nào thì để trống.
            // Khung tank cố định giúp mắt so hàng ngang giữa các máy mà không phải đọc lại
            // nhãn; hàng trống KHÔNG có nghĩa là "máy đang rảnh", chỉ là tank không tồn tại.
            foreach (TankCodeMapper::allLetterCodes() as $tankLabel) {
                $tankGroupId = $code.'::'.$tankLabel;
                $seenTankGroups[$tankGroupId] = true;
                $tankGroupIds[] = $tankGroupId;
                $groups[] = ['id' => $tankGroupId, 'content' => $tankLabel, 'order' => ++$order];
            }

            foreach ($variant['variants'] as $v) {
                $tankLabel = TankCodeMapper::toLetterCode($v['tank']) ?? ('Tank #' . $v['tank']);
                $tankGroupId = $code . '::' . $tankLabel;

                if (!isset($seenTankGroups[$tankGroupId])) {
                    $seenTankGroups[$tankGroupId] = true;
                    $tankGroupIds[] = $tankGroupId;
                    $groups[] = ['id' => $tankGroupId, 'content' => $tankLabel, 'order' => ++$order];
                }

                $machineIdToTankGroup[$v['machine_id']] = $tankGroupId;
                $machineIdToCode[$v['machine_id']] = $code;
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

        // Giờ kết thúc nhuộm THẬT từ MES (bảng cache mes_batch_completions) — thay giờ "ảo"
        // của BPDB/Sedo khi ghép được theo máy + mã màu + mã hàng + thời điểm gần nhau.
        $mesNormCodes = [];
        foreach (array_values($machineIdToCode) as $rawCode) {
            $mesNormCodes[MachineCodeNormalizer::normalize($rawCode)] = true;
        }
        $mesIndex = $this->loadMesCompletionIndex(array_keys($mesNormCodes), $fromDt, $toDt);

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
            // Task "chưa kết thúc" (FinishTime rỗng, kể cả case TaskStatus=40 nhưng BPDB
            // thiếu FinishTime — dữ liệu nguồn không nhất quán, xem detectStuckWarning()
            // DATA_INCONSISTENT) mà đã treo quá ngưỡng này thì ẨN khỏi Gantt thay vì vẽ
            // thanh kéo dài vô hạn tới hiện tại (báo cáo 2026-07-29: EP69725-L18032 ở
            // VD003/D4 không thấy thời gian kết thúc — thanh Gantt trông như đang chạy vô
            // thời hạn dù thực tế đã xong từ lâu).
            $hideUncompletedAfterMinutes = $this->stuckThresholdMinutes('bpdb_gantt_hide_uncompleted_after_minutes', 24 * 60);

            foreach ($rows as $row) {
                $tankGroupId = $machineIdToTankGroup[$row['Machine']] ?? null;
                if ($tankGroupId === null) {
                    continue;
                }

                $isDeleted = (bool) ($row['IsDeleted'] ?? false);
                $status = (int) $row['TaskStatus'];

                [$color, $productCode] = $this->splitColorProductFromTitle($row['TaskTitle']);
                $startCarbon = Carbon::parse($row['WorkStartTime'], 'Asia/Ho_Chi_Minh');
                $machineCodeNorm = MachineCodeNormalizer::normalize($machineIdToCode[$row['Machine']] ?? '');
                $mesEnd = $this->matchMesEnd($mesIndex, $machineCodeNorm, $color, $productCode, $startCarbon);

                $bpdbUncompleted = empty($row['FinishTime']) && $status !== 99 && !$isDeleted;

                if ($mesEnd !== null && $mesEnd->gt($startCarbon)) {
                    // MES xác nhận mẻ đã kết thúc -> dùng giờ THẬT, hết "ảo". Kể cả task BPDB
                    // còn treo (thiếu FinishTime) cũng được đóng đúng thời điểm nhuộm xong.
                    $end = $mesEnd;
                    $uncompleted = false;
                    $endSource = 'MES';
                } elseif ($bpdbUncompleted) {
                    // Không có bằng chứng kết thúc từ cả MES lẫn BPDB -> vẫn coi đang chạy (vẽ
                    // tới now()); ẩn nếu treo quá ngưỡng (giữ nguyên hành vi cũ).
                    $runningMinutes = $now->diffInMinutes($startCarbon, true);
                    if ($runningMinutes >= $hideUncompletedAfterMinutes) {
                        continue;
                    }
                    $end = $now;
                    $uncompleted = true;
                    $endSource = 'BPDB_RUNNING';
                } else {
                    // BPDB có FinishTime (giờ CẤP/PHA xong, không phải giờ nhuộm xong) mà MES
                    // chưa có -> tạm dùng, đánh dấu ước tính để frontend nói rõ nguồn.
                    $end = Carbon::parse($row['FinishTime'], 'Asia/Ho_Chi_Minh');
                    $uncompleted = false;
                    $endSource = 'BPDB';
                }

                $items[] = [
                    'id' => $row['Id'],
                    'group' => $tankGroupId,
                    'taskTitle' => $row['TaskTitle'],
                    'color' => $color,
                    // Màu ĐẠI DIỆN họ màu suy từ mã màu — BPDB không lưu RGB thật.
                    // Xem ColorCodePalette để biết vì sao phải suy ra và độ chính xác tới đâu.
                    'colorHex' => $this->palette->hexFor($color),
                    'productCode' => $productCode,
                    'taskStatus' => $status,
                    'isDeleted' => $isDeleted,
                    'uncompleted' => $uncompleted,
                    // Nguồn của giờ kết thúc đang vẽ: MES (thật) | BPDB (giờ pha, ước tính) |
                    // BPDB_RUNNING (chưa xong, đang vẽ tới hiện tại).
                    'endSource' => $endSource,
                    'errorMessage' => $row['ErrorMsg'] ?: null,
                    'start' => $startCarbon->toIso8601String(),
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

    /**
     * Tổng số mẻ của MỘT mã màu - mã hàng đã từng chạy trên toàn bộ máy VD, tính từ bản
     * ghi cũ nhất còn trong BPDB tới hiện tại (yêu cầu 2026-08-03: bấm vào thanh Gantt
     * phải thấy cả tổng số mẻ mã đó đã chạy từ đầu, không chỉ số mẻ trong khoảng ngày lọc).
     *
     * KHÔNG gộp vào getGanttTimeline: query này quét toàn bộ lịch sử SUP_Tasks, chạy sẵn
     * cho mọi mẻ đang vẽ (hàng trăm thanh) sẽ nện BPDB vô ích trong khi người dùng chỉ bấm
     * xem vài mẻ — nên tách endpoint gọi theo yêu cầu, cache 5 phút mỗi mã.
     *
     * "Đã từng chạy" đếm đúng theo tiêu chí thanh Gantt: có WorkStartTime (thật sự khởi
     * chạy), chưa bị xóa, không phải task hủy (TaskStatus=99).
     */
    public function getLotRunTotal(string $color, string $productCode): array
    {
        $lot = $color . '-' . $productCode;
        $cacheKey = 'bpdb_lot_run_total:' . md5($lot);

        return Cache::remember($cacheKey, self::LOT_TOTAL_CACHE_TTL, function () use ($lot) {
            $registry = $this->getMachineRegistry();
            // 1 máy VD có NHIỀU machine_id (mỗi tổ hợp Machine+Tank+MứcNước là 1 dòng
            // DyeMachines — xem ghi chú đầu file), nên phải quy ngược machine_id về mã máy
            // vật lý để cộng dồn, không đếm mỗi machine_id thành một "máy" riêng.
            $machineIdToCode = [];
            foreach ($registry as $code => $variant) {
                foreach ($variant['variants'] as $v) {
                    $machineIdToCode[$v['machine_id']] = $code;
                }
            }
            $machineIds = array_keys($machineIdToCode);

            if (!$machineIds) {
                return ['lot' => $lot, 'total' => 0, 'firstRunAt' => null, 'lastRunAt' => null, 'byMachine' => [], 'bpdbConnected' => true];
            }

            // TaskTitle = "{mã màu}-{mã hàng} {yyyymmddhhmm}" (xem splitColorProductFromTitle).
            // Khớp theo tiền tố CÓ dấu cách phía sau, không phải LIKE 'lot%' trần — nếu không
            // "RED-L1803" sẽ đếm nhầm cả mẻ của "RED-L18032". Vẫn nhận trường hợp TaskTitle
            // không có phần thời gian ở đuôi (so sánh bằng).
            // Escape ký tự đại diện của LIKE trong chính mã màu/mã hàng ('[' phải thay TRƯỚC).
            $prefix = str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $lot) . ' %';
            $placeholders = implode(',', array_fill(0, count($machineIds), '?'));

            try {
                // GROUP BY Machine (không phải 1 dòng tổng): cùng 1 lần quét lịch sử cho ra
                // luôn phần chia theo máy, cộng dồn lại trong PHP để có tổng — tránh phải
                // chạy 2 query trên cùng tập dữ liệu.
                $rows = $this->client->select(
                    "SELECT Machine, COUNT(*) AS Total, MIN(WorkStartTime) AS FirstRun, MAX(WorkStartTime) AS LastRun
                     FROM dbo.SUP_Tasks
                     WHERE Machine IN ($placeholders)
                       AND WorkStartTime IS NOT NULL
                       AND ISNULL(IsDeleted, 0) = 0
                       AND TaskStatus <> 99
                       AND (TaskTitle = ? OR TaskTitle LIKE ?)
                     GROUP BY Machine",
                    [...$machineIds, $lot, $prefix]
                );
            } catch (\Throwable $e) {
                Log::warning('BpdbMachineMonitoringService: BPDB unavailable, cannot count lot run total', [
                    'lot' => $lot,
                    'error' => $e->getMessage(),
                ]);

                return ['lot' => $lot, 'total' => null, 'firstRunAt' => null, 'lastRunAt' => null, 'byMachine' => [], 'bpdbConnected' => false];
            }

            $total = 0;
            $firstRun = null;
            $lastRun = null;
            $byMachine = [];

            foreach ($rows as $row) {
                $code = $machineIdToCode[$row['Machine']] ?? null;
                if ($code === null) {
                    continue;
                }
                $count = (int) $row['Total'];
                $total += $count;
                $byMachine[$code] = ($byMachine[$code] ?? 0) + $count;

                $rowFirst = !empty($row['FirstRun']) ? Carbon::parse($row['FirstRun'], 'Asia/Ho_Chi_Minh') : null;
                $rowLast = !empty($row['LastRun']) ? Carbon::parse($row['LastRun'], 'Asia/Ho_Chi_Minh') : null;
                if ($rowFirst && (!$firstRun || $rowFirst->lt($firstRun))) {
                    $firstRun = $rowFirst;
                }
                if ($rowLast && (!$lastRun || $rowLast->gt($lastRun))) {
                    $lastRun = $rowLast;
                }
            }

            $byMachineList = [];
            foreach ($byMachine as $code => $count) {
                $byMachineList[] = ['machineCode' => $code, 'count' => $count];
            }
            // Máy chạy nhiều mẻ nhất lên trước; đồng số mẻ thì theo mã máy cho ổn định thứ tự
            // giữa các lần gọi (nếu không, thứ tự sẽ đổi lung tung theo cách SQL Server trả về).
            usort($byMachineList, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['machineCode'], $b['machineCode']));

            return [
                'lot' => $lot,
                'total' => $total,
                'firstRunAt' => $firstRun?->toIso8601String(),
                'lastRunAt' => $lastRun?->toIso8601String(),
                'byMachine' => $byMachineList,
                'bpdbConnected' => true,
            ];
        });
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

    /**
     * Nạp giờ kết thúc thật của mẻ từ cache mes_batch_completions, gom theo khoá ghép
     * "machineCodeChuẩn|màu|mã hàng" -> danh sách {begin, end}. Nới khoảng ±1 ngày vì mẻ có
     * thể bắt đầu hơi trước hoặc kết thúc hơi sau cửa sổ lọc của Gantt.
     *
     * Bọc try/catch: bảng chưa migrate hoặc DB lỗi thì trả rỗng -> Gantt vẫn chạy như cũ
     * (tính năng bất hoạt, không làm sập màn hình).
     *
     * @param  array<int, string>  $normCodes  Mã máy đã chuẩn hoá (VD5, VD202...)
     * @return array<string, array<int, array{begin: Carbon, end: Carbon}>>
     */
    private function loadMesCompletionIndex(array $normCodes, Carbon $fromDt, Carbon $toDt): array
    {
        if (empty($normCodes)) {
            return [];
        }

        try {
            $rows = MesBatchCompletion::query()
                ->whereIn('machine_code', $normCodes)
                ->whereNotNull('end_time')
                ->whereNotNull('begin_time')
                ->whereBetween('begin_time', [$fromDt->copy()->subDay(), $toDt->copy()->addDay()])
                ->get(['machine_code', 'color_code', 'article_code', 'begin_time', 'end_time']);
        } catch (\Throwable $e) {
            Log::warning('BpdbMachineMonitoringService: không đọc được mes_batch_completions, bỏ ghép giờ kết thúc thật', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $index = [];
        foreach ($rows as $r) {
            $key = $r->machine_code . '|' . trim((string) $r->color_code) . '|' . trim((string) $r->article_code);
            // begin_time/end_time đã được model cast sang Carbon.
            $index[$key][] = ['begin' => $r->begin_time, 'end' => $r->end_time];
        }

        return $index;
    }

    /**
     * Tìm giờ kết thúc MES khớp một thanh Gantt: cùng máy+màu+mã hàng, beginTime MES gần
     * WorkStartTime nhất trong cửa sổ cho phép. Trả null nếu thiếu khoá hoặc không có ứng
     * viên nào đủ gần (khi đó thanh giữ nguyên cách tính cũ).
     *
     * @param  array<string, array<int, array{begin: Carbon, end: Carbon}>>  $index
     */
    private function matchMesEnd(array $index, string $machineCodeNorm, ?string $color, ?string $product, Carbon $start): ?Carbon
    {
        if ($machineCodeNorm === '' || $color === null || $product === null) {
            return null;
        }

        $candidates = $index[$machineCodeNorm . '|' . trim($color) . '|' . trim($product)] ?? null;
        if (!$candidates) {
            return null;
        }

        $best = null;
        $bestDiff = null;
        foreach ($candidates as $c) {
            $diff = abs($start->diffInMinutes($c['begin'], false));
            if ($diff <= self::MES_MATCH_WINDOW_MINUTES && ($bestDiff === null || $diff < $bestDiff)) {
                $bestDiff = $diff;
                $best = $c['end'];
            }
        }

        return $best;
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

        $tasksByMachineCode = [];
        $hasIdleMachine = false;
        foreach ($registry as $machineCode => $variant) {
            $tasksByMachineCode[$machineCode] = [];
            foreach ($variant['variants'] as $v) {
                foreach ($tasksByMachineId[$v['machine_id']] ?? [] as $t) {
                    $tasksByMachineCode[$machineCode][] = $t;
                }
            }
            $hasIdleMachine = $hasIdleMachine || empty($tasksByMachineCode[$machineCode]);
        }

        // Chỉ trả giá cho query aggregate 30 ngày khi thật sự có máy trống cần biết "trống
        // từ bao giờ" — lúc nhà máy chạy full thì bỏ qua hoàn toàn.
        $lastActivityByMachineId = ($bpdbConnected && $hasIdleMachine)
            ? $this->getLastActivityByMachineId($allMachineIds)
            : [];

        $machines = [];
        foreach ($registry as $machineCode => $variant) {
            $idleSince = null;
            foreach ($variant['variants'] as $v) {
                // Máy vật lý gồm nhiều dòng DyeMachines (Machine+Tank+Mức nước) — mốc
                // nhàn rỗi của máy là lần hoạt động GẦN NHẤT trong toàn bộ các tổ hợp đó.
                $candidate = $lastActivityByMachineId[$v['machine_id']] ?? null;
                if ($candidate !== null && ($idleSince === null || strcmp((string) $candidate, (string) $idleSince) > 0)) {
                    $idleSince = $candidate;
                }
            }

            $machines[] = $bpdbConnected
                ? $this->reduceMachineStatus($machineCode, $variant, $tasksByMachineCode[$machineCode], $idleSince)
                : $this->unknownStatus($machineCode, $variant);
        }

        return [
            'machines' => $machines,
            'fetched_at' => $fetchedAt->toIso8601String(),
            'bpdb_connected' => $bpdbConnected,
        ];
    }

    /**
     * Lần hoạt động gần nhất của TỪNG dòng DyeMachines, giới hạn IDLE_LOOKBACK_DAYS ngày.
     * Chỉ dùng làm mốc đếm cho máy IDLE — máy không có bản ghi nào trong cửa sổ này trả
     * về null (frontend hiển thị "> 30 ngày"), KHÔNG suy diễn thành một mốc cụ thể.
     *
     * @param  array<int|string>  $machineIds
     * @return array<int|string, string> [machine_id => 'Y-m-d H:i:s' theo giờ VN]
     */
    public function getLastActivityByMachineId(array $machineIds): array
    {
        if (empty($machineIds)) {
            return [];
        }

        return Cache::remember('bpdb_machine_last_activity', self::LAST_ACTIVITY_CACHE_TTL, function () use ($machineIds) {
            try {
                $placeholders = implode(',', array_fill(0, count($machineIds), '?'));
                $rows = $this->client->select(
                    "SELECT Machine, MAX(COALESCE(FinishTime, WorkStartTime, CreateTime)) AS LastActivity
                     FROM dbo.SUP_Tasks
                     WHERE Machine IN ($placeholders) AND CreateTime >= ?
                     GROUP BY Machine",
                    [...$machineIds, now('Asia/Ho_Chi_Minh')->subDays(self::IDLE_LOOKBACK_DAYS)->format('Y-m-d H:i:s')]
                );

                $map = [];
                foreach ($rows as $row) {
                    if (!empty($row['LastActivity'])) {
                        $map[$row['Machine']] = $row['LastActivity'];
                    }
                }

                return $map;
            } catch (\Throwable $e) {
                // Mất mốc nhàn rỗi không được làm hỏng cả bảng trạng thái — chỉ mất cột
                // "đã kéo dài" của các máy IDLE.
                Log::warning('BpdbMachineMonitoringService: cannot read last activity for idle machines', [
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Quy tắc chọn task hiện tại (mục 6 tài liệu yêu cầu):
     * PROCESSING > TRANSITIONING > WAITING > hoàn thành gần nhất > hủy gần nhất.
     * Cùng mức ưu tiên: WorkStartTime mới nhất -> CreateTime mới nhất -> Id lớn nhất.
     */
    private function reduceMachineStatus(string $machineCode, array $variant, array $tasks, ?string $idleSince = null): array
    {
        if (empty($tasks)) {
            return $this->buildResult($machineCode, $variant, 'IDLE', null, null, 0, 0, null, null, $idleSince, 'LAST_ACTIVITY');
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

        [$statusSince, $statusSinceSource] = $this->resolveStatusAnchor($operationalStatus, $primary);

        return $this->buildResult(
            $machineCode,
            $variant,
            $operationalStatus,
            $primary,
            $lastCompleted,
            count($activeTasks),
            count($waitingTasks),
            $tasks[0]['CreateTime'] ?? null,
            $warnings,
            $statusSince,
            $statusSinceSource
        );
    }

    /**
     * Mốc thời gian máy BẮT ĐẦU rơi vào trạng thái hiện tại, lấy từ chính task quyết định
     * trạng thái đó — không có mốc "đổi trạng thái" riêng trong BPDB nên phải suy từ mốc
     * gần nghĩa nhất, và trả kèm nguồn để người xem biết đồng hồ đang đếm từ đâu:
     *   PROCESSING/ERROR -> WorkStartTime (lúc máy thật sự bắt đầu chạy task)
     *   WAITING/TRANSITIONING -> CreateTime (lúc task được tạo/xếp hàng)
     *   COMPLETED_RECENTLY/CANCELLED -> FinishTime (lúc task kết thúc, máy bắt đầu trống)
     *
     * @return array{0: ?string, 1: ?string} [mốc thời gian, mã nguồn mốc]
     */
    private function resolveStatusAnchor(string $operationalStatus, array $task): array
    {
        $candidates = match ($operationalStatus) {
            'PROCESSING', 'ERROR' => [['WorkStartTime', 'WORK_START'], ['CreateTime', 'CREATE']],
            'COMPLETED_RECENTLY', 'CANCELLED' => [['FinishTime', 'FINISH'], ['CreateTime', 'CREATE']],
            default => [['CreateTime', 'CREATE']],
        };

        foreach ($candidates as [$field, $source]) {
            if (!empty($task[$field])) {
                return [$task[$field], $source];
            }
        }

        return [null, null];
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

    private function buildResult(string $machineCode, array $variant, string $operationalStatus, ?array $current, ?array $lastCompleted, int $activeCount, int $waitingCount, ?string $lastActivityAt, ?array $warning = null, ?string $statusSince = null, ?string $statusSinceSource = null): array
    {
        $statusSinceCarbon = $statusSince ? Carbon::parse($statusSince, 'Asia/Ho_Chi_Minh') : null;

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
            // Máy đã ở trạng thái hiện tại từ lúc nào / bao lâu rồi. null = không đủ bằng
            // chứng để nói (máy IDLE không có task nào trong 30 ngày, hoặc BPDB thiếu mốc
            // thời gian) — frontend phải hiển thị "không xác định", không được coi là 0.
            'statusSince' => $statusSinceCarbon?->toIso8601String(),
            'statusSinceSource' => $statusSinceCarbon ? $statusSinceSource : null,
            'statusDurationSeconds' => $statusSinceCarbon ? now()->diffInSeconds($statusSinceCarbon, true) : null,
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
            'statusSince' => null,
            'statusSinceSource' => null,
            'statusDurationSeconds' => null,
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
