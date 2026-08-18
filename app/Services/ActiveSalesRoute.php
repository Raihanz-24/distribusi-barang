<?php

namespace App\Services;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\DailyRoute;
use App\Models\StockTransfer;
use App\Models\User;

class ActiveSalesRoute
{
    public function find(User $sales): ?DailyRoute
    {
        $activeRouteId = StockTransfer::query()
            ->where('sales_id', $sales->id)
            ->where('transfer_type', StockTransferType::IssueToSales)
            ->where('status', StockTransferStatus::Received)
            ->whereNotNull('daily_route_id')
            ->whereDoesntHave('returnTransfer')
            ->latest('received_at')
            ->value('daily_route_id');

        return DailyRoute::query()
            ->where('sales_id', $sales->id)
            ->when(
                $activeRouteId,
                fn ($query) => $query->whereKey($activeRouteId),
                fn ($query) => $query->whereDate('route_date', today()),
            )
            ->first();
    }
}
