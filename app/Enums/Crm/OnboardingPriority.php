<?php

namespace App\Enums\Crm;

enum OnboardingPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string { return str($this->value)->headline()->toString(); }
    public function tone(): string { return match ($this) { self::Low, self::Normal => 'neutral', self::High => 'warning', self::Urgent => 'danger' }; }
}
