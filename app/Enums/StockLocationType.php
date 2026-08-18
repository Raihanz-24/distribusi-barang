<?php

namespace App\Enums;

enum StockLocationType: string
{
    case MainWarehouse = 'main_warehouse';
    case AdminWarehouse = 'admin_warehouse';
    case Sales = 'sales';
    case Store = 'store';

    public function label(): string
    {
        return match ($this) {
            self::MainWarehouse => 'Gudang Utama',
            self::AdminWarehouse => 'Gudang Admin',
            self::Sales => 'Stok Bawaan Sales',
            self::Store => 'Toko',
        };
    }
}
