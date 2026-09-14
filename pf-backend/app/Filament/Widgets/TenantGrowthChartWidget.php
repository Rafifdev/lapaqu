<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class TenantGrowthChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Pendaftaran Restoran Baru';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '260px';
    protected int | string | array $columnSpan = 3;

    public ?string $filter = '30';

    protected function getFilters(): ?array
    {
        return [
            '7' => '7 Hari',
            '30' => '30 Hari',
            '90' => '90 Hari',
        ];
    }

    protected function getData(): array
    {
        $range = (int) ($this->filter ?? 30);
        
        return Cache::remember("dashboard_tenant_growth_range_{$range}", 60, function () use ($range) {
            $days = collect(range($range - 1, 0))->map(fn ($day) => now()->subDays($day)->toDateString());
            
            $counts = $days->map(function ($date) {
                return Tenant::whereDate('created_at', $date)->count();
            });

            $labels = $days->map(function ($date) use ($range) {
                $carbon = Carbon::parse($date);
                return $range > 30 ? $carbon->format('d/m') : $carbon->format('d M');
            });

            return [
                'datasets' => [
                    [
                        'label' => 'Jumlah Restoran Terdaftar',
                        'data' => $counts->toArray(),
                        'backgroundColor' => 'rgba(245, 158, 11, 0.85)',
                        'borderRadius' => 4,
                    ],
                ],
                'labels' => $labels->toArray(),
            ];
        });
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
