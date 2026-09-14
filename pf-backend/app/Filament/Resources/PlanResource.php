<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';
    protected static string | \UnitEnum | null $navigationGroup = 'Billing & Subscription';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode Paket')
                    ->placeholder('PLAN-STARTER')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                TextInput::make('name')
                    ->label('Nama Paket')
                    ->placeholder('  Paket Starter')
                    ->required()
                    ->maxLength(100),
                TextInput::make('price_per_outlet_monthly')
                    ->label('Harga per Outlet / Bulan')
                    ->placeholder('250000')
                    ->required()
                    ->numeric()
                    ->prefix('Rp'),
                TextInput::make('max_tables_per_outlet')
                    ->label('Maksimal Meja per Outlet')
                    ->placeholder('  50')
                    ->numeric()
                    ->helperText('Kosongkan jika unlimited'),
                TextInput::make('max_users_per_outlet')
                    ->label('Maksimal User per Outlet')
                    ->placeholder('  10')
                    ->numeric()
                    ->helperText('Kosongkan jika unlimited'),
                Toggle::make('is_active')
                    ->label('Status Paket Aktif')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->badge(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('price_per_outlet_monthly')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('max_tables_per_outlet')->default('Unlimited'),
                Tables\Columns\TextColumn::make('max_users_per_outlet')->default('Unlimited'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Publikasi')
                    ->trueLabel('Hanya Paket Aktif')
                    ->falseLabel('Hanya Paket Nonaktif'),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make()->label('Edit'),
                    Actions\DeleteAction::make()->label('Hapus'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
