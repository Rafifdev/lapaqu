<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngredientStockLog extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'ingredient_id',
        'type',
        'quantity',
        'unit',
        'balance_before',
        'balance_after',
        'reference_id',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];


    protected $appends = [
        'balanceBefore',
        'balanceAfter',
        'createdAt',
        'referenceId',
        'createdBy',
    ];

    public function getBalanceBeforeAttribute()
    {
        return (float) ($this->attributes['balance_before'] ?? 0);
    }

    public function getBalanceAfterAttribute()
    {
        return (float) ($this->attributes['balance_after'] ?? 0);
    }

    public function getCreatedAtAttribute()
    {
        return $this->attributes['created_at'] ?? null;
    }

    public function getReferenceIdAttribute()
    {
        return $this->attributes['reference_id'] ?? null;
    }

    public function getCreatedByAttribute()
    {
        return $this->attributes['created_by'] ?? null;
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
