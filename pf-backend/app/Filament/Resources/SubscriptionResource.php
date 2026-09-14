<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-sparkles';
    protected static string | \UnitEnum | null $navigationGroup = 'Billing & Subscription';
    protected static ?string $navigationLabel = 'Langganan Aktif';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tenant', 'plan']))
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->label('Restoran')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('plan.name')->label('Paket')->badge()->color('primary'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'overdue' => 'danger',
                        'suspended' => 'danger',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('active_outlets_count')->label('Jumlah Cabang')->alignCenter(),
                Tables\Columns\TextColumn::make('current_period_end')->label('Jatuh Tempo')->dateTime('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Mulai Berlangganan')->dateTime('d M Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('current_period_end', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Langganan')
                    ->options([
                        'active' => 'Active',
                        'trial' => 'Trial',
                        'overdue' => 'Overdue',
                        'suspended' => 'Suspended',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->relationship('plan', 'name')
                    ->label('Paket Langganan'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                Actions\ViewAction::make()->label('Lihat'),
            ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }
}
