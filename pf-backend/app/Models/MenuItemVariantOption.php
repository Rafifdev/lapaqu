<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MenuItemVariantOption extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'variant_group_id',
        'name',
        'price_modifier',
        'is_available',
    ];

    protected $casts = [
        'price_modifier' => 'integer',
        'is_available' => 'boolean',
    ];

    public function variantGroup(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariantGroup::class, 'variant_group_id');
    }
}
