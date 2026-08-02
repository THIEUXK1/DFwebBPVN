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
    /**
     * $quantityOverride: quantity KHÔNG cố định theo công thức — xác nhận từ dữ liệu
     * thật VD006 (2 thùng cùng công thức khác nhau vẫn có thể ra 2 QR khác total nếu
     * quantity riêng khác nhau... thực ra VD006 cả 2 thùng cùng 240, nhưng nguyên tắc là
     * quantity thuộc về THÙNG, không thuộc về công thức). Dùng quantity riêng của thùng
     * (machine_chemical_channels.quantity) nếu có, fallback về quantity mặc định của
     * công thức khi thùng chưa xác nhận số thật.
     */
    public function buildQrText(?int $quantityOverride = null): string
    {
        $lines = [
            "{$this->code_1}-{$this->code_2}",
            $this->formatNumber($this->dosing_step),
            $this->formatNumber($quantityOverride ?? $this->quantity),
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
        $key = static::combinedCodeKey($combined);
        if ($key === null) {
            return null;
        }

        [$code1, $code2] = explode('|', $key, 2);

        return static::where('code_1', $code1)
            ->where('code_2', $code2 === '' ? null : $code2)
            ->first();
    }

    /**
     * Khóa tra cứu chuẩn hóa từ mã ghép trên thùng: "<code_1>|<code_2>" (code_2 rỗng khi
     * thùng chỉ có 1 mã). Tách riêng để lookupByCombinedCode() và indexedByCode() dùng
     * CHUNG một cách tách chuỗi — hai nơi tách khác nhau là tra trượt mà không báo lỗi.
     */
    public static function combinedCodeKey(?string $combined): ?string
    {
        if (!$combined) {
            return null;
        }

        $parts = array_map(
            fn ($p) => preg_replace('/\s+/', '', $p),
            explode('+', $combined, 2)
        );

        return ($parts[0] ?? '').'|'.($parts[1] ?? '');
    }

    /**
     * Toàn bộ công thức nạp 1 lần, đánh khóa theo combinedCodeKey() — dùng khi phải tra cho
     * cả danh sách thùng (getChannels), thay cho việc gọi lookupByCombinedCode() từng thùng
     * (mỗi lần là 1 truy vấn riêng, rất tốn khi DB ở máy chủ khác).
     */
    public static function indexedByCode(): \Illuminate\Support\Collection
    {
        return static::all()->keyBy(fn (self $g) => $g->code_1.'|'.($g->code_2 ?? ''));
    }
}
