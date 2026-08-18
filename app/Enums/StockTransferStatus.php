<?php

namespace App\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PendingApproval = 'pending_approval';
    case Received = 'received';
    case Discrepancy = 'discrepancy';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Issued => 'Menunggu penerimaan',
            self::PendingApproval => 'Menunggu persetujuan Admin',
            self::Received => 'Diterima',
            self::Discrepancy => 'Ada selisih',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
