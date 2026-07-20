<?php
// backend/app/Models/LegacyExceptionQueueItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegacyExceptionQueueItem extends Model
{
    protected $table = 'legacy_exception_queue_items';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'reason',
        'resolved_at',
        'resolution',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
