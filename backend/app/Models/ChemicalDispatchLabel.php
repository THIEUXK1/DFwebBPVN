<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalDispatchLabel extends Model
{
    protected $table = 'chemical_dispatch_labels';

    protected $fillable = [
        'channel_id',
        'dosing_step_1',
        'dosing_step_2',
        'quantity',
        'total_weight_1',
        'total_weight_2',
        'legacy_id',
    ];

    protected $casts = [
        'dosing_step_1' => 'integer',
        'dosing_step_2' => 'integer',
        'quantity' => 'integer',
        'total_weight_1' => 'float',
        'total_weight_2' => 'float',
    ];

    public function channel()
    {
        return $this->belongsTo(MachineChemicalChannel::class, 'channel_id');
    }

    /**
     * Thùng (machine_chemical_channels.chemical_code) đã tự mang sẵn "mã1 + mã2" (vd
     * "AC77 + AC78") — B6/B7 gốc suy ra trực tiếp từ đây, không lưu trùng lặp trong bảng này.
     *
     * @return array{0: string, 1: string}
     */
    public function splitChemicalCode(): array
    {
        $raw = $this->channel ? (string) $this->channel->chemical_code : '';
        // preg_replace bỏ TOÀN BỘ khoảng trắng (không chỉ 2 đầu) — dữ liệu thật có lỗi
        // gõ dư dấu cách giữa mã (vd "AC 78" thay vì "AC78"), không mã tra cứu nào trong
        // chemical_weight_references có khoảng trắng hợp lệ nên strip an toàn.
        $parts = array_map(
            fn ($p) => preg_replace('/\s+/', '', $p),
            explode('+', $raw, 2)
        );

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * Tái tạo đúng 9 dòng giá trị thô (không nhãn) mà Mod_MAKE_QR.TaoQR_chemical gốc
     * ghép từ B1,B2,B3,B4,B5,A6,C6,A7,C7 — A6/A7 tra qua ChemicalWeightReference giống
     * VLOOKUP(...,semi!E:F,2), IFERROR về 0 nếu không tìm thấy mã.
     */
    public function buildQrText(): string
    {
        $machineCode = ($this->channel && $this->channel->machine) ? $this->channel->machine->code : '';
        [$code1, $code2] = $this->splitChemicalCode();

        $unitWeight1 = ChemicalWeightReference::where('code', $code1)->value('unit_weight') ?? 0;
        $unitWeight2 = ChemicalWeightReference::where('code', $code2)->value('unit_weight') ?? 0;

        $lines = [
            $machineCode,
            $this->formatNumber($this->dosing_step_1),
            "{$code1}+{$code2}",
            $this->formatNumber($this->dosing_step_2),
            $this->formatNumber($this->quantity),
            $this->formatNumber($unitWeight1),
            $this->formatNumber($this->total_weight_1),
            $this->formatNumber($unitWeight2),
            $this->formatNumber($this->total_weight_2),
        ];

        return implode("\n", $lines);
    }

    private function formatNumber($value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }
}
