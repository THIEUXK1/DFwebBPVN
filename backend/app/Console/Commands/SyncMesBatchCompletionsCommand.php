<?php
// backend/app/Console/Commands/SyncMesBatchCompletionsCommand.php

namespace App\Console\Commands;

use App\Models\MesBatchCompletion;
use App\Models\MesFormula;
use App\Services\Mes\MesSedoClient;
use App\Support\MachineCodeNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đồng bộ "giờ kết thúc nhuộm THẬT của mẻ" từ VN-MES (eBatchLine, status=60) về
 * app.mes_batch_completions. Gantt máy VD đọc bảng này để thay giờ kết thúc "ảo" của
 * BPDB/Sedo bằng giờ thật (xem BpdbMachineMonitoringService::buildGanttTimeline).
 *
 * Chỉ giữ các mẻ chạy trên MÁY VD (machineNo bắt đầu 'VD') — đúng phạm vi Gantt đang vẽ;
 * lọc theo tiền tố nên KHÔNG phụ thuộc BPDB (MES chết/BPDB chết độc lập nhau).
 *
 * Chạy định kỳ trên CS-SERVER qua Scheduled Task DFWeb-MesSync (mỗi 15 phút) ->
 * tools/mes-sync-batch.bat -> lệnh này; đăng ký bằng tools/register-mes-sync-task.ps1.
 * Credential MES nạp từ C:\web\tools\mes-batch-creds.bat (ngoài repo, ngoài .env).
 * Cửa sổ đồng bộ mặc định lùi 3 ngày theo endTime để bắt kịp mẻ vừa kết thúc mà không
 * phải quét lại toàn bộ.
 */
class SyncMesBatchCompletionsCommand extends Command
{
    protected $signature = 'mes:sync-batch-completions
        {--days=3 : Số ngày lùi theo endTime để đồng bộ (mặc định 3)}
        {--dry-run : Chỉ hiển thị kết quả, không ghi DB}';

    protected $description = 'Đồng bộ giờ kết thúc nhuộm thật của mẻ (máy VD) từ VN-MES vào mes_batch_completions';

