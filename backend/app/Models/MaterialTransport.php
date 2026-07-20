<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransport extends Model
{
    protected $table = 'material_transports';

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
        'batch_id',
        'workstation_id',
        'status',
        'sla_minutes',
        'started_at',
        'arrived_at',
        'delay_reason',
        'weighing_job_id',
        'material_label_id',
    ];

    public function weighingJob()
    {
        return $this->belongsTo(WeighingJob::class, 'weighing_job_id');
    }

    public function materialLabel()
    {
        return $this->belongsTo(MaterialLabel::class, 'material_label_id');
    }

    protected $casts = [
        'started_at' => 'datetime',
        'arrived_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function events()
    {
        return $this->hasMany(MaterialTransportEvent::class, 'transport_id');
    }
}
