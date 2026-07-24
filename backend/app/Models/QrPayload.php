<?php
// backend/app/Models/QrPayload.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QrPayload extends Model
{
    protected $table = 'qr_payloads';
    
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'dispatch_id',
        'payload_version',
        'payload_type',
        'raw_payload',
        'payload_hash',
        'routing_decision_id',
        'template_version',
        'source_record_id',
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

    public function dispatch()
    {
        return $this->belongsTo(MachineDispatch::class, 'dispatch_id');
    }

    public function routingDecision()
    {
        return $this->belongsTo(RoutingDecision::class, 'routing_decision_id');
    }
}
