<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordResetWithOtp;
use App\Filament\Pages\Auth\ResetPasswordWithOtp;
use App\Filament\Pages\Auth\VerifyPasswordResetOtp;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ActiveTenantsWidget;
use App\Filament\Widgets\MonthlyRevenueChartWidget;
use App\Filament\Widgets\PlanDistributionChartWidget;
use App\Filament\Widgets\PlatformStatsOverviewWidget;
use App\Filament\Widgets\TenantGrowthChartWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin;

class PfAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('pf-admin')
            ->path('pf-admin')
            ->brandName('Lapaqu Platform')
            ->login(Login::class)
            ->passwordReset(RequestPasswordResetWithOtp::class, ResetPasswordWithOtp::class)
            ->routes(function (Panel $panel) {
                Route::get('/password-reset/verify-otp', VerifyPasswordResetOtp::class)
                    ->name('auth.password-reset.verify-otp');
            })
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                PlatformStatsOverviewWidget::class,
                MonthlyRevenueChartWidget::class,
                PlanDistributionChartWidget::class,
                TenantGrowthChartWidget::class,
                ActiveTenantsWidget::class,
            ])
            ->navigationGroups([
                'Multi-Tenant Management',
                'Billing & Subscription',
                'Platform Moderation',
                'Security & System Logs',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->plugin(
                FilamentSpatieLaravelHealthPlugin::make()
                    ->usingPage(\App\Filament\Pages\SystemHealthPage::class)
                    ->navigationGroup('Security & System Logs')
                    ->navigationLabel('System Health & Engine')
                    ->navigationIcon('heroicon-o-cpu-chip')
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
