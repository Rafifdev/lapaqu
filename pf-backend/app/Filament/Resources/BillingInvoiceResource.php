<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingInvoiceResource\Pages;
use App\Models\BillingInvoice;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class BillingInvoiceResource extends Resource
{
    protected static ?string $model = BillingInvoice::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';
    protected static string | \UnitEnum | null $navigationGroup = 'Billing & Subscription';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['subscription.tenant']))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('subscription.tenant.name')->label('Tenant')->searchable(),
                Tables\Columns\TextColumn::make('amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('due_date')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Pembayaran')
                    ->options([
                        'paid' => 'Lunas (Paid)',
                        'pending' => 'Menunggu Pembayaran (Pending)',
                        'expired' => 'Kadaluarsa (Expired)',
                    ]),
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
            'index' => Pages\ListBillingInvoices::route('/'),
        ];
    }
}
