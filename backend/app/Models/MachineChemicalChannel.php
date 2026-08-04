<?php

namespace App\Models;

use App\Services\QrPayloadService;
use Illuminate\Database\Eloquent\Model;

class MachineChemicalChannel extends Model
{
    protected $table = 'machine_chemical_channels';
    
    public $timestamps = false;

    protected $fillable = [
        'machine_id',
        'channel_number',
        'chemical_code',
        'quantity',
        'is_active',
        'legacy_id',
    ];

    protected $casts = [
        'channel_number' => 'integer',
        'quantity' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    /**
     * Ảnh QR THẬT (chụp/xuất trực tiếp từ tem in ở xưởng, xem QR.rar 2026-07-28) — ưu
     * tiên hơn hẳn việc dựng lại text rồi sinh QR bằng chemical_formula_groups: đã phát
     * hiện 2 lần dữ liệu đọc từ ảnh bảng giấy sai (quantity 150 vs 240 thật, unit_weight_2
     * 40 vs 17 thật) — ảnh thật loại bỏ hoàn toàn rủi ro này. File đặt tên
     * "QR_{mã máy}_{mã hóa chất ghép}.jpg" (vd "QR_VD006_AC77+AC78.jpg"), lưu tĩnh ở
     * public/chemical-qr/. Trả null nếu thùng này chưa có ảnh thật tương ứng.
     */
    public function qrImageUrl(): ?string
    {
        if (!$this->chemical_code || !$this->machine) {
            return null;
        }

        $parts = array_filter(array_map(
            fn ($p) => preg_replace('/\s+/', '', $p),
            explode('+', $this->chemical_code, 2)
        ), fn ($p) => $p !== '');

        $combo = implode('+', $parts);
        // Tên file trên đĩa dùng mã 3 chữ số ("QR_VD006_...") vì ảnh được xuất từ tem in ở
        // xưởng theo định dạng QR. Danh mục máy nay dùng mã 2 chữ số VD01-VD18 đúng arrVD của
        // VBA gốc (mainform.CommandButton5_Click), nên PHẢI chuẩn hóa lại khi tra tên file —
        // nếu không toàn bộ 38 ảnh QR thật trả null và màn Gọi hóa chất mất sạch mã QR.
        $machineCode = app(QrPayloadService::class)->normalizeVdCode($this->machine->code);
        $filename = "QR_{$machineCode}_{$combo}.jpg";

        if (!isset(static::danhSachAnhQr()[$filename])) {
            return null;
        }

        // rawurlencode filename (KHÔNG encode cả path) — dấu "+" thô trong URL bị server
        // hiểu sai (không khớp tên file, rơi về route mặc định của Laravel thay vì trả
        // đúng ảnh); encode thành "%2B" thì browser tải đúng file.
        return '/chemical-qr/' . rawurlencode($filename);
    }

    private static ?array $anhQrCache = null;

    /**
     * Tên các file ảnh QR hiện có, đọc thư mục ĐÚNG 1 LẦN cho mỗi request thay vì gọi
     * file_exists() cho từng thùng (getChannels duyệt toàn bộ thùng, và trang tự tải lại
     * mỗi 10 giây). Cache chỉ sống trong 1 request nên thêm/bớt ảnh vẫn nhận ra ở lần tải
     * kế tiếp, không cần xóa cache thủ công.
     */
    private static function danhSachAnhQr(): array
    {
        if (static::$anhQrCache === null) {
            $files = @scandir(public_path('chemical-qr'));
            static::$anhQrCache = array_fill_keys($files ?: [], true);
        }

        return static::$anhQrCache;
    }
}
