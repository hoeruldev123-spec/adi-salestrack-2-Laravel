<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case PAID = 'PAID';
    case UNPAID = 'UNPAID';
    case CANCELLED = 'CANCELLED';
    case REFUNDED = 'REFUNDED';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
