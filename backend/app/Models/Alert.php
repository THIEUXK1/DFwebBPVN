<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $table = 'alerts';

    public $timestamps = false;

    protected $fillable = [
        'rule_code',
        'severity',
        'message',
        'batch_id',
        'machine_id',
        'status',
        'assigned_to',
        'resolved_by',
        'reason',
        'resolution',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(AlertRule::class, 'rule_code', 'rule_code');
    }

    public function batch()
    {
        return $this->belongsTo(ProductionBatch::class, 'batch_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
