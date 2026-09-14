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

        // Enforce Brevo SMTP settings explicitly from current .env
        config([
            'mail.default' => env('MAIL_MAILER', 'smtp'),
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => env('MAIL_HOST', 'smtp-relay.brevo.com'),
            'mail.mailers.smtp.port' => (int) env('MAIL_PORT', 587),
            'mail.mailers.smtp.username' => env('MAIL_USERNAME', 'b712a3001@smtp-brevo.com'),
            'mail.mailers.smtp.password' => env('MAIL_PASSWORD'),
            'mail.mailers.smtp.encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'mail.from.address' => env('MAIL_FROM_ADDRESS', 'tolebot1@gmail.com'),
            'mail.from.name' => env('MAIL_FROM_NAME', 'Lapaqu Platform'),
        ]);
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
