<?php

namespace App\Services\Routing;

use App\Contracts\RouteOptimizer;
use App\Data\Coordinate;
use App\Data\RouteOptimizationInput;
use App\Data\RouteOptimizationResult;
use App\Services\GeoDistance;

class LocalRouteOptimizer implements RouteOptimizer
{
    public function __construct(private readonly GeoDistance $distance) {}

    public function optimize(RouteOptimizationInput $input): RouteOptimizationResult
    {
        if ($input->stores === []) {
            return new RouteOptimizationResult([], 0, null, 'local', true);
        }

        $remaining = $input->stores;
        $order = [];
        $current = $input->start;

        while ($remaining !== []) {
            $nextId = $this->nearest($current, $remaining, $input->priorities);
            $order[] = $nextId;
            $current = $remaining[$nextId];
            unset($remaining[$nextId]);
        }

        $order = $this->twoOpt($input->start, $order, $input->stores);

        return new RouteOptimizationResult(
            orderedStoreIds: $order,
            distanceMeters: $this->routeDistance($input->start, $order, $input->stores),
            durationSeconds: null,
            provider: 'local',
            isEstimate: true,
            metadata: ['algorithm' => 'nearest-neighbor+2-opt'],
        );
    }

    private function nearest(Coordinate $from, array $stores, array $priorities): int
    {
        $bestId = (int) array_key_first($stores);
        $bestScore = PHP_INT_MAX;

        foreach ($stores as $id => $coordinate) {
            $priority = max(1, (int) ($priorities[$id] ?? 100));
            $score = $this->distance->meters($from, $coordinate) * (1 + ($priority - 1) / 1000);
            if ($score < $bestScore) {
                $bestId = (int) $id;
                $bestScore = $score;
            }
        }

        return $bestId;
    }

    private function twoOpt(Coordinate $start, array $order, array $stores): array
    {
        $improved = true;
        $bestDistance = $this->routeDistance($start, $order, $stores);

        while ($improved) {
            $improved = false;
            for ($i = 0; $i < count($order) - 1; $i++) {
                for ($j = $i + 1; $j < count($order); $j++) {
                    $candidate = $order;
                    array_splice($candidate, $i, $j - $i + 1, array_reverse(array_slice($candidate, $i, $j - $i + 1)));
                    $distance = $this->routeDistance($start, $candidate, $stores);
                    if ($distance < $bestDistance) {
                        $order = $candidate;
                        $bestDistance = $distance;
                        $improved = true;
                    }
                }
            }
        }

        return $order;
    }

    private function routeDistance(Coordinate $start, array $order, array $stores): int
    {
        $total = 0;
        $current = $start;
        foreach ($order as $id) {
            $total += $this->distance->meters($current, $stores[$id]);
            $current = $stores[$id];
        }

        return $total;
    }
}
