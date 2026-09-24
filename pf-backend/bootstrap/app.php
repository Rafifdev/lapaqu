<?php

use App\Http\Middleware\EnforceTenantSubscriptionStatus;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\ResolveTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\AuthenticateFromStaffCookie::class,
            ResolveTenantContext::class,
        ]);

        $middleware->encryptCookies(except: [
            'lapaqu_staff_session',
        ]);

        $middleware->alias([
            'tenant.context' => ResolveTenantContext::class,
            'tenant.subscription' => EnforceTenantSubscriptionStatus::class,
            'role' => EnsureRole::class,
            'redis.cache' => \App\Http\Middleware\RedisDashboardCache::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
