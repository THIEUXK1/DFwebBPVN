<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ProductionBatch extends Model
{
    protected $table = 'production_batches';

    /**
     * Tiền tố `legacy_batch_id` của lô sinh ra bởi CÂN TAY — cân không quét đơn nào.
     *
     * Đây KHÔNG phải lệnh sản xuất: không màu, không mã hàng, không máy, không ai đặt hàng. Nó
     * chỉ tồn tại vì `weighing_jobs.production_batch_id` là NOT NULL nên vòng cân buộc phải gắn
     * vào một lô nào đó.
     *
     * Phân biệt với tiền tố "ADHOC-": lô ADHOC ĐẾN TỪ MỘT TEM QR thật (chỉ là chưa khớp lô nào
     * trong Web), nên nó vẫn là việc sản xuất và vẫn phải nằm trong báo cáo. Lô CÂN TAY thì
     * không liên quan gì tới quét đơn cả.
     */
    public const MANUAL_BATCH_PREFIX = 'CANTAY-';

    /**
     * Loại lô CÂN TAY ra khỏi mọi danh sách/thống kê sản xuất.
     *
     * Phải viết dạng "NULL HOẶC không khớp" chứ không chỉ `not like`: `legacy_batch_id` là
     * nullable, mà trong SQL `NULL NOT LIKE '...'` cho ra NULL (không phải TRUE) — dùng `not like`
     * trần sẽ ném luôn mọi lô không có mã cũ ra khỏi báo cáo.
     */
    public function scopeKhongPhaiCanTay($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('legacy_batch_id')
                ->orWhere('legacy_batch_id', 'not like', self::MANUAL_BATCH_PREFIX.'%');
        });
    }
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    // Disable Laravel default timestamps since we only have created_at in the legacy design
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'legacy_batch_id',
        'color',
        'product_code',
        'machine_id',
        'tank_id',
        'level_code',
        'status',
        'cloth_weight',
        'raw_qr_dye',
        'raw_qr_chemical',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cloth_weight' => 'float',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function tank()
    {
        return $this->belongsTo(Tank::class, 'tank_id');
    }

    public function dispatches()
    {
        return $this->hasMany(MachineDispatch::class, 'batch_id');
    }
}
