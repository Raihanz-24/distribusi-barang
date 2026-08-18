<?php

namespace App\Services;

use App\Enums\StockLocationType;
use App\Models\StockLocation;
use App\Models\Store;

class StoreTransactionReport
{
    public function data(Store $store): array
    {
        $visits = $store->visits()
            ->with(['sales:id,name,username', 'items.product:id,name,pieces_per_renteng'])
            ->where(fn ($query) => $query->whereHas('items')->orWhere('amount_paid', '>', 0))
            ->latest('completed_at')
            ->get();
        $location = StockLocation::query()
            ->with(['balances.product:id,name,code,pieces_per_renteng'])
            ->where('type', StockLocationType::Store)
            ->where('reference_id', $store->id)
            ->first();
        $stockBalances = $location?->balances
            ->filter(fn ($balance) => $balance->quantity_pieces > 0)
            ->sortBy('product.name')
            ->values() ?? collect();

        return [
            'store' => $store,
            'visits' => $visits,
            'stockBalances' => $stockBalances,
            'stockTotal' => (int) $stockBalances->sum('quantity_pieces'),
            'outstandingDebt' => $store->outstanding_debt,
        ];
    }
}
