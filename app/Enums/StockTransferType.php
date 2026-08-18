<?php

namespace App\Enums;

enum StockTransferType: string
{
    case WarehouseTransfer = 'warehouse_transfer';
    case IssueToSales = 'issue_to_sales';
    case ReturnFromSales = 'return_from_sales';

    public function label(): string
    {
        return match ($this) {
            self::WarehouseTransfer => 'Gudang Utama → Gudang Admin',
            self::IssueToSales => 'Gudang Admin → Sales',
            self::ReturnFromSales => 'Sales → Gudang Admin',
        };
    }
}
