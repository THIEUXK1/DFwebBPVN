<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChemicalFormulaGroup extends Model
{
    protected $table = 'chemical_formula_groups';

    protected $fillable = [
        'code_1',
        'code_2',
        'dosing_step',
        'quantity',
        'unit_weight_1',
        'total_weight_1',
        'unit_weight_2',
        'total_weight_2',
        'legacy_id',
    ];

    protected $casts = [
        'dosing_step' => 'integer',
        'quantity' => 'integer',
        'unit_weight_1' => 'float',
        'total_weight_1' => 'float',
        'unit_weight_2' => 'float',
        'total_weight_2' => 'float',
    ];

    /**
     * Tái tạo đúng QR thật đang dán ở xưởng: "#<code1>-<code2>\r\n<step>\r\n<qty>\r\n
     * <unit1>\r\n<total1>[\r\n<unit2>\r\n<total2>]#". Giá trị unit_weight lưu thẳng
     * (không tra qua ChemicalWeightReference) — QR thật là nguồn sự thật, đã phát hiện
     * ít nhất 1 nhóm (AC123+AC122) lệch với kết quả tra bảng "semi".
     */
    public function buildQrText(): string
    {
        $lines = [
            "{$this->code_1}-{$this->code_2}",
            $this->formatNumber($this->dosing_step),
            $this->formatNumber($this->quantity),
            $this->formatNumber($this->unit_weight_1),
            $this->formatNumber($this->total_weight_1),
        ];

        if ($this->code_2 !== null && $this->code_2 !== '') {
            $lines[] = $this->formatNumber($this->unit_weight_2);
            $lines[] = $this->formatNumber($this->total_weight_2);
        }

        return '#' . implode("\r\n", $lines) . '#';
    }

    private function formatNumber($value): string
    {
        if ($value === null) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.');
    }

    /**
     * Tra công thức từ mã ghép trên thùng (machine_chemical_channels.chemical_code, vd
     * "AC77 + AC78") — thùng KHÔNG cố định 1 công thức duy nhất (cùng 1 thùng có thể lần
     * lượt chạy nhiều công thức khác nhau qua các lô khác nhau, xác nhận từ ảnh bảng giấy
     * thật ở xưởng), nên không cần bảng mapping máy<->công thức riêng: chemical_code hiện
     * tại của thùng CHÍNH LÀ công thức đang active, chỉ cần tách mã rồi tra thẳng ra đây.
     */
    public static function lookupByCombinedCode(?string $combined): ?self
    {
        if (!$combined) {
            return null;
        }

        $parts = array_map(
            fn ($p) => preg_replace('/\s+/', '', $p),
            explode('+', $combined, 2)
        );

        $code1 = $parts[0] ?? '';
        $code2 = $parts[1] ?? null;

        return static::where('code_1', $code1)
            ->where('code_2', $code2 === '' ? null : $code2)
            ->first();
    }
}
