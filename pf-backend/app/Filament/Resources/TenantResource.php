<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null $navigationGroup = 'Multi-Tenant Management';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Restoran')
                    ->placeholder('  Kopi Kenangan')
                    ->required()
                    ->maxLength(150),
                TextInput::make('subdomain')
                    ->label('Subdomain')
                    ->placeholder('kopikenangan')
                    ->helperText('Format: huruf kecil tanpa spasi')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(50),
                Select::make('status')
                    ->label('Status Akun')
                    ->options([
                        'trial' => 'Trial (Uji Coba)',
                        'active' => 'Aktif',
                        'suspended' => 'Suspended (Dibekukan)',
                        'churned' => 'Berhenti Berlangganan',
                    ])
                    ->required(),
                DatePicker::make('trial_ends_at')
                    ->label('Batas Trial')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('outlets'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nama Restoran')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('subdomain')->label('Subdomain')->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'trial' => 'Trial',
                        'suspended' => 'Suspended',
                        'churned' => 'Berhenti Berlangganan',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'trial' => 'warning',
                        'suspended' => 'danger',
                        'churned' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('trial_ends_at')->label('Batas Trial')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('outlets_count')->label('Cabang')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Terdaftar')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Akun')
                    ->options([
                        'active' => 'Aktif',
                        'trial' => 'Trial',
                        'suspended' => 'Suspended',
                        'churned' => 'Berhenti Berlangganan',
                    ]),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\EditAction::make(),
                    Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Tenant $record) => $record->status !== 'suspended')
                        ->action(function (Tenant $record) {
                            $record->status = 'suspended';
                            $record->save();
                            Notification::make()->title('Tenant berhasil disuspend')->warning()->send();
                        }),
                    Actions\Action::make('activate')
                        ->label('Aktifkan')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (Tenant $record) => $record->status !== 'active')
                        ->action(function (Tenant $record) {
                            $record->status = 'active';
                            $record->save();
                            Notification::make()->title('Tenant berhasil diaktifkan')->success()->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
