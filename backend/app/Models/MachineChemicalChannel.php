<?php

namespace App\Models;

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
        $filename = "QR_{$this->machine->code}_{$combo}.jpg";

        if (!file_exists(public_path("chemical-qr/{$filename}"))) {
            return null;
        }

        // rawurlencode filename (KHÔNG encode cả path) — dấu "+" thô trong URL bị server
        // hiểu sai (không khớp tên file, rơi về route mặc định của Laravel thay vì trả
        // đúng ảnh); encode thành "%2B" thì browser tải đúng file.
        return '/chemical-qr/' . rawurlencode($filename);
    }
}
