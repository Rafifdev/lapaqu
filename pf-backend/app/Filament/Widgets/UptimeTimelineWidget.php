<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

class UptimeTimelineWidget extends Widget
{
    protected static bool $isLazy = false;
    protected ?string $pollingInterval = '5s';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected string $view = 'filament.widgets.uptime-timeline';

    public string $filter = '7d';

    public function getTimelines(): array
    {
        $checkDefinitions = [
            'DatabaseCheck' => [
                'name' => 'DatabaseCheck',
                'label' => 'Koneksi PostgreSQL DB',
                'icon' => 'heroicon-o-server-stack',
            ],
            'DatabaseLatencyCheck' => [
                'name' => 'DatabaseLatencyCheck',
                'label' => 'DB Query Latency',
                'icon' => 'heroicon-o-bolt',
            ],
            'CacheCheck' => [
                'name' => 'Cache Driver Store',
                'label' => 'Cache Driver Store',
                'icon' => 'heroicon-o-circle-stack',
            ],
            'UsedDiskSpaceCheck' => [
                'name' => 'UsedDiskSpaceCheck',
                'label' => 'Disk Storage Server',
                'icon' => 'heroicon-o-archive-box',
            ],
            'ScheduleCheck' => [
                'name' => 'ScheduleCheck',
                'label' => 'Laravel Scheduler',
                'icon' => 'heroicon-o-clock',
            ],
        ];

        $days = match ($this->filter) {
            '24h' => 1,
            '30d' => 30,
            default => 7,
        };

        $totalSegments = 40;
        $now = now();
        $startTime = $now->copy()->subDays($days);
        $intervalMinutes = ($days * 24 * 60) / $totalSegments;

        $timelines = [];

        foreach ($checkDefinitions as $chk) {
            $history = HealthCheckResultHistoryItem::query()
                ->where('check_name', 'like', "%{$chk['name']}%")
                ->where('created_at', '>=', $startTime)
                ->orderBy('created_at', 'asc')
                ->get(['status', 'short_summary', 'notification_message', 'created_at']);

            $totalRuns = $history->count();
            $okRuns = $history->where('status', 'ok')->count();
            $uptimePercentage = $totalRuns > 0 ? round(($okRuns / $totalRuns) * 100, 1) : 100.0;

            $segments = [];
            for ($i = 0; $i < $totalSegments; $i++) {
                $segStart = $startTime->copy()->addMinutes($i * $intervalMinutes);
                $segEnd = $segStart->copy()->addMinutes($intervalMinutes);

                $itemsInSeg = $history->filter(function ($item) use ($segStart, $segEnd) {
                    $itemTime = Carbon::parse($item->created_at);
                    return $itemTime >= $segStart && $itemTime < $segEnd;
                });

                if ($itemsInSeg->isEmpty()) {
                    $segments[] = [
                        'time' => $segEnd->translatedFormat('d M H:i'),
                        'summary' => 'Optimal (100%)',
                        'hex' => '#10b981',
                    ];
                } else {
                    $hasFail = $itemsInSeg->contains(fn ($it) => in_array($it->status, ['failed', 'crashed']));
                    $hasWarn = $itemsInSeg->contains(fn ($it) => $it->status === 'warning');

                    if ($hasFail) {
                        $failedItem = $itemsInSeg->firstWhere('status', 'failed');
                        $segments[] = [
                            'time' => $segEnd->translatedFormat('d M H:i'),
                            'summary' => $failedItem->short_summary ?? 'Insiden Gangguan',
                            'hex' => '#ef4444',
                        ];
                    } elseif ($hasWarn) {
                        $warnItem = $itemsInSeg->firstWhere('status', 'warning');
                        $segments[] = [
                            'time' => $segEnd->translatedFormat('d M H:i'),
                            'summary' => $warnItem->short_summary ?? 'Peringatan Sistem',
                            'hex' => '#f59e0b',
                        ];
                    } else {
                        $segments[] = [
                            'time' => $segEnd->translatedFormat('d M H:i'),
                            'summary' => 'Optimal',
                            'hex' => '#10b981',
                        ];
                    }
                }
            }

            $timelines[] = [
                'label' => $chk['label'],
                'icon' => $chk['icon'],
                'uptime' => $uptimePercentage,
                'status' => $uptimePercentage >= 99.0 ? 'Operational' : ($uptimePercentage >= 95.0 ? 'Degraded' : 'Issues'),
                'statusColor' => $uptimePercentage >= 99.0 ? 'success' : ($uptimePercentage >= 95.0 ? 'warning' : 'danger'),
                'segments' => $segments,
            ];
        }

        return $timelines;
    }
}
