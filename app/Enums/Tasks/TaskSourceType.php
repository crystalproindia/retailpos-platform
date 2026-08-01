<?php

namespace App\Enums\Tasks;

enum TaskSourceType: string
{
    case Manual = 'manual';
    case SystemRule = 'system_rule';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::SystemRule => 'Rule generated',
            self::Import => 'Imported',
        };
    }
}
