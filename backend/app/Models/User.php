<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * An NIS staff officer.
 */
class User extends Authenticatable implements OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username', 'fullname', 'service_number', 'email', 'password',
        'role', 'command', 'is_active', 'must_change_password', 'photo_path',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_last_step'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** SuperAdmin implicitly holds every role (legacy requireRole behaviour). */
    public function hasRole(StaffRole ...$roles): bool
    {
        return $this->role === StaffRole::SuperAdmin || in_array($this->role, $roles, true);
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    public function auditLabel(): string
    {
        return "{$this->fullname} ({$this->service_number})";
    }
}
