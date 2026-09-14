<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'code',
        'name',
        'price_per_outlet_monthly',
        'max_tables_per_outlet',
        'max_users_per_outlet',
        'max_outlets',
        'is_active',
    ];

    protected $casts = [
        'price_per_outlet_monthly' => 'integer',
        'max_tables_per_outlet' => 'integer',
        'max_users_per_outlet' => 'integer',
        'max_outlets' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
