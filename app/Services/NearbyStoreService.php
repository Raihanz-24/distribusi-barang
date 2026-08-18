<?php

namespace App\Services;

use App\Data\Coordinate;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Collection;

class NearbyStoreService
{
    public function __construct(private readonly GeoDistance $distance) {}

    public function find(User $sales, Coordinate $position, ?int $radius = null, ?int $limit = null): Collection
    {
        $radius ??= config('maps.nearby_radius_meters');
        $limit ??= config('maps.nearby_limit');
        $latitudeDelta = $radius / 111320;
        $longitudeDelta = $radius / max(1, 111320 * cos(deg2rad($position->latitude)));

        $activeRoute = app(ActiveSalesRoute::class)->find($sales);
        $routeStoreIds = $activeRoute?->routeStores()->pluck('store_id')->all() ?? [];

        return Store::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$position->latitude - $latitudeDelta, $position->latitude + $latitudeDelta])
            ->whereBetween('longitude', [$position->longitude - $longitudeDelta, $position->longitude + $longitudeDelta])
            ->get()
            ->map(function (Store $store) use ($position, $routeStoreIds): Store {
                $store->setAttribute('distance_meters', $this->distance->meters(
                    $position,
                    new Coordinate((float) $store->latitude, (float) $store->longitude),
                ));
                $store->setAttribute('in_today_route', in_array($store->id, $routeStoreIds, true));

                return $store;
            })
            ->filter(fn (Store $store) => $store->distance_meters <= $radius)
            ->sortBy([
                [fn (Store $store) => $store->in_today_route ? 0 : 1, 'asc'],
                ['distance_meters', 'asc'],
            ])
            ->take($limit)
            ->values();
    }
}
