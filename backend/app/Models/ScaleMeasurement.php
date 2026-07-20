<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ScaleMeasurement extends Model
{
    protected $table = 'scale_measurements';

    protected $keyType = 'string';
    public $incrementing = false;
    
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
        'legacy_source',
        'legacy_id',
        'legacy_batch_id',
        'color',
        'product_code',
        'machine_code',
        'level_code',
        'rack_code',
        'dye_code',
        'weight',
        'tare_weight',
        'gross_weight',
        'process_code',
        'measured_at',
        'process_color',
        'warehouse_done',
        'warehouse_time',
        'material_type',
        'weighing_job_item_id',
    ];

    public function weighingJobItem()
    {
        return $this->belongsTo(WeighingJobItem::class, 'weighing_job_item_id');
    }

    protected $casts = [
        'weight' => 'decimal:6',
        'tare_weight' => 'decimal:6',
        'gross_weight' => 'decimal:6',
        'measured_at' => 'datetime',
        'warehouse_done' => 'boolean',
        'warehouse_time' => 'datetime',
        'imported_at' => 'datetime',
    ];
}
