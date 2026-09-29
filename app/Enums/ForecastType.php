<?php

namespace App\Enums;

enum ForecastType: string
{
    case HISTORICAL = 'HISTORICAL';
    case OPPORTUNITY = 'OPPORTUNITY';
    case COMBINED = 'COMBINED';
    case SCENARIO = 'SCENARIO';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
