<?php

namespace App\Services\Routing;

use App\Contracts\RouteOptimizer;
use App\Data\RouteOptimizationInput;
use App\Data\RouteOptimizationResult;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class OsrmRouteOptimizer implements RouteOptimizer
{
    public function __construct(
        private readonly Factory $http,
        private readonly LocalRouteOptimizer $local,
    ) {}

    public function optimize(RouteOptimizationInput $input): RouteOptimizationResult
    {
        $baseUrl = rtrim((string) config('maps.osrm_base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('OSRM_BASE_URL belum dikonfigurasi.');
        }

        $ids = array_keys($input->stores);
        $coordinates = [$input->start, ...array_values($input->stores)];
        $coordinateString = implode(';', array_map(
            fn ($coordinate) => $coordinate->longitude.','.$coordinate->latitude,
            $coordinates
        ));
        $cacheKey = 'osrm-table:'.sha1($coordinateString);

        $table = Cache::remember($cacheKey, config('maps.cache_seconds'), function () use ($baseUrl, $coordinateString) {
            $response = $this->http
                ->timeout(config('maps.http_timeout_seconds'))
                ->retry(2, 200)
                ->get("{$baseUrl}/table/v1/driving/{$coordinateString}", ['annotations' => 'distance,duration']);

            $response->throw();

            return $response->json();
        });

        if (($table['code'] ?? null) !== 'Ok' || empty($table['distances'])) {
            throw new RuntimeException('OSRM tidak mengembalikan matriks yang dapat digunakan.');
        }

        $orderIndexes = $this->nearestNeighbor($table['distances'], $input->priorities, $ids);
        $orderedIds = array_map(fn (int $index) => (int) $ids[$index - 1], $orderIndexes);
        [$distance, $duration] = $this->totals($orderIndexes, $table);

        return new RouteOptimizationResult(
            orderedStoreIds: $orderedIds,
            distanceMeters: (int) round($distance),
            durationSeconds: (int) round($duration),
            provider: 'osrm',
            isEstimate: false,
            metadata: ['service' => 'table'],
        );
    }

    private function nearestNeighbor(array $matrix, array $priorities, array $ids): array
    {
        $remaining = range(1, count($ids));
        $order = [];
        $current = 0;
        while ($remaining !== []) {
            usort($remaining, function (int $a, int $b) use ($matrix, $priorities, $ids, $current): int {
                $scoreA = ($matrix[$current][$a] ?? PHP_INT_MAX) * (1 + (($priorities[$ids[$a - 1]] ?? 100) - 1) / 1000);
                $scoreB = ($matrix[$current][$b] ?? PHP_INT_MAX) * (1 + (($priorities[$ids[$b - 1]] ?? 100) - 1) / 1000);

                return $scoreA <=> $scoreB;
            });
            $current = array_shift($remaining);
            $order[] = $current;
        }

        return $order;
    }

    private function totals(array $order, array $table): array
    {
        $distance = $duration = 0;
        $current = 0;
        foreach ($order as $index) {
            if (! isset($table['distances'][$current][$index], $table['durations'][$current][$index])) {
                throw new RuntimeException('Sebagian koordinat tidak terhubung oleh OSRM.');
            }
            $distance += $table['distances'][$current][$index];
            $duration += $table['durations'][$current][$index];
            $current = $index;
        }

        return [$distance, $duration];
    }
}
