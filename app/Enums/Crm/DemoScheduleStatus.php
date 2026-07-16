<?php

namespace App\Enums\Crm;

enum DemoScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No show',
            self::Rescheduled => 'Rescheduled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Completed => 'success',
            self::Cancelled, self::NoShow => 'danger',
            self::Rescheduled => 'warning',
        };
    }
}
