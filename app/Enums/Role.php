<?php

namespace App\Enums;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case SALES = 'SALES';
    case SALES_MANAGER = 'SALES_MANAGER';
    case MANAGEMENT = 'MANAGEMENT';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrator',
            self::SALES => 'Sales',
            self::SALES_MANAGER => 'Sales Manager',
            self::MANAGEMENT => 'Management',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
