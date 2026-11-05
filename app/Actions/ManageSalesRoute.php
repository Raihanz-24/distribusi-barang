<?php

namespace App\Actions;

use App\Enums\DailyRouteStatus;
use App\Enums\OptimizationStatus;
use App\Models\DailyRoute;
use App\Models\DailyRouteStore;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSalesRoute
{
    public function today(User $sales): DailyRoute
    {
        if (! $sales->isSales()) {
            throw ValidationException::withMessages(['authorization' => 'Hanya Sales yang dapat membuat rute lapangan.']);
        }

        return DB::transaction(function () use ($sales): DailyRoute {
            $route = DailyRoute::query()->firstOrCreate(
                ['sales_id' => $sales->id, 'route_date' => today()],
                [
                    'status' => DailyRouteStatus::Scheduled,
                    'optimization_status' => OptimizationStatus::Pending,
                    'created_by' => $sales->id,
                ],
            );

            if ($route->wasRecentlyCreated) {
                app(AuditLogger::class)->record(
                    'route.created_by_sales',
                    $route,
                    $sales,
                    newValues: ['route_date' => today()->toDateString()],
                    description: 'Sales membuat rute lapangan hari ini.',
                );
            }

            return $route;
        });
    }

    public function addStore(DailyRoute $route, Store $store, User $actor): DailyRouteStore
    {
        $this->authorize($route, $actor);
        $this->ensureMutable($route);

        return DB::transaction(function () use ($route, $store, $actor): DailyRouteStore {
            $lockedRoute = DailyRoute::query()->lockForUpdate()->findOrFail($route->id);
            $existing = $lockedRoute->routeStores()->where('store_id', $store->id)->first();

            if ($existing) {
                return $existing;
            }

            $routeStore = $lockedRoute->routeStores()->create([
                'store_id' => $store->id,
                'planned_order' => ((int) $lockedRoute->routeStores()->max('planned_order')) + 1,
                'priority' => 100,
                'scheduled_reason' => $actor->isSales()
                    ? 'Ditambahkan Sales dari pencarian toko lapangan.'
                    : 'Ditambahkan Admin.',
            ]);

            $lockedRoute->update([
                'optimization_status' => OptimizationStatus::Pending,
                'optimization_provider' => null,
                'route_geometry' => null,
            ]);

            app(AuditLogger::class)->record(
                'route.store_added',
                $routeStore,
                $actor,
                newValues: ['route_id' => $route->id, 'store_id' => $store->id],
                description: "{$store->name} ditambahkan ke rute lapangan.",
            );

            return $routeStore;
        });
    }

    public function move(DailyRouteStore $routeStore, string $direction, User $actor): void
    {
        $route = $routeStore->dailyRoute;
        $this->authorize($route, $actor);
        $this->ensureMutable($route);

        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => 'Arah urutan rute tidak valid.']);
        }

        DB::transaction(function () use ($routeStore, $direction): void {
            $items = $routeStore->dailyRoute->routeStores()
                ->lockForUpdate()
                ->orderBy('planned_order')
                ->orderBy('id')
                ->get()
                ->values();
            $index = $items->search(fn (DailyRouteStore $item) => $item->is($routeStore));
            $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $items->has($targetIndex)) {
                return;
            }

            $items->each(fn (DailyRouteStore $item, int $position) => $item->update([
                'planned_order' => $position + 1,
                'optimized_order' => null,
            ]));

            $current = $items[$index];
            $target = $items[$targetIndex];
            $currentOrder = $current->planned_order;
            $current->update(['planned_order' => $target->planned_order]);
            $target->update(['planned_order' => $currentOrder]);

            $routeStore->dailyRoute->update([
                'optimization_status' => OptimizationStatus::Pending,
                'optimization_provider' => null,
                'route_geometry' => null,
            ]);
        });
    }

    public function removeStore(DailyRouteStore $routeStore, User $actor): void
    {
        $route = $routeStore->dailyRoute;
        $this->authorize($route, $actor);
        $this->ensureMutable($route);

        if ($routeStore->latestVisit()->exists()) {
            throw ValidationException::withMessages(['store' => 'Toko yang sudah diproses tidak dapat dihapus dari rute.']);
        }

        DB::transaction(function () use ($routeStore, $route, $actor): void {
            $storeName = $routeStore->store?->name ?? 'Toko';
            $routeStore->delete();

            $route->routeStores()->orderBy('planned_order')->orderBy('id')->get()->values()->each(
                fn (DailyRouteStore $item, int $index) => $item->update([
                    'planned_order' => $index + 1,
                    'optimized_order' => null,
                ]),
            );
            $route->update([
                'optimization_status' => OptimizationStatus::Pending,
                'optimization_provider' => null,
                'route_geometry' => null,
            ]);

            app(AuditLogger::class)->record(
                'route.store_removed',
                $route,
                $actor,
                description: "{$storeName} dihapus dari rute lapangan.",
            );
        });
    }

    private function authorize(DailyRoute $route, User $actor): void
    {
        if (! $actor->isAdmin() && (! $actor->isSales() || $route->sales_id !== $actor->id)) {
            throw ValidationException::withMessages(['authorization' => 'Anda tidak berhak mengubah rute ini.']);
        }
    }

    private function ensureMutable(DailyRoute $route): void
    {
        if (in_array($route->status, [DailyRouteStatus::PendingReview, DailyRouteStatus::Completed, DailyRouteStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['route' => 'Rute yang sudah diajukan atau selesai tidak dapat diubah.']);
        }
    }
}
