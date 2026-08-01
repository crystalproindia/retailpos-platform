<?php

namespace App\Enums\Tasks;

enum TaskType: string
{
    case Personal = 'personal';
    case Work = 'work';

    public function label(): string
    {
        return match ($this) {
            self::Personal => 'Personal',
            self::Work => 'Work',
        };
    }
}
