<?php

namespace App\Enums;

enum PricingType: string
{
    case LICENSE = 'LICENSE';
    case SUBSCRIPTION = 'SUBSCRIPTION';
    case ONE_TIME = 'ONE_TIME';
    case SERVICE = 'SERVICE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
