<?php

namespace App\Enums;

enum ForecastRunStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
