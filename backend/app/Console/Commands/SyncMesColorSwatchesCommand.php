<?php
// backend/app/Console/Commands/SyncMesColorSwatchesCommand.php

namespace App\Console\Commands;

use App\Models\ColorSwatch;
use App\Services\Mes\MesSedoClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đồng bộ bảng tra "mã màu -> màu hiển thị thật" từ VN-MES về app.color_swatches.
 * Chạy định kỳ (xem routes/console.php); Gantt chỉ đọc bảng này nên MES chết vẫn còn màu.
 */
class SyncMesColorSwatchesCommand extends Command
{
    protected $signature = 'mes:sync-color-swatches {--dry-run : Chỉ hiển thị kết quả, không ghi DB}';

    protected $description = 'Đồng bộ mã màu + màu RGB thật từ VN-MES (Sedo) vào bảng color_swatches';

    /**
     * Màu mặc định "chưa gán màu" của MES (#DDDDF0) — chiếm phần lớn bản ghi, không phải
     * màu nhuộm thật nên phải loại, nếu không mọi mẻ sẽ hiện chung một màu tím nhạt.
     */
    private const MES_UNSET_COLOR = 15785437;

    /** Tên trường trong dữ liệu MES — theo getLocalData() của ganttDrawerSVG.js. */
    private const FIELD_COLOR_CODE = 'cText09';
    private const FIELD_ARTICLE_CODE = 'cText06';
    private const FIELD_RGB = 'cRgbcolor';

    public function handle(MesSedoClient $client): int
    {
        try {
            $rows = $client->fetchGanttRows();
        } catch (Throwable $e) {
            $this->error('Không lấy được dữ liệu MES: ' . $e->getMessage());
            Log::warning('mes:sync-color-swatches thất bại', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        $this->info('Đã tải ' . count($rows) . ' bản ghi từ MES.');

        // Gom theo mã màu. Bản ghi MES sắp xếp theo thời gian tăng dần nên ghi đè tuần tự
        // sẽ giữ lại lần xuất hiện MỚI NHẤT của mỗi mã màu — đúng thứ ta muốn nếu màu của
        // một mã màu từng được hiệu chỉnh lại bên MES.
        $swatches = [];
        $skippedUnset = 0;
        $skippedNoCode = 0;

        foreach ($rows as $row) {
            $code = trim((string) ($row[self::FIELD_COLOR_CODE] ?? ''));
            if ($code === '') {
                $skippedNoCode++;
                continue;
            }

            $raw = $row[self::FIELD_RGB] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            $value = (int) $raw;
            if ($value === self::MES_UNSET_COLOR) {
                $skippedUnset++;
                continue;
            }

            $swatches[$code] = [
                'color_code' => $code,
                'rgb_hex' => MesSedoClient::bgrToHex($value),
                'source_value' => $value,
                'source' => 'MES_SEDO',
                'sample_article_code' => trim((string) ($row[self::FIELD_ARTICLE_CODE] ?? '')) ?: null,
            ];
        }

        $this->line(sprintf(
            'Mã màu có màu thật: %d  |  bỏ qua (màu mặc định): %d  |  bỏ qua (không có mã màu): %d',
            count($swatches),
            $skippedUnset,
            $skippedNoCode
        ));

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: KHÔNG ghi DB.');
            foreach (array_slice($swatches, 0, 15, true) as $code => $s) {
                $this->line('  ' . str_pad($code, 16) . ' -> ' . $s['rgb_hex']);
            }

            return self::SUCCESS;
        }

        if ($swatches === []) {
            $this->warn('Không có mã màu nào để ghi — giữ nguyên dữ liệu cũ.');

            return self::SUCCESS;
        }

        $now = now();
        $payload = array_map(
            fn (array $s) => $s + ['synced_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            array_values($swatches)
        );

        // upsert theo color_code (unique) — chạy lại nhiều lần không sinh trùng.
        foreach (array_chunk($payload, 500) as $chunk) {
            ColorSwatch::upsert(
                $chunk,
                ['color_code'],
                ['rgb_hex', 'source_value', 'source', 'sample_article_code', 'synced_at', 'updated_at']
            );
        }

        $this->info('Đã ghi ' . count($payload) . ' mã màu vào color_swatches.');

        return self::SUCCESS;
    }
}
