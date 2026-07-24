<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialLabel extends Model
{
    protected $table = 'material_labels';

    protected $keyType = 'string';
    public $incrementing = false;
    
    public $timestamps = false;

    protected $fillable = [
        'production_batch_id',
        'weighing_job_id',
        'material_type',
        'material_code',
        'weight',
        'reprint_count',
        'reprint_reason',
    ];

    protected $casts = [
        'weight' => 'float',
        'reprint_count' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function job()
    {
        return $this->belongsTo(WeighingJob::class, 'weighing_job_id');
    }

    public function material()
    {
        return $this->belongsTo(Material::class, 'material_code', 'code');
    }
}
