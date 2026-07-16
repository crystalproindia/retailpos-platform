<?php

namespace App\Enums\Crm;

enum LeadScoreCategory: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';
    case AtRisk = 'at_risk';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::AtRisk => 'At Risk',
            default => str($this->value)->headline()->toString(),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Hot => 'danger',
            self::Warm => 'warning',
            self::Cold => 'neutral',
            self::AtRisk => 'danger',
            self::Won => 'success',
            self::Lost => 'neutral',
        };
    }
}
