<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // 1. Try header X-Tenant-Subdomain or X-Tenant-ID
        if ($subdomain = $request->header('X-Tenant-Subdomain')) {
            $tenant = Tenant::where('subdomain', strtolower($subdomain))->first();
        } elseif ($tenantId = $request->header('X-Tenant-ID')) {
            $tenant = Tenant::find($tenantId);
        }

        // 2. Try subdomain from host header (e.g. tenant.domain.com)
        if (!$tenant) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) >= 3 && !in_array($parts[0], ['api', 'admin', 'www', 'localhost'])) {
                $tenant = Tenant::where('subdomain', strtolower($parts[0]))->first();
            }
        }

        // 3. Try from authenticated user
        if (!$tenant && $request->user() && $request->user()->tenant_id) {
            $tenant = Tenant::find($request->user()->tenant_id);
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
            app()->instance('tenant_id', $tenant->id);
        }

        return $next($request);
    }
}
