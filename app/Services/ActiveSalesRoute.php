<?php

namespace App\Services;

use App\Models\DailyRoute;
use App\Models\User;

class ActiveSalesRoute
{
    public function find(User $sales): ?DailyRoute
    {
        $activeRouteId = app(ActiveSalesStock::class)->find($sales)?->daily_route_id;

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
