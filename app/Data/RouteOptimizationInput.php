<?php

namespace App\Data;

final readonly class RouteOptimizationInput
{
    /**
     * @param  array<int, Coordinate>  $stores  Key adalah store ID.
     * @param  array<int, int>  $priorities
     */
    public function __construct(
        public Coordinate $start,
        public array $stores,
        public array $priorities = [],
    ) {}
}
