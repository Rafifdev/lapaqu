<?php
namespace App\Filament\Resources\BillingInvoiceResource\Pages;
use App\Filament\Resources\BillingInvoiceResource;
use Filament\Resources\Pages\ListRecords;
class ListBillingInvoices extends ListRecords {
    protected static string $resource = BillingInvoiceResource::class;
}