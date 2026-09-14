<?php

namespace App\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($model instanceof User) {
            return;
        }

        if (app()->bound('tenant_id') && ($tenantId = app('tenant_id'))) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } elseif (auth()->hasUser() && ($user = auth()->user()) && $user->tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        }
    }
}
