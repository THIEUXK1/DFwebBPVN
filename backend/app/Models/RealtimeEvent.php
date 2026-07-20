<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeEvent extends Model
{
    protected $table = 'realtime_events';

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'payload',
        'actor_id',
        'machine_id',
        'batch_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }
}
