<?php

namespace App\Filament\Resources\SalesStockTransfers;

use App\Enums\StockTransferType;
use App\Filament\Resources\SalesStockTransfers\Pages\CreateSalesStockTransfer;
use App\Filament\Resources\SalesStockTransfers\Pages\EditSalesStockTransfer;
use App\Filament\Resources\SalesStockTransfers\Pages\ListSalesStockTransfers;
use App\Filament\Resources\SalesStockTransfers\Pages\ViewSalesStockTransfer;
use App\Filament\Resources\StockTransfers\StockTransferResource;
use Filament\Support\Icons\Heroicon;

class SalesStockTransferResource extends StockTransferResource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Distribusi';

    protected static ?string $modelLabel = 'Distribusi';

    protected static ?string $pluralModelLabel = 'Distribusi';

    protected static ?int $navigationSort = 5;

    protected static function allowedTransferTypes(): array
    {
        return [
            StockTransferType::IssueToSales->value,
            StockTransferType::ReturnFromSales->value,
        ];
    }

    protected static function defaultTransferType(): string
    {
        return StockTransferType::IssueToSales->value;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesStockTransfers::route('/'),
            'create' => CreateSalesStockTransfer::route('/create'),
            'view' => ViewSalesStockTransfer::route('/{record}'),
            'edit' => EditSalesStockTransfer::route('/{record}/edit'),
        ];
    }
}
