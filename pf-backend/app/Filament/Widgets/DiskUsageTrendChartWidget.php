<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

class DiskUsageTrendChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;
    protected ?string $pollingInterval = '5s';
    protected ?string $heading = 'Penggunaan Disk Server (%)';
    protected static ?int $sort = 4;
    protected ?string $maxHeight = '220px';
    protected int | string | array $columnSpan = 1;
    public ?string $filter = '7d';

    protected function getFilters(): ?array
    {
        return [
            '24h' => '24 Jam',
            '7d' => '7 Hari',
            '30d' => '30 Hari',
        ];
    }

    protected function getData(): array
    {
        // 1. Measure real system disk usage
        $totalDisk = @disk_total_space('/') ?: 1;
        $freeDisk = @disk_free_space('/') ?: 1;
        $realDiskPercent = round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1);

        $daysCount = match ($this->filter) {
            '24h' => 1,
            '30d' => 30,
            default => 7,
        };

        $sampleCount = min(24, $daysCount == 1 ? 12 : $daysCount);
        $stepDays = max(1, (int) ($daysCount / $sampleCount));
        $intervals = collect(range($sampleCount - 1, 0))->map(fn ($i) => now()->subDays($i * $stepDays));

        $records = HealthCheckResultHistoryItem::query()
            ->where('check_name', 'like', '%UsedDiskSpaceCheck%')
            ->where('created_at', '>=', now()->subDays($daysCount))
            ->get();

        $dataPoints = [];
        $labels = [];

        foreach ($intervals as $index => $time) {
            $timeStr = $time->format('Y-m-d');
            $matching = $records->filter(fn ($r) => Carbon::parse($r->created_at)->format('Y-m-d') === $timeStr);

            if ($index === $sampleCount - 1) {
                // Latest point is real system disk usage
                $dataPoints[] = $realDiskPercent;
            } elseif ($matching->isNotEmpty()) {
                $val = (int) str_replace('%', '', $matching->last()->short_summary ?? '15');
                $dataPoints[] = $val;
            } else {
                $dataPoints[] = $realDiskPercent;
            }

            $labels[] = $this->filter === '24h' ? $time->format('H:00') : $time->translatedFormat('d M');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Disk Usage (%)',
                    'data' => $dataPoints,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'fill' => 'start',
                    'tension' => 0.25,
                    'pointRadius' => 4,
                ],
                [
                    'label' => 'Critical Threshold (90%)',
                    'data' => array_fill(0, count($labels), 90),
                    'borderColor' => '#ef4444',
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
