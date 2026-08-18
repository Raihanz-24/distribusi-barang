<?php

namespace App\Services;

use App\Data\Coordinate;
use App\Models\Store;
use Illuminate\Support\Collection;

class DuplicateStoreDetector
{
    public function __construct(private readonly GeoDistance $distance) {}

    public function candidates(string $name, Coordinate $coordinate, ?int $ignoreId = null, int $radiusMeters = 100): Collection
    {
        $normalized = mb_strtolower(trim($name));

        return Store::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get()
            ->map(function (Store $store) use ($normalized, $coordinate): Store {
                similar_text($normalized, mb_strtolower($store->name), $similarity);
                $store->setAttribute('name_similarity', $similarity);
                $store->setAttribute('distance_meters', $this->distance->meters(
                    $coordinate,
                    new Coordinate((float) $store->latitude, (float) $store->longitude)
                ));

                return $store;
            })
            ->filter(fn (Store $store) => $store->distance_meters <= $radiusMeters && $store->name_similarity >= 60)
            ->values();
    }
}
