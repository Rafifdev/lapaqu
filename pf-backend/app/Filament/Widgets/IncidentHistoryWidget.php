<?php

namespace App\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

class IncidentHistoryWidget extends BaseWidget
{
    protected static bool $isLazy = false;
    protected ?string $pollingInterval = '5s';
    protected static ?string $heading = 'Riwayat Insiden & Gangguan Sistem (Incident Log by Check Run)';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                HealthCheckResultHistoryItem::query()
                    ->whereIn('status', ['warning', 'failed', 'crashed'])
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('check_label')
                    ->label('Komponen')
                    ->weight('bold')
                    ->searchable()
                    ->default(fn (HealthCheckResultHistoryItem $record) => $record->check_name),

                Tables\Columns\TextColumn::make('status')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'failed', 'crashed' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'failed', 'crashed' => 'CRITICAL',
                        'warning' => 'WARNING',
                        default => strtoupper($state),
                    }),

                Tables\Columns\TextColumn::make('incident_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (HealthCheckResultHistoryItem $record): string {
                        // Check if a newer check for this component has succeeded
                        $newerOk = HealthCheckResultHistoryItem::where('check_name', $record->check_name)
                            ->where('created_at', '>', $record->created_at)
                            ->where('status', 'ok')
                            ->exists();

                        return $newerOk ? 'RESOLVED' : 'ONGOING';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'RESOLVED' => 'success',
                        'ONGOING' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('duration')
                    ->label('Durasi Gangguan')
                    ->state(function (HealthCheckResultHistoryItem $record): string {
                        $nextOk = HealthCheckResultHistoryItem::where('check_name', $record->check_name)
                            ->where('created_at', '>', $record->created_at)
                            ->where('status', 'ok')
                            ->orderBy('created_at', 'asc')
                            ->first();

                        if ($nextOk) {
                            $startTime = Carbon::parse($record->created_at);
                            $resolvedTime = Carbon::parse($nextOk->created_at);
                            $diffMinutes = $startTime->diffInMinutes($resolvedTime);
                            if ($diffMinutes < 1) return '< 1 menit';
                            if ($diffMinutes < 60) return "{$diffMinutes} menit";
                            return round($diffMinutes / 60, 1) . ' jam';
                        }

                        return 'Sedang Berlangsung';
                    }),

                Tables\Columns\TextColumn::make('short_summary')
                    ->label('Ringkasan / Error')
                    ->limit(45)
                    ->tooltip(fn (HealthCheckResultHistoryItem $record) => $record->notification_message ?? $record->short_summary),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Terjadi')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Severity')
                    ->options([
                        'warning' => 'Warning',
                        'failed' => 'Critical / Failed',
                    ]),
            ])
            ->actions([
                \Filament\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn (HealthCheckResultHistoryItem $record) => "Detail Insiden: {$record->check_label}")
                    ->modalDescription(fn (HealthCheckResultHistoryItem $record) => "Batch ID: {$record->batch}")
                    ->modalContent(fn (HealthCheckResultHistoryItem $record) => view('filament.widgets.incident-detail-modal', ['record' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->emptyStateHeading('Tidak Ada Insiden Aktif')
            ->emptyStateDescription('Seluruh komponen engine dan database beroperasi optimal.');
    }
}
