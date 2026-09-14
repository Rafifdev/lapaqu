<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ActiveTenantsWidget extends BaseWidget
{
    protected static bool $isLazy = false;
    protected static ?string $heading = 'Daftar Restoran Aktif';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->whereIn('status', ['active', 'trial'])
                    ->with(['outlets', 'currentSubscription.plan'])
                    ->withCount('outlets')
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Toko')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('outlets_count')
                    ->label('Cabang')
                    ->sortable(),
                Tables\Columns\TextColumn::make('currentSubscription.plan.name')
                    ->label('Tier')
                    ->badge()
                    ->default(fn (Tenant $record): string => $record->status === 'trial' ? 'Free Trial' : 'Basic Starter')
                    ->color(fn (string $state): string => match ($state) {
                        'Enterprise' => 'primary',
                        'Pro Growth', 'Pro Plan' => 'info',
                        'Basic Starter', 'Basic' => 'success',
                        'Free Trial', 'Trial Period' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->defaultPaginationPageOption(4)
            ->paginationPageOptions([4, 8])
            ->defaultSort('created_at', 'desc');
    }
}
