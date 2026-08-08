<?php
// backend/app/Console/Commands/SyncMesBatchCompletionsCommand.php

namespace App\Console\Commands;

use App\Models\MesBatchCompletion;
use App\Services\Mes\MesSedoClient;
use App\Support\MachineCodeNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
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
 * Chạy định kỳ (đăng ký cùng chỗ với mes:sync-color-swatches). Cửa sổ đồng bộ mặc định
 * lùi 3 ngày theo endTime để bắt kịp mẻ vừa kết thúc mà không phải quét lại toàn bộ.
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

        return self::SUCCESS;
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

    /** MES trả 'Y-m-d H:i:s' theo giờ VN; parse gắn đúng tz, null nếu rỗng/không hợp lệ. */
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
