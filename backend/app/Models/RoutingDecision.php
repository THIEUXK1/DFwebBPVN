<?php
// backend/app/Models/RoutingDecision.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoutingDecision extends Model
{
    protected $table = 'routing_decisions';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dispatch_id',
        'mode',
        'route',
        'area_label',
        'matched_rule',
        'rule_version',
        'input_snapshot',
        'warnings',
        'needs_manual_review',
        'decided_at',
    ];

    protected $casts = [
        'input_snapshot' => 'array',
        'warnings' => 'array',
        'needs_manual_review' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }
}
