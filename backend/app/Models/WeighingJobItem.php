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
     * Nhãn ĐẠT/KHÔNG ĐẠT của dòng cân — tương đương cột `processColor` trong Access
     * (VBA btnSave_Click ghi ACCEPTED/REJECTED theo màu nền ô txt_process).
     *
     * SUY RA, không lưu cột riêng: cả 3 giá trị nguồn (planned_weight, tolerance_minus,
     * tolerance_plus) đều được snapshot lên chính item lúc quét QR, nên nhãn tái dựng được
     * y nguyên về sau kể cả khi công thức hay hằng dung sai của hệ thống thay đổi.
     */
    public function getProcessStatusAttribute(): string
    {
        if ($this->status !== 'COMPLETED' || $this->actual_weight === null) {
            return 'PENDING';
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
