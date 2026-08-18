<?php

namespace App\Services;

use App\Enums\StockLocationType;
use App\Models\StockLocation;
use App\Models\Store;
use App\Models\User;

class StockLocationService
{
    public function warehouse(): StockLocation
    {
        return $this->mainWarehouse();
    }

    public function mainWarehouse(): StockLocation
    {
        return $this->resolve(StockLocationType::MainWarehouse, 0, 'Gudang Utama');
    }

    public function adminWarehouse(): StockLocation
    {
        return $this->resolve(StockLocationType::AdminWarehouse, 0, 'Gudang Admin');
    }

    public function forSales(User $sales): StockLocation
    {
        return $this->resolve(StockLocationType::Sales, $sales->id, 'Stok Bawaan Sales — '.$sales->name);
    }

    public function forStore(Store $store): StockLocation
    {
        return $this->resolve(StockLocationType::Store, $store->id, 'Titipan — '.$store->name);
    }

    private function resolve(StockLocationType $type, ?int $referenceId, string $name): StockLocation
    {
        $location = StockLocation::query()
            ->where('type', $type)
            ->where('reference_id', $referenceId)
            ->first();

        if ($location) {
            if ($location->name !== $name || ! $location->is_active) {
                $location->update(['name' => $name, 'is_active' => true]);
            }

            return $location;
        }

        return StockLocation::create([
            'type' => $type,
            'reference_id' => $referenceId,
            'name' => $name,
            'is_active' => true,
        ]);
    }
}
