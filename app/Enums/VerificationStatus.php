<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
            self::Duplicate => 'Duplikat',
        };
    }
}
