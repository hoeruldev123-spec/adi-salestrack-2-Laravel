<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case LEAD = 'LEAD';
    case QUALIFICATION = 'QUALIFICATION';
    case PROPOSAL = 'PROPOSAL';
    case NEGOTIATION = 'NEGOTIATION';
    case CLOSING = 'CLOSING';

    public function defaultProbability(): int
    {
        return match ($this) {
            self::LEAD => 10,
            self::QUALIFICATION => 30,
            self::PROPOSAL => 50,
            self::NEGOTIATION => 70,
            self::CLOSING => 90,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
