<?php

namespace App\Enums;

enum OpportunityStatus: string
{
    case OPEN = 'OPEN';
    case WON = 'WON';
    case LOST = 'LOST';
    case CANCELLED = 'CANCELLED';
    case ON_HOLD = 'ON_HOLD';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
