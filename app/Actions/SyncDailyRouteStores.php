<?php

namespace App\Actions;

use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\RouteArea;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class SyncDailyRouteStores
{
    public function execute(DailyRoute $route, array $areaIds, array $extraStoreIds = []): void
    {
        $areaIds = collect($areaIds)->map(fn ($id) => (int) $id)->unique()->values();
        $areaStoreIds = $areaIds
            ->flatMap(fn (int $areaId) => RouteArea::find($areaId)?->stores()
                ->orderByRaw('COALESCE(route_area_store.priority, 999999)')
                ->orderBy('stores.name')
                ->pluck('stores.id')
                ->all() ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $requestedExtraIds = collect($extraStoreIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $areaStoreIds, true))
            ->unique()
            ->values()
            ->all();
        $extraStoreIds = Store::query()
            ->whereIn('id', $requestedExtraIds)
            ->whereHas('visits', fn ($query) => $query
                ->whereBetween('arrived_at', [
                    $route->route_date->copy()->subDays(4)->startOfDay(),
                    $route->route_date->copy()->subDay()->endOfDay(),
                ])
                ->where('visit_status', VisitStatus::Closed))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $desiredStoreIds = [...$areaStoreIds, ...$extraStoreIds];

        DB::transaction(function () use ($route, $areaIds, $extraStoreIds, $desiredStoreIds): void {
            $route->areas()->sync($areaIds->mapWithKeys(fn (int $areaId, int $index) => [
                $areaId => ['area_order' => $index + 1],
            ])->all());
            $route->update(['route_area_id' => $areaIds->first()]);

            $route->routeStores()
                ->whereNotIn('store_id', $desiredStoreIds)
                ->where('visit_status', VisitStatus::Pending)
                ->delete();

            foreach ($desiredStoreIds as $index => $storeId) {
                $route->routeStores()->updateOrCreate(
                    ['store_id' => $storeId],
                    [
                        'planned_order' => $index + 1,
                        'priority' => 100,
                        'scheduled_reason' => in_array($storeId, $extraStoreIds, true)
                            ? 'reschedule_closed_store'
                            : null,
                    ],
                );
            }
        });
    }
}
