<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantSubscriptionStatus
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound('tenant') && ($tenant = app('tenant'))) {
            if (in_array($tenant->status, ['suspended', 'churned'])) {
                return response()->json([
                    'message' => 'Akses tenant dinonaktifkan karena status langganan ' . $tenant->status . '.',
                    'status' => $tenant->status,
                ], 403);
            }
        }

        return $next($request);
    }
}
