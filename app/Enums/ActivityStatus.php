<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case OPEN = 'OPEN';
    case DONE = 'DONE';
    case CANCELLED = 'CANCELLED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
