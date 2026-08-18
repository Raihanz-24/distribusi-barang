<?php

namespace App\Enums;

enum DailyRouteStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Scheduled => 'Terjadwal',
            self::InProgress => 'Berjalan',
            self::PendingReview => 'Menunggu pemeriksaan',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