    public function handle(MesSedoClient $client): int
    {
        $days = max(1, (int) $this->option('days'));
        $tz = 'Asia/Ho_Chi_Minh';
        $end = now($tz)->endOfDay();
        $start = now($tz)->subDays($days)->startOfDay();

        $this->info(sprintf(
            'Đồng bộ mẻ kết thúc từ %s đến %s (VN).',
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ));

        try {
            $rows = $client->fetchBatchCompletions(
                $start->format('Y-m-d H:i:s'),
                $end->format('Y-m-d H:i:s')
            );
        } catch (Throwable $e) {
            $this->error('Không lấy được dữ liệu MES: ' . $e->getMessage());
            Log::warning('mes:sync-batch-completions thất bại', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info('Đã tải ' . count($rows) . ' dòng mẻ kết thúc (mọi máy) từ MES.');

        $now = now();
        $payload = [];
        $pfnos = [];
        $skippedNonVd = 0;
        $skippedNoKey = 0;

        foreach ($rows as $row) {
            $machineRaw = trim((string) ($row['machineNo'] ?? ''));

            // Chỉ giữ máy VD — đúng phạm vi Gantt. Lọc theo tiền tố đã chuẩn hoá (uppercase).
            if (!str_starts_with(strtoupper($machineRaw), 'VD')) {
                $skippedNonVd++;
                continue;
            }

            $batchNo = $this->str($row['batchNo'] ?? null, 64);
            $lineNo = $this->str($row['lineNo'] ?? null, 32) ?? '1';
            $endTime = $this->parseVnTime($row['endTime'] ?? null, $tz);

            // Khoá tự nhiên = batchNo + lineNo (field `id` của MES luôn rỗng); thiếu batchNo
            // hoặc thiếu endTime (lý do tồn tại của bảng) thì bỏ.
            if ($batchNo === null || $endTime === null) {
                $skippedNoKey++;
                continue;
            }

            $payload[$batchNo . '|' . $lineNo] = [
                'batch_no' => $batchNo,
                'line_no' => $lineNo,
                'machine_code' => MachineCodeNormalizer::normalize($machineRaw),
                'machine_no_raw' => $this->str($machineRaw, 32),
                'color_code' => $this->str($row['colorCode'] ?? null, 100),
                'article_code' => $this->str($row['artCode'] ?? null, 100),
                'order_ucode' => $this->str($row['orderUcode'] ?? null, 64),
                'begin_time' => $this->parseVnTime($row['beginTime'] ?? null, $tz),
                'end_time' => $endTime,
                'end_by_name' => $this->str($row['endByName'] ?? null, 100),
                'shift' => $this->str($row['clShift'] ?? null, 16),
                'manu_step' => $this->str($row['manuStep'] ?? null, 32),
                'status' => $this->str($row['status'] ?? '60', 16),
                // Nguyên cả dòng MES (JSONB) — phục vụ popup "xem toàn bộ thông tin mẻ".
                // upsert() bỏ qua cast của model nên phải tự json_encode ở đây.
                'raw' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'source' => 'MES_EBATCHLINE',
                'synced_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Gom số công thức để đồng bộ bảng liệu (mes_formulas) sau khi ghi mẻ xong.
            $pfno = trim((string) ($row['pfmPfno'] ?? ''));
            if ($pfno !== '') {
                $pfnos[] = $pfno;
            }
        }

        $this->line(sprintf(
            'Mẻ VD giữ lại: %d  |  bỏ (không phải VD): %d  |  bỏ (thiếu id/endTime): %d',
            count($payload),
            $skippedNonVd,
            $skippedNoKey
        ));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: KHÔNG ghi DB.');
            foreach (array_slice($payload, 0, 15) as $p) {
                $this->line(sprintf(
                    '  %-8s %-12s %-12s begin=%s end=%s',
                    $p['machine_code'],
                    (string) $p['color_code'],
                    (string) $p['article_code'],
                    optional($p['begin_time'])->format('Y-m-d H:i') ?? '—',
                    optional($p['end_time'])->format('Y-m-d H:i')
                ));
            }

            return self::SUCCESS;
        }

        if ($payload === []) {
            $this->warn('Không có mẻ VD nào để ghi — giữ nguyên dữ liệu cũ.');

            return self::SUCCESS;
        }

        // upsert theo (batch_no, line_no) — chạy lại nhiều lần không sinh trùng.
        foreach (array_chunk(array_values($payload), 500) as $chunk) {
            MesBatchCompletion::upsert(
                $chunk,
                ['batch_no', 'line_no'],
                [
                    'machine_code', 'machine_no_raw', 'color_code',
                    'article_code', 'order_ucode', 'begin_time', 'end_time', 'end_by_name',
                    'shift', 'manu_step', 'status', 'raw', 'source', 'synced_at', 'updated_at',
                ]
            );
        }

        $this->info('Đã ghi ' . count($payload) . ' mẻ VD vào mes_batch_completions.');

        // Đồng bộ công thức (thuốc nhuộm + hóa chất) cho các mẻ vừa ghi — chỉ lấy công thức
        // còn thiếu/cũ để giới hạn số lần gọi MES mỗi lần chạy. Bọc riêng: lỗi ở đây (vd bảng
        // mes_formulas chưa migrate) KHÔNG được làm hỏng việc ghi mẻ vốn đã thành công ở trên.
        try {
            $this->syncFormulas($client, $pfnos);
        } catch (Throwable $e) {
            $this->warn('Đồng bộ công thức lỗi (bỏ qua): ' . $e->getMessage());
            Log::warning('mes:sync-batch-completions đồng bộ công thức lỗi tổng', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }

    /**
     * Đồng bộ công thức MES (mesPfM) cho các pfmPfno cho trước vào bảng mes_formulas.
     * Chỉ gọi MES cho công thức CHƯA có hoặc đã đồng bộ quá 7 ngày — công thức gần như bất
     * biến nên sau lần đổ đầu, mỗi lần chạy chỉ còn vài mã mới.
     *
     * @param array<int,string> $pfnos
     */
    private function syncFormulas(MesSedoClient $client, array $pfnos): void
    {
        $fromRun = array_values(array_filter(array_unique($pfnos)));

        // Ứng viên = công thức của mẻ vừa đồng bộ + công thức của MỌI mẻ đã lưu (Gantt hiển
        // thị dải rộng hơn cửa sổ 3 ngày, nên phải backfill dần cho mẻ cũ mới đủ phủ).
        $allInBatches = DB::table('mes_batch_completions')
            ->selectRaw("DISTINCT NULLIF(trim(raw->>'pfmPfno'), '') AS pfno")
            ->whereRaw("NULLIF(trim(raw->>'pfmPfno'), '') IS NOT NULL")
            ->pluck('pfno')
            ->all();

        $candidates = array_values(array_unique(array_merge($fromRun, $allInBatches)));
        if ($candidates === []) {
            return;
        }

        // Bỏ công thức đã đồng bộ trong 7 ngày (gần như bất biến, khỏi gọi lại MES).
        $fresh = MesFormula::whereIn('pfm_pfno', $candidates)
            ->where('synced_at', '>=', now()->subDays(7))
            ->pluck('pfm_pfno')
            ->all();
        $todo = array_values(array_diff($candidates, $fresh));

        // Ưu tiên công thức của mẻ vừa đồng bộ trước, rồi tới backfill; giới hạn mỗi lần chạy
        // để không nện MES quá lâu (các lần chạy 15 phút sau sẽ backfill tiếp phần còn lại).
        $todo = array_values(array_unique(array_merge(
            array_values(array_intersect($fromRun, $todo)),
            $todo
        )));
        $cap = 80;
        $capped = array_slice($todo, 0, $cap);

        $this->info(sprintf(
            'Công thức: %d mã cần đồng bộ (làm %d lần này, còn %d để lần sau).',
            count($todo),
            count($capped),
            max(0, count($todo) - count($capped))
        ));

        if ($capped === []) {
            return;
        }

        $now = now();
        $ok = 0;
        $miss = 0;

        foreach ($capped as $pfno) {
            try {
                $f = $client->fetchFormulaByPfno($pfno);
            } catch (Throwable $e) {
                Log::warning('mes:sync-batch-completions đồng bộ công thức lỗi', [
                    'pfmPfno' => $pfno,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($f === null) {
                $miss++;
                continue;
            }

            $dye = 0;
            $aux = 0;
            foreach ($f['materials'] as $m) {
                if (($m['kind'] ?? '') === 'aux') {
                    $aux++;
                } else {
                    $dye++;
                }
            }

            MesFormula::updateOrCreate(
                ['pfm_pfno' => $pfno],
                [
                    'formula_id' => $this->str($f['formulaId'] ?? null, 64),
                    'color_code' => $this->str($f['colorCode'] ?? null, 100),
                    'article_code' => $this->str($f['articleCode'] ?? null, 100),
                    'machine_code' => $this->str($f['machineCode'] ?? null, 32),
                    'dye_count' => $dye,
                    'aux_count' => $aux,
                    'materials' => $f['materials'],
                    'raw' => $f['raw'],
                    'process' => $f['process'] ?? null,
                    'source' => 'MES_MESPFM',
                    'synced_at' => $now,
                ]
            );
            $ok++;
        }

        $this->info(sprintf('Công thức đã đồng bộ: %d ghi, %d không tìm thấy.', $ok, $miss));
    }

    /** Cắt độ dài an toàn để không vỡ cột varchar; trả null nếu rỗng. */
    private function str(mixed $value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /**
     * MES trả 'Y-m-d H:i:s' theo giờ VN; parse gắn đúng tz, null nếu rỗng/không hợp lệ.
     *
     * LƯU Ý về cách mốc này nằm trong DB: upsert() ghi thẳng qua query builder nên Carbon bị
     * format 'Y-m-d H:i:s' KHÔNG kèm offset, và session Postgres bị ép UTC — tức cột
     * timestamptz đang giữ GIỜ TƯỜNG Việt Nam bị gán nhãn UTC (lệch 7 tiếng). Phía đọc đã bù
     * lại ở accessor của MesBatchCompletion; đổi cách ghi ở đây thì PHẢI sửa cả accessor đó.
     */
    private function parseVnTime(mixed $value, string $tz): ?Carbon
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $tz);
        } catch (Throwable) {
            return null;
        }
    }
}
