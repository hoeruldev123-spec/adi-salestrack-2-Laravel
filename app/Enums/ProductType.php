<?php

namespace App\Enums;

enum ProductType: string
{
    case LICENSE = 'LICENSE';
    case HARDWARE = 'HARDWARE';
    case CLOUD = 'CLOUD';
    case SERVICE = 'SERVICE';
    case PRODUCT = 'PRODUCT';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
