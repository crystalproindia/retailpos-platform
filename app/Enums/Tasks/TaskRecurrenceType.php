<?php

namespace App\Enums\Tasks;

enum TaskRecurrenceType: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Interval = 'interval';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
