<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    protected $table = 'audit_logs';
    
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'before_data',
        'after_data',
        'client_ip',
        
        // New Kiosk audit columns
        'actor_type',
        'actor_id',
        'kiosk_session_id',
        'device_id',
        'agent_id',
        'correlation_id',
        'timestamp'
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'created_at' => 'datetime',
        'timestamp' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->timestamp = $model->timestamp ?? now();

            if (request()) {
                $session = request()->attributes->get('kiosk_session');
                $client = request()->attributes->get('operation_client');

                if ($session && $client) {
                    $model->actor_type = 'KIOSK_CLIENT';
                    $model->actor_id = (string)$client->id;
                    $model->kiosk_session_id = $session->id;
                    
                    if (!$model->client_ip) {
                        $model->client_ip = request()->ip();
                    }

                    if (!$model->correlation_id) {
                        $model->correlation_id = request()->header('X-Correlation-ID') ?: (string)Str::uuid();
                    }

                    // Extract device/agent ids from request context if present
                    $model->device_id = $model->device_id ?? request()->header('X-Device-ID') ?? request()->input('device_id');
                    $model->agent_id = $model->agent_id ?? request()->header('X-Agent-ID') ?? request()->input('agent_id');
                } else {
                    $model->actor_type = 'USER';
                    $user_id = auth()->id() ?? $model->user_id;
                    $model->actor_id = $user_id ? (string)$user_id : null;
                    $model->user_id = $user_id;

                    if (!$model->client_ip) {
                        $model->client_ip = request()->ip();
                    }

                    if (!$model->correlation_id) {
                        $model->correlation_id = request()->header('X-Correlation-ID') ?: (string)Str::uuid();
                    }
                }
            } else {
                // CLI or queues
                if (auth()->check()) {
                    $model->actor_type = 'USER';
                    $model->actor_id = (string)auth()->id();
                    $model->user_id = auth()->id();
                } else {
                    $model->actor_type = $model->actor_type ?? 'SYSTEM';
                }
                if (!$model->correlation_id) {
                    $model->correlation_id = (string)Str::uuid();
                }
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
