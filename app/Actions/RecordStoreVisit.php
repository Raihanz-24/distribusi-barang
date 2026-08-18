<?php

namespace App\Actions;

use App\Data\Coordinate;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\Store;
use App\Models\StoreVisit;
use App\Models\User;
use App\Services\ActiveSalesRoute;
use App\Services\GeoDistance;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordStoreVisit
{
    public function __construct(private readonly GeoDistance $distance) {}

    public function execute(User $sales, Store $store, Coordinate $position, VisitStatus $status, array $data = []): StoreVisit
    {
        $route = app(ActiveSalesRoute::class)->find($sales);
        $routeStore = $route?->routeStores()->where('store_id', $store->id)->first();
        $distance = $this->distance->meters(
            $position,
            new Coordinate((float) $store->latitude, (float) $store->longitude),
        );
        $needsReason = ! $routeStore || $distance > config('maps.nearby_radius_meters');
        if ($needsReason && blank($data['exception_reason'] ?? null)) {
            throw ValidationException::withMessages(['exception_reason' => 'Alasan wajib diisi untuk toko di luar rute atau lokasi yang jauh.']);
        }
        if (! $routeStore && ! config('maps.allow_outside_route_visits')) {
            throw ValidationException::withMessages(['store_id' => 'Kunjungan di luar rute dinonaktifkan oleh Admin.']);
        }

        return DB::transaction(function () use ($sales, $store, $position, $status, $data, $route, $routeStore, $distance): StoreVisit {
            User::query()->whereKey($sales->id)->lockForUpdate()->firstOrFail();
            $route?->newQuery()->whereKey($route->id)->lockForUpdate()->first();
            $actualOrder = $route ? ((int) $route->routeStores()->max('actual_order') + 1) : 1;
            $visitNumber = (int) StoreVisit::where('sales_id', $sales->id)->max('visit_number') + 1;
            $visit = StoreVisit::create([
                'daily_route_id' => $route?->id,
                'daily_route_store_id' => $routeStore?->id,
                'store_id' => $store->id,
                'sales_id' => $sales->id,
                'visit_number' => $visitNumber,
                'visit_status' => $status,
                'arrived_at' => $data['arrived_at'] ?? now(),
                'completed_at' => $status === VisitStatus::Pending ? null : now(),
                'latitude' => $position->latitude,
                'longitude' => $position->longitude,
                'location_accuracy' => $data['location_accuracy'] ?? null,
                'distance_to_store_meters' => $distance,
                'visit_notes' => $data['visit_notes'] ?? null,
                'exception_reason' => $data['exception_reason'] ?? null,
            ]);
            $routeStore?->update(['actual_order' => $actualOrder, 'visit_status' => $status]);

            return $visit;
        });
    }
}
