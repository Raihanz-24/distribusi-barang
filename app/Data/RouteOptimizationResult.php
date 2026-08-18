<?php

namespace App\Data;

final readonly class RouteOptimizationResult
{
    public function __construct(
        public array $orderedStoreIds,
        public int $distanceMeters,
        public ?int $durationSeconds,
        public string $provider,
        public bool $isEstimate,
        public ?array $geometry = null,
        public array $metadata = [],
    ) {}
}
