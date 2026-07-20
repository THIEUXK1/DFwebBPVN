<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FeedOperation extends Model
{
    protected $table = 'feed_operations';

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
        'operator_id',
        'water_verified',
        'materials_verified',
        'override_approved',
        'override_approved_by',
        'override_reason',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'water_verified' => 'boolean',
        'materials_verified' => 'boolean',
        'override_approved' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function overrideApprovedBy()
    {
        return $this->belongsTo(User::class, 'override_approved_by');
    }
}
