<?php

namespace App\Services;

use App\Enums\SalesSettlementStatus;
use App\Models\DailySalesSettlement;
use App\Models\StoreVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesDepositReport
{
    public function rows(array $filters): Collection
    {
        return $this->query($filters)->get();
    }

    public function summary(Collection $rows): array
    {
        $expected = (int) $rows->sum('total_collection');
        $actual = (int) $rows->sum('verified_collection');

        return [
            'records' => $rows->count(),
            'expected' => $expected,
            'actual' => $actual,
            'difference' => $actual - $expected,
            'debt' => (int) $rows->sum('total_debt'),
            'stores' => (int) $rows->sum('total_stores'),
            'stock_shortage' => (int) $rows->sum('stock_shortage_pieces'),
        ];
    }

    public function query(array $filters): Builder
    {
        return DailySalesSettlement::query()
            ->with(['sales:id,name,username', 'returnTransfer:id,number,status', 'dailyRoute:id,route_date'])
            ->addSelect([
                'total_debt' => StoreVisit::query()
                    ->selectRaw('COALESCE(SUM(debt_amount), 0)')
                    ->whereColumn('daily_route_id', 'daily_sales_settlements.daily_route_id')
                    ->whereNull('reversed_at'),
            ])
            ->where('status', SalesSettlementStatus::Approved)
            ->when($filters['start_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('settlement_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('settlement_date', '<=', $date))
            ->when($filters['sales_id'] ?? null, fn (Builder $query, int|string $salesId) => $query->where('sales_id', $salesId))
            ->orderByDesc('settlement_date')
            ->orderBy('sales_id');
    }
}
