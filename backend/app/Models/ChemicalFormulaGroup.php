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
}
