<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationClient extends Model
{
    protected $table = 'operation_clients';

    // A-02 (audit 2026-07-17): kiosk_token_hash/registration_token_hash trước đây lộ
    // ra JSON trả về frontend (GET /api/admin/workstations, /api/workstations...) vì
    // model không có $hidden. Đây là hash (không phải token gốc) nhưng không có lý do
    // gì để gửi ra browser — ẩn hẳn.
    protected $hidden = [
        'kiosk_token_hash',
        'registration_token_hash',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (empty($model->type) && !empty($model->default_capability)) {
                $model->type = $model->default_capability;
            }
            if (empty($model->workstation_type) && !empty($model->default_capability)) {
                $model->workstation_type = $model->default_capability;
            }
            if (empty($model->default_capability) && !empty($model->workstation_type)) {
                $model->default_capability = $model->workstation_type;
            }
        });
    }

    protected $fillable = [
        'code',
        'name',
        'location',
        'status',
        'last_seen_at',
        'default_capability',
        'default_route',
        'type',
        'workstation_type',
        'default_screen',
        'kiosk_mode',
        'kiosk_token_hash',
        'kiosk_token_active',
        'kiosk_token_expires_at',
        'registration_token_hash',
        'hostname',
        'registered_ip',
        'last_ip',
        'configuration',
        'version',
        'agent_version',
        'last_heartbeat',
        'suspended',
        'active_errors',
    ];

    protected $casts = [
        'kiosk_mode' => 'boolean',
        'kiosk_token_active' => 'boolean',
        'kiosk_token_expires_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_heartbeat' => 'datetime',
        'suspended' => 'boolean',
        'configuration' => 'array',
        'active_errors' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'operation_client_id');
    }

    public function clientCapabilities()
    {
        return $this->hasMany(OperationClientCapability::class, 'operation_client_id');
    }

    public function capabilities()
    {
        return $this->belongsToMany(Capability::class, 'operation_client_capabilities', 'operation_client_id', 'capability_id')
            ->withPivot('enabled', 'configuration_json')
            ->withTimestamps();
    }

    public function clientDevices()
    {
        return $this->hasMany(OperationClientDevice::class, 'operation_client_id');
    }

    public function devices()
    {
        return $this->belongsToMany(Device::class, 'operation_client_devices', 'operation_client_id', 'device_id')
            ->withPivot('device_role', 'is_default', 'priority', 'enabled')
            ->withTimestamps();
    }

    public function kioskSessions()
    {
        return $this->hasMany(KioskSession::class, 'operation_client_id');
    }

    public function weighingJobs()
    {
        return $this->hasMany(WeighingJob::class, 'assigned_operation_client_id');
    }
}
