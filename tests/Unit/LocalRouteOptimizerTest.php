<?php

namespace Tests\Unit;

use App\Data\Coordinate;
use App\Data\RouteOptimizationInput;
use App\Services\GeoDistance;
use App\Services\Routing\LocalRouteOptimizer;
use PHPUnit\Framework\TestCase;

class LocalRouteOptimizerTest extends TestCase
{
    public function test_optimizer_returns_each_store_exactly_once_and_is_stable(): void
    {
        $optimizer = new LocalRouteOptimizer(new GeoDistance);
        $input = new RouteOptimizationInput(
            new Coordinate(-8.58, 116.10),
            [
                10 => new Coordinate(-8.57, 116.11),
                20 => new Coordinate(-8.56, 116.12),
                30 => new Coordinate(-8.59, 116.09),
            ],
        );

        $first = $optimizer->optimize($input);
        $second = $optimizer->optimize($input);

        $this->assertSame($first->orderedStoreIds, $second->orderedStoreIds);
        $this->assertEqualsCanonicalizing([10, 20, 30], $first->orderedStoreIds);
        $this->assertCount(3, array_unique($first->orderedStoreIds));
        $this->assertGreaterThan(0, $first->distanceMeters);
        $this->assertTrue($first->isEstimate);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Coordinate(-91, 116);
    }
}
