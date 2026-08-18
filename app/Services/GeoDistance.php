<?php

namespace App\Services;

use App\Data\Coordinate;

class GeoDistance
{
    public function meters(Coordinate $from, Coordinate $to): int
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($to->latitude - $from->latitude);
        $longitudeDelta = deg2rad($to->longitude - $from->longitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($from->latitude)) * cos(deg2rad($to->latitude))
            * sin($longitudeDelta / 2) ** 2;

        return (int) round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
