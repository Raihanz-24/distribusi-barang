<?php

namespace App\Filament\Resources\SalesStockTransfers\Pages;

use App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource;
use App\Filament\Resources\StockTransfers\Pages\CreateStockTransfer;

class CreateSalesStockTransfer extends CreateStockTransfer
{
    protected static string $resource = SalesStockTransferResource::class;
}
