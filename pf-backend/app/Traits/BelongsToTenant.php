<?php

namespace App\Traits;

use App\Models\Outlet;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                if (app()->bound('tenant_id') && app('tenant_id')) {
                    $model->tenant_id = app('tenant_id');
                } elseif (auth()->check() && auth()->user()->tenant_id) {
                    $model->tenant_id = auth()->user()->tenant_id;
                } elseif (!empty($model->outlet_id)) {
                    $outlet = Outlet::withoutGlobalScopes()->find($model->outlet_id);
                    if ($outlet && $outlet->tenant_id) {
                        $model->tenant_id = $outlet->tenant_id;
                    }
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
