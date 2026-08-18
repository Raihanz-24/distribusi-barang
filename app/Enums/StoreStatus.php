<?php

namespace App\Enums;

enum StoreStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Tidak aktif',
            self::Closed => 'Berhenti',
        };
    }
}
