<?php

namespace App\Enums;

enum ProductType: string
{
    case PRODUCT = 'PRODUCT';
    case SERVICE = 'SERVICE';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
