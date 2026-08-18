<?php

namespace App\Enums;

enum OptimizationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Manual = 'manual';
}
