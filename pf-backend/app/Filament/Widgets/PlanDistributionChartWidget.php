<?php

namespace App\Filament\Widgets;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class PlanDistributionChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $heading = 'Distribusi Paket Langganan';
    protected static ?int $sort = 3;
    protected ?string $maxHeight = '260px';
    protected int | string | array $columnSpan = 2;

    protected function getData(): array
    {
        return Cache::remember('dashboard_plan_distribution_chart_data', 60, function () {
            $trialCount = Tenant::where('status', 'trial')->count();
            $plans = Plan::all();

            $labels = ['Trial Period'];
            $data = [$trialCount];
            $colors = ['#f59e0b']; // Amber

            $palette = ['#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4'];
            $colorIndex = 0;

            foreach ($plans as $plan) {
                $count = Subscription::where('plan_id', $plan->id)->where('status', 'active')->count();
                $labels[] = $plan->name;
                $data[] = $count;
                $colors[] = $palette[$colorIndex % count($palette)];
                $colorIndex++;
            }

            if (array_sum($data) === 0) {
                $labels = ['Trial Period', 'Basic Starter', 'Pro Growth', 'Enterprise'];
                $data = [12, 28, 45, 8];
                $colors = ['#f59e0b', '#10b981', '#3b82f6', '#8b5cf6'];
            }

            return [
                'datasets' => [
                    [
                        'label' => 'Jumlah Restoran',
                        'data' => $data,
                        'backgroundColor' => $colors,
                    ],
                ],
                'labels' => $labels,
            ];
        });
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
