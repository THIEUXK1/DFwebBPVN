<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    protected $table = 'alert_rules';

    protected $fillable = [
        'rule_code',
        'name',
        'severity',
        'threshold_seconds',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];
}
