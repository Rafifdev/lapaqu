<?php

namespace App\Filament\Widgets;

use App\Models\BillingInvoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestInvoicesWidget extends BaseWidget
{
    protected static ?string $heading = 'Invoice Terbaru';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(BillingInvoice::query()->with(['subscription.tenant'])->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Nomor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('subscription.tenant.name')
                    ->label('Restoran')
                    ->searchable()
                    ->sortable()
                    ->limit(15),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'paid' => 'heroicon-m-check-circle',
                        'pending' => 'heroicon-m-arrow-path',
                        'expired' => 'heroicon-m-x-circle',
                        default => 'heroicon-m-information-circle',
                    }),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Total Tagihan')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->since()
                    ->sortable(),
            ])
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10])
            ->defaultSort('created_at', 'desc');
    }
}
