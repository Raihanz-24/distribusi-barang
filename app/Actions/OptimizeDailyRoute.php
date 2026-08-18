<?php

namespace App\Actions;

use App\Contracts\RouteOptimizer;
use App\Data\Coordinate;
use App\Data\RouteOptimizationInput;
use App\Enums\OptimizationStatus;
use App\Enums\VisitStatus;
use App\Models\DailyRoute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class OptimizeDailyRoute
{
    public function __construct(private readonly RouteOptimizer $optimizer) {}

    public function execute(DailyRoute $route, User $actor, ?Coordinate $start = null)
    {
        $route->load('routeStores.store');
        $start ??= $this->startCoordinate($route);
        $pending = $route->routeStores->filter(
            fn ($item) => $item->visit_status === VisitStatus::Pending && $item->store->latitude !== null
        );
        $input = new RouteOptimizationInput(
            $start,
            $pending->mapWithKeys(fn ($item) => [
                $item->store_id => new Coordinate((float) $item->store->latitude, (float) $item->store->longitude),
            ])->all(),
            $pending->pluck('priority', 'store_id')->all(),
        );

        try {
            $result = $this->optimizer->optimize($input);

            DB::transaction(function () use ($route, $actor, $input, $result): void {
                foreach ($result->orderedStoreIds as $index => $storeId) {
                    $route->routeStores()->where('store_id', $storeId)->update(['optimized_order' => $index + 1]);
                }
                $route->update([
                    'optimization_status' => OptimizationStatus::Completed,
                    'optimization_provider' => $result->provider,
                    'optimized_distance_meters' => $result->distanceMeters,
                    'optimized_duration_seconds' => $result->durationSeconds,
                    'route_geometry' => $result->geometry,
                ]);
                $route->optimizationRuns()->create([
                    'provider' => $result->provider,
                    'start_coordinate' => $input->start->toArray(),
                    'input_store_ids' => array_keys($input->stores),
                    'result_order' => $result->orderedStoreIds,
                    'distance_meters' => $result->distanceMeters,
                    'duration_seconds' => $result->durationSeconds,
                    'geometry' => $result->geometry,
                    'response_payload' => $result->metadata,
                    'status' => 'completed',
                    'executed_by' => $actor->id,
                    'executed_at' => now(),
                ]);
            });

            return $result;
        } catch (Throwable $exception) {
            $route->update(['optimization_status' => OptimizationStatus::Failed]);
            $route->optimizationRuns()->create([
                'provider' => config('maps.routing_provider'),
                'start_coordinate' => $input->start->toArray(),
                'input_store_ids' => array_keys($input->stores),
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);
            throw $exception;
        }
    }

    private function startCoordinate(DailyRoute $route): Coordinate
    {
        $latitude = $route->start_latitude ?? $route->area?->default_start_latitude ?? config('maps.default_latitude');
        $longitude = $route->start_longitude ?? $route->area?->default_start_longitude ?? config('maps.default_longitude');

        return new Coordinate((float) $latitude, (float) $longitude);
    }
}
