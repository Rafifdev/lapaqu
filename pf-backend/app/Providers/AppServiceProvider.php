<?php

namespace App\Providers;

use App\Health\Checks\DatabaseLatencyCheck;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force fresh reload of .env to avoid stale dev server process cache
        if (file_exists(base_path('.env'))) {
            try {
                $dotenv = \Dotenv\Dotenv::createMutable(base_path());
                $dotenv->load();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Standard Laravel mail configuration is handled by config/mail.php
        $checks = [
            DatabaseCheck::new()->label('Koneksi Database PostgreSQL'),
            DatabaseLatencyCheck::new()->label('Database Roundtrip Latency'),
            CacheCheck::new()->label('Status Driver Cache'),
            UsedDiskSpaceCheck::new()
                ->label('Penggunaan Disk Server')
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
            ScheduleCheck::new()->label('Laravel Scheduler Heartbeat'),
        ];

        // Production-only strict checks (environment, config caching, queue worker)
        if (app()->isProduction()) {
            $checks[] = OptimizedAppCheck::new()
                ->label('Optimasi Caching Aplikasi')
                ->checkConfig()
                ->checkRoutes();
            $checks[] = EnvironmentCheck::new()->label('Environment & Debug Mode');
            $checks[] = QueueCheck::new()->label('Queue Worker & Failed Jobs');
        }

        Health::checks($checks);
    }
}
