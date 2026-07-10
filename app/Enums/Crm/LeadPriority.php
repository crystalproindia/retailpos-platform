<?php

namespace App\Enums\Crm;

enum LeadPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Low => 'neutral',
            self::Medium => 'info',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
