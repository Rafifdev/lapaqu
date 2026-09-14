<?php

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('outlet.{outletId}', function ($user, $outletId) {
    if (!$user) {
        return false;
    }

    // Superadmin has global access
    if (method_exists($user, 'hasRole') && (
        $user->hasRole('superadmin') ||
        $user->hasRole('superadmin', 'sanctum') ||
        $user->hasRole('superadmin', 'web')
    )) {
        return true;
    }

    // Direct outlet assignment (cashier, kitchen staff, etc.)
    if (!empty($user->outlet_id) && (string) $user->outlet_id === (string) $outletId) {
        return true;
    }

    // Owner or tenant admin access to any outlet within their tenant
    if ($user->tenant_id) {
        $belongsToTenant = Outlet::where('id', $outletId)
            ->where('tenant_id', $user->tenant_id)
            ->exists();

        if ($belongsToTenant) {
            if (method_exists($user, 'hasRole')) {
                if ($user->hasRole('owner') || $user->hasRole('owner', 'sanctum') || $user->hasRole('owner', 'web')) {
                    return true;
                }
            }
            if (empty($user->outlet_id)) {
                return true;
            }
        }
    }

    // Fallback: If user has role owner without specific tenant restrictions
    if (method_exists($user, 'hasRole') && ($user->hasRole('owner') || $user->hasRole('owner', 'sanctum'))) {
        return true;
    }

    return false;
});