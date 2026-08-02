<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WeighingJobItem extends Model
{
    protected $table = 'weighing_job_items';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'weighing_job_id',
        'material_code',
        'planned_weight',
        'tolerance_minus',
        'tolerance_plus',
        'sequence_no',
        'rack_code',
        'actual_weight',
        'status',
        'label_id',
        'completed_at',
        'override_approved',
        'override_reason',
        'override_by',
    ];

    protected $casts = [
        'planned_weight' => 'float',
        'tolerance_minus' => 'float',
        'tolerance_plus' => 'float',
        'actual_weight' => 'float',
        'sequence_no' => 'integer',
        'completed_at' => 'datetime',
        'override_approved' => 'boolean',
    ];

    protected $appends = ['process_status'];

    /**
     * Mã vật tư mồi cho dòng CÂN TAY (cân không quét đơn).
     *
     * Phải có một mã thật vì `weighing_job_items.material_code` là NOT NULL và có khoá ngoại tới
     * `materials.code` — không để trống được dù cân tay chẳng có vật tư nào. Cũng chính là dấu
     * nhận biết dòng cân tay ở `process_status` và ở báo cáo.
     */
    public const MANUAL_MATERIAL_CODE = 'CANTAY';

    /**
     * Nhãn ĐẠT/KHÔNG ĐẠT của dòng cân — tương đương cột `processColor` trong Access
     * (VBA btnSave_Click ghi ACCEPTED/REJECTED theo màu nền ô txt_process).
     *
     * SUY RA, không lưu cột riêng: cả 3 giá trị nguồn (planned_weight, tolerance_minus,
     * tolerance_plus) đều được snapshot lên chính item lúc quét QR, nên nhãn tái dựng được
     * y nguyên về sau kể cả khi công thức hay hằng dung sai của hệ thống thay đổi.
     */
    public function getProcessStatusAttribute(): string
    {
        if ($this->status !== 'COMPLETED') {
            return 'PENDING';
        }

        // Dòng CÂN TAY (bấm NEXT cân luôn, không quét đơn nào): không có mục tiêu để so, nên
        // không tồn tại khái niệm ĐẠT/KHÔNG ĐẠT. Trả nhãn riêng thay vì để rơi xuống nhánh dưới
        // — `planned_weight` = 0 sẽ khiến mọi dòng cân tay hiện KHÔNG ĐẠT, mà đó là nói SAI chứ
        // không phải để trống.
        //
        // Nhận qua `material_code` chứ không qua `planned_weight <= 0`: mã mồi này chỉ dòng cân
        // tay mới có, nên không bản ghi cũ nào bị đổi nhãn (có thể có dòng QR mục tiêu 0 do tem
        // hỏng — chúng vẫn giữ nguyên KHÔNG ĐẠT như trước).
        if ($this->material_code === self::MANUAL_MATERIAL_CODE) {
            return 'MANUAL';
        }

        // Đã chốt mẻ (COMPLETED) nhưng không có số cân nào = dòng bị bỏ qua, không cân. Port
        // đúng VBA btnSave_Click: mọi dòng có WEIGHT mục tiêu đều được ghi, ô PROCESS trống
        // thì nền không xanh nên `processColor` = REJECTED. Nhánh này chỉ xảy ra ở luồng lưu
        // cả mẻ (/weighing-station-v2) — luồng lưu từng dòng luôn có actual_weight.
        if ($this->actual_weight === null) {
            return 'REJECTED';
        }

        $min = (float) $this->planned_weight - (float) $this->tolerance_minus;
        $max = (float) $this->planned_weight + (float) $this->tolerance_plus;

        return ((float) $this->actual_weight >= $min && (float) $this->actual_weight <= $max)
            ? 'ACCEPTED'
            : 'REJECTED';
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function job()
    {
        return $this->belongsTo(WeighingJob::class, 'weighing_job_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_code', 'code');
    }

    public function measurements()
    {
        return $this->hasMany(ScaleMeasurement::class, 'weighing_job_item_id');
    }

    public function label()
    {
        return $this->belongsTo(MaterialLabel::class, 'label_id');
    }

    public function overrideByUser()
    {
        return $this->belongsTo(User::class, 'override_by');
    }
}
