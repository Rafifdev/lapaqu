<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    protected string $guard_name = 'sanctum';
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'name',
        'email',
        'google_id',
        'facebook_id',
        'avatar',
        'password',
        'phone',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'pin',
        'is_active',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'pin',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'pf-admin') {
            return $this->hasRole('superadmin', 'web')
                || $this->hasRole('superadmin', 'sanctum')
                || $this->hasRole('superadmin')
                || $this->email === 'admin@lapaqu.id'
                || str_ends_with($this->email, '@lapaqu.id');
        }

        return true;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
