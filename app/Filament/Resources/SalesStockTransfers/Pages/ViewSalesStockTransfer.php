<?php

namespace App\Filament\Resources\SalesStockTransfers\Pages;

use App\Filament\Resources\SalesStockTransfers\SalesStockTransferResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewSalesStockTransfer extends ViewRecord
{
    protected static string $resource = SalesStockTransferResource::class;

    protected string $view = 'filament.resources.sales-stock-transfers.pages.view-sales-stock-transfer';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'sales', 'dailyRoute.area', 'dailyRoute.areas', 'items.product',
            'pickupTransfer.items.product', 'returnTransfer.items.product',
            'pickupSettlement.dailyRoute.visits.store', 'pickupSettlement.dailyRoute.visits.items.product',
            'returnSettlement.dailyRoute.visits.store',
            'returnSettlement.dailyRoute.visits.items.product',
        ]);
    }
}
