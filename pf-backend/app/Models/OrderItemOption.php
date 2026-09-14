<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemOption extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_item_id',
        'variant_option_id',
        'option_name_snapshot',
        'price_modifier_snapshot',
        'created_at',
    ];

    protected $casts = [
        'price_modifier_snapshot' => 'integer',
        'created_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function variantOption(): BelongsTo
    {
        return $this->belongsTo(MenuItemVariantOption::class, 'variant_option_id');
    }
}
