<?php

namespace App\Http\Middleware;

use App\Services\RedisCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RedisDashboardCache
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, mixed $ttlSeconds = 300): Response
    {
        $ttl = (int) ($ttlSeconds ?: 300);

        // 1. Identify Tenant Context
        $user = $request->user('sanctum');
        $tenantId = $request->attributes->get('tenant_id')
            ?? $user?->tenant_id
            ?? $request->header('X-Tenant-Id')
            ?? 'default';

        $tenantTag = RedisCacheService::tenantTag($tenantId);

        // 2. If it is a mutation (POST, PUT, PATCH, DELETE), execute first then invalidate tenant cache!
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $response = $next($request);
            if ($response->isSuccessful()) {
                RedisCacheService::flushTenant($tenantId);
            }
            return $response;
        }

        // 3. If GET request, check Redis cache
        if ($request->isMethod('GET')) {
            // Generate deterministic cache key
            $key = 'lapaqu:api:' . md5(
                $request->fullUrl() . ':' .
                ($user?->id ?? 'guest') . ':' .
                ($request->header('X-Outlet-Id') ?? '')
            );

            try {
                $cached = Cache::tags([$tenantTag, 'dashboard_api'])->get($key);
                if ($cached !== null && is_array($cached)) {
                    $jsonRes = response()->json($cached['data'], $cached['status'] ?? 200);
                    $jsonRes->headers->set('X-Cache', 'HIT');
                    $jsonRes->headers->set('X-Cache-Store', 'Redis');
                    $jsonRes->headers->set('X-Cache-TTL', (string) $ttl);
                    $jsonRes->headers->set('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
                    return $jsonRes;
                }
            } catch (\Throwable $e) {
                // Graceful fallback
            }

            // Execute request
            $response = $next($request);

            // Cache response if successful JSON
            if ($response->isSuccessful()) {
                try {
                    $content = $response->getContent();
                    $decoded = json_decode($content, true);
                    if ($decoded !== null) {
                        Cache::tags([$tenantTag, 'dashboard_api'])->put($key, [
                            'data' => $decoded,
                            'status' => $response->getStatusCode(),
                        ], now()->addSeconds($ttl));

                        $response->headers->set('X-Cache', 'MISS');
                        $response->headers->set('X-Cache-Store', 'Redis');
                    }
                } catch (\Throwable $e) {
                    // Silently fail caching
                }
            }

            return $response;
        }

        return $next($request);
    }
}
