<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestTenantsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pendaftaran Restoran Terbaru';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Tenant::query()->with(['outlets', 'subscriptions.plan'])->latest('created_at')->limit(5))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama Restoran')->weight('bold'),
                Tables\Columns\TextColumn::make('subdomain')->label('Subdomain'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'churned' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('outlets_count')->counts('outlets')->label('Cabang'),
                Tables\Columns\TextColumn::make('trial_ends_at')->label('Batas Trial')->dateTime('d M Y'),
                Tables\Columns\TextColumn::make('created_at')->label('Terdaftar')->since(),
            ])
            ->paginated(false);
    }
}
