<?php

namespace App\Data;

use InvalidArgumentException;

final readonly class Coordinate
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Koordinat tidak valid.');
        }
    }

    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }
}
