<?php

namespace App\Enums;

enum EntityStatus: string
{
    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
