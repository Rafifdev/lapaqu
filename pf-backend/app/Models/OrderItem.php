<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'menu_item_id',
        'item_name_snapshot',
        'base_price_snapshot',
        'quantity',
        'subtotal',
        'status',
        'notes',
        'is_voided',
        'voided_by_user_id',
        'voided_at',
    ];

    protected $casts = [
        'base_price_snapshot' => 'integer',
        'quantity' => 'integer',
        'subtotal' => 'integer',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }
}
