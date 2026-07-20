<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DispatchEvent extends Model
{
    protected $table = 'dispatch_events';

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
        'dispatch_id',
        'event_type',
        'color',
        'code',
        'machine_id',
        'tank',
        'level',
        'confirm_1',
        'confirm_2',
        'is_sent',
        'scale_checked',
        'raw_qr_dye',
        'raw_qr_chemical',
        'occurred_at',
        'actor_user_id',
        'legacy_source',
        'legacy_id',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_sent' => 'boolean',
        'scale_checked' => 'boolean',
    ];

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function actorUser()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
