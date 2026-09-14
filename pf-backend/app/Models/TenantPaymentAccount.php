<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantPaymentAccount extends Model
{
    use HasFactory, HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'xendit_sub_account_id',
        'bank_code',
        'bank_account_number',
        'bank_account_holder_name',
        'is_active',
    ];

    protected $casts = [
        'bank_account_number' => 'encrypted',
        'is_active' => 'boolean',
    ];

    public function settlementLogs(): HasMany
    {
        return $this->hasMany(SettlementLog::class);
    }
}
