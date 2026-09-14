<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItem extends Model
{
    use HasFactory, HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'outlet_id',
        'category_id',
        'name',
        'description',
        'base_price',
        'image_url',
        'is_available',
    ];

    protected $casts = [
        'base_price' => 'integer',
        'is_available' => 'boolean',
    ];

    protected $appends = [
        'price',
        'max_servings',
    ];

    public function getPriceAttribute(): int
    {
        return (int) ($this->attributes['base_price'] ?? 0);
    }

    /**
     * Calculate max servings based on ingredient stock.
     * Returns null if no recipe is defined (unlimited).
     * Returns 0 if any ingredient is out of stock.
     */
    public function getMaxServingsAttribute(): ?int
    {
        $recipes = $this->relationLoaded('recipes')
            ? $this->recipes
            : $this->recipes()->with('ingredient')->get();

        if ($recipes->isEmpty()) {
            return null; // no recipe = unlimited (stok tidak ditrack)
        }

        $maxServings = PHP_INT_MAX;

        foreach ($recipes as $recipe) {
            $ingredient = $recipe->ingredient;
            if (!$ingredient || !$ingredient->is_active) {
                return 0;
            }

            $needed = (float) $recipe->quantity_needed;
            if ($needed <= 0) {
                continue;
            }

            $available = (float) $ingredient->current_stock;
            $possibleServings = (int) floor($available / $needed);
            $maxServings = min($maxServings, $possibleServings);
        }

        return $maxServings === PHP_INT_MAX ? null : $maxServings;
    }

    /**
     * Check if this menu item can serve a given quantity.
     */
    public function canServe(int $quantity): bool
    {
        $maxServings = $this->maxServings;

        // No recipe defined = always available (stok tidak ditrack)
        if ($maxServings === null) {
            return true;
        }

        return $maxServings >= $quantity;
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function variantGroups(): HasMany
    {
        return $this->hasMany(MenuItemVariantGroup::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(MenuItemRecipe::class);
    }
}
