<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DatabaseLatencyTrendChartWidget;
use App\Filament\Widgets\DiskUsageTrendChartWidget;
use App\Filament\Widgets\IncidentHistoryWidget;
use App\Filament\Widgets\UptimeTimelineWidget;
use ShuvroRoy\FilamentSpatieLaravelHealth\Pages\HealthCheckResults as BaseHealthPage;

class SystemHealthPage extends BaseHealthPage
{
    protected ?string $pollingInterval = '5s';
    protected static ?string $slug = 'system-health';
    protected static string | \UnitEnum | null $navigationGroup = 'Security & System Logs';
    protected static ?string $navigationLabel = 'System Health & Engine';
    protected static ?string $title = 'System Health & Engine Metrics';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    protected function getFooterWidgets(): array
    {
        return [
            UptimeTimelineWidget::class,
            DatabaseLatencyTrendChartWidget::class,
            DiskUsageTrendChartWidget::class,
            IncidentHistoryWidget::class,
        ];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 2;
    }
}
