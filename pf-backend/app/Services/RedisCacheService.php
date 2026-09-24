<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RedisCacheService
{
    /**
     * Get tenant cache tag
     */
    public static function tenantTag(?string $tenantId): string
    {
        return 'tenant_' . ($tenantId ?: 'global');
    }

    /**
     * Flush all Redis cache for a specific tenant
     */
    public static function flushTenant(?string $tenantId): void
    {
        try {
            $tag = self::tenantTag($tenantId);
            Cache::tags([$tag])->flush();
            Log::info("Redis cache flushed for tenant tag: {$tag}");
        } catch (\Throwable $e) {
            Log::warning("Failed to flush redis cache for tenant: " . $e->getMessage());
        }
    }

    /**
     * Flush entire dashboard cache
     */
    public static function flushAllDashboard(): void
    {
        try {
            Cache::tags(['dashboard_api'])->flush();
            Log::info("Redis dashboard_api cache flushed completely");
        } catch (\Throwable $e) {
            Log::warning("Failed to flush dashboard_api cache: " . $e->getMessage());
        }
    }
}
