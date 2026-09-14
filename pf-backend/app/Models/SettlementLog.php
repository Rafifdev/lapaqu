<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementLog extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'tenant_payment_account_id',
        'order_id',
        'gross_amount',
        'platform_fee',
        'net_amount',
        'status',
        'type',
        'settled_at',
        'created_at',
    ];

    protected $casts = [
        'gross_amount' => 'integer',
        'platform_fee' => 'integer',
        'net_amount' => 'integer',
        'settled_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(TenantPaymentAccount::class, 'tenant_payment_account_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
