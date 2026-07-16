<?php

namespace App\Enums\Crm;

enum LeadScoreConfidence: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
