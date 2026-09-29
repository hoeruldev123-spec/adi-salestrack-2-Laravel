<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'CALL';
    case MEETING = 'MEETING';
    case EMAIL = 'EMAIL';
    case FOLLOW_UP = 'FOLLOW_UP';
    case NOTE = 'NOTE';
    case TASK = 'TASK';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
