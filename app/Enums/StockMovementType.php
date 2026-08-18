<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case ProductionReceipt = 'production_receipt';
    case WarehouseTransfer = 'warehouse_transfer';
    case TransferToSales = 'transfer_to_sales';
    case DepositToStore = 'deposit_to_store';
    case SaleFromStore = 'sale_from_store';
    case WithdrawalFromStore = 'withdrawal_from_store';
    case ReturnGood = 'return_good';
    case ReturnDamaged = 'return_damaged';
    case SalesReturnToWarehouse = 'sales_return_to_warehouse';
    case Damage = 'damage';
    case Loss = 'loss';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Stok awal',
            self::ProductionReceipt => 'Hasil produksi',
            self::WarehouseTransfer => 'Transfer antar gudang',
            self::TransferToSales => 'Penyerahan ke Sales',
            self::DepositToStore => 'Titipan ke toko',
            self::SaleFromStore => 'Terjual dari toko',
            self::WithdrawalFromStore => 'Penarikan dari toko',
            self::ReturnGood => 'Retur baik',
            self::ReturnDamaged => 'Retur rusak',
            self::SalesReturnToWarehouse => 'Pengembalian Sales',
            self::Damage => 'Kerusakan',
            self::Loss => 'Kehilangan',
            self::Adjustment => 'Penyesuaian',
            self::Reversal => 'Pembalikan',
        };
    }
}
