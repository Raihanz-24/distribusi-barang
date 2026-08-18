<?php

namespace App\Enums;

enum StockUnit: string
{
    case Piece = 'piece';
    case Renteng = 'renteng';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Bungkus',
            self::Renteng => 'Renteng',
        };
    }
}
