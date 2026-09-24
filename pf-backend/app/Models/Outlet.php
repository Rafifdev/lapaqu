<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'slogan',
        'address',
        'phone',
        'timezone',
        'is_active',
        'is_main',
        'enable_tax',
        'tax_percentage',
        'enable_service_charge',
        'service_charge_percentage',
        'table_timeout',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'enable_tax' => 'boolean',
        'tax_percentage' => 'integer',
        'enable_service_charge' => 'boolean',
        'service_charge_percentage' => 'integer',
        'table_timeout' => 'integer',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(Table::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
