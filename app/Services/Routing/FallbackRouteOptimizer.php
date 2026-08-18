<?php

namespace App\Services\Routing;

use App\Contracts\RouteOptimizer;
use App\Data\RouteOptimizationInput;
use App\Data\RouteOptimizationResult;
use Throwable;

class FallbackRouteOptimizer implements RouteOptimizer
{
    public function __construct(
        private readonly OsrmRouteOptimizer $osrm,
        private readonly LocalRouteOptimizer $local,
    ) {}

    public function optimize(RouteOptimizationInput $input): RouteOptimizationResult
    {
        if (config('maps.routing_provider') !== 'osrm') {
            return $this->local->optimize($input);
        }

        try {
            return $this->osrm->optimize($input);
        } catch (Throwable $exception) {
            report($exception);
            $fallback = $this->local->optimize($input);

            return new RouteOptimizationResult(
                $fallback->orderedStoreIds,
                $fallback->distanceMeters,
                $fallback->durationSeconds,
                'local-fallback',
                true,
                $fallback->geometry,
                [...$fallback->metadata, 'fallback_reason' => $exception->getMessage()],
            );
        }
    }
}
