<?php

namespace App\Enums;

enum SalesSettlementStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pemeriksaan',
            self::Approved => 'Disetujui',
        };
    }
}
