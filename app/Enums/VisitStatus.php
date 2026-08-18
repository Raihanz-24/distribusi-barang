<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Pending = 'pending';
    case Visited = 'visited';
    case Closed = 'closed';
    case OwnerAbsent = 'owner_absent';
    case NotFound = 'not_found';
    case Postponed = 'postponed';
    case Inactive = 'inactive';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum dikunjungi',
            self::Visited => 'Selesai',
            self::Closed => 'Toko tutup',
            self::OwnerAbsent => 'Pemilik tidak ada',
            self::NotFound => 'Tidak ditemukan',
            self::Postponed => 'Ditunda',
            self::Inactive => 'Tidak aktif',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
