<?php

namespace App\Services;

use App\Enums\StockTransferStatus;
use App\Enums\StockTransferType;
use App\Models\StockTransfer;
use App\Models\User;

class ActiveSalesStock
{
    public function find(User $sales, bool $lockForUpdate = false): ?StockTransfer
    {
        return StockTransfer::query()
            ->with('items.product')
            ->where('sales_id', $sales->id)
            ->where('transfer_type', StockTransferType::IssueToSales)
            ->where('status', StockTransferStatus::Received)
            ->whereNotNull('daily_route_id')
            ->whereDoesntHave('returnTransfer')
            ->latest('received_at')
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
    }
}
