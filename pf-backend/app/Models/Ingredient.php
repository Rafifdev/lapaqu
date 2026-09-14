<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ingredient extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'category_id',
        'name',
        'unit',
        'base_unit',
        'current_stock',
        'low_stock_threshold',
        'cost_per_unit',
        'is_active',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'low_stock_threshold' => 'decimal:2',
        'cost_per_unit' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'is_low_stock',
        'display_stock',
        'display_low_stock_threshold',
        'purchase_price',
        'purchase_unit',
        'base_cost_per_unit',
        'base_unit_display',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'category_id');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuItemRecipe::class);
    }

    public function stockLogs(): HasMany
    {
        return $this->hasMany(IngredientStockLog::class);
    }

    /**
     * Check if current stock is below or equal to low stock threshold.
     */
    public function getIsLowStockAttribute(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return (float) $this->current_stock <= (float) $this->low_stock_threshold;
    }

    /**
     * Get stock quantity converted to display unit.
     */
    public function getDisplayStockAttribute(): float
    {
        return self::fromBaseUnit((float) $this->current_stock, $this->unit);
    }

    public function getDisplayLowStockThresholdAttribute(): ?float
    {
        if ($this->low_stock_threshold === null) {
            return null;
        }

        return self::fromBaseUnit((float) $this->low_stock_threshold, $this->unit);
    }

    /**
     * Harga Beli (Purchase Price) dalam satuan pembelian (misal Rp 250.000 / kg).
     */
    public function getPurchasePriceAttribute(): float
    {
        $unit = strtolower(trim($this->unit ?? ''));
        $cost = (float) ($this->cost_per_unit ?? 0);

        if (in_array($unit, ['g', 'gram']) && $cost < 1000) {
            return $cost * 1000;
        }

        if (in_array($unit, ['ml', 'mililiter']) && $cost < 500) {
            return $cost * 1000;
        }

        return $cost;
    }

    /**
     * Satuan pembelian untuk Harga Beli (misal 'kg', 'l', 'pcs').
     */
    public function getPurchaseUnitAttribute(): string
    {
        $unit = strtolower(trim($this->unit ?? ''));
        $cost = (float) ($this->cost_per_unit ?? 0);

        if (in_array($unit, ['g', 'gram']) && $cost < 1000) {
            return 'kg';
        }

        if (in_array($unit, ['ml', 'mililiter']) && $cost < 500) {
            return 'l';
        }

        return $this->unit ?? '-';
    }

    /**
     * Harga Satuan (Base Cost) per unit resep (misal Rp 250 / g atau Rp 22 / ml).
     */
    public function getBaseCostPerUnitAttribute(): float
    {
        $unit = strtolower(trim($this->unit ?? ''));
        $cost = (float) ($this->cost_per_unit ?? 0);

        if (in_array($unit, ['kg', 'kilogram', 'l', 'liter'])) {
            return round($cost / 1000, 2);
        }

        if (in_array($unit, ['g', 'gram']) && $cost >= 1000) {
            return round($cost / 1000, 2);
        }

        if (in_array($unit, ['ml', 'mililiter']) && $cost >= 500) {
            return round($cost / 1000, 2);
        }

        return $cost;
    }

    /**
     * Satuan resep dasar untuk Harga Satuan (misal 'g', 'ml', 'pcs').
     */
    public function getBaseUnitDisplayAttribute(): string
    {
        $unit = strtolower(trim($this->unit ?? ''));

        if (in_array($unit, ['kg', 'kilogram', 'g', 'gram'])) {
            return 'g';
        }

        if (in_array($unit, ['l', 'liter', 'ml', 'mililiter'])) {
            return 'ml';
        }

        return $this->unit ?? '-';
    }

    /**
     * Standardize unit conversions to canonical base units:
     * - Weight: gram (kg -> gram: * 1000)
     * - Volume: ml (liter -> ml: * 1000)
     * - Quantity: pcs (pcs, butir, lembar, porsi -> 1:1)
     */
    public static function resolveBaseUnit(string $unit): string
    {
        $unit = strtolower(trim($unit));

        return match ($unit) {
            'kg', 'kilogram', 'gram', 'g' => 'gram',
            'l', 'liter', 'ml', 'mililiter' => 'ml',
            default => $unit,
        };
    }

    public static function toBaseUnit(float $quantity, string $unit): float
    {
        $unit = strtolower(trim($unit));

        return match ($unit) {
            'kg', 'kilogram' => $quantity * 1000,
            'l', 'liter' => $quantity * 1000,
            default => $quantity,
        };
    }

    public static function fromBaseUnit(float $quantityInBase, string $displayUnit): float
    {
        $displayUnit = strtolower(trim($displayUnit));

        return match ($displayUnit) {
            'kg', 'kilogram' => round($quantityInBase / 1000, 3),
            'l', 'liter' => round($quantityInBase / 1000, 3),
            default => round($quantityInBase, 2),
        };
    }
}
