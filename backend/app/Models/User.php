<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';
    
    // UUID configurations
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected $fillable = [
        'username',
        'display_name',
        'password_hash',
        'is_active',
        'operation_client_id',
        'pin',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Override default password attribute mapping for Auth.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()->where('code', $roleCode)->exists();
    }

    public function operationClient()
    {
        return $this->belongsTo(OperationClient::class, 'operation_client_id');
    }

    // Alias for backward compatibility
    public function workstation()
    {
        return $this->belongsTo(Workstation::class, 'operation_client_id');
    }

    public function getWorkstationIdAttribute()
    {
        return $this->operation_client_id;
    }

    public function setWorkstationIdAttribute($value)
    {
        $this->attributes['operation_client_id'] = $value;
    }

    /**
     * Verify short manager PIN for sensitive actions (supervisor/admin authorization).
     */
    public static function verifyManagerPin(string $pin)
    {
        $users = self::where('is_active', true)->get();
        foreach ($users as $user) {
            if ($user->hasRole('SUPERVISOR') || $user->hasRole('ADMIN')) {
                if ($user->pin && (password_verify($pin, $user->pin) || $pin === $user->pin)) {
                    return $user;
                }
            }
        }
        return null;
    }
}
