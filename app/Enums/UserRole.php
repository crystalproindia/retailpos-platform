<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Manager = 'manager';
    case Sales = 'sales';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Manager => 'Manager',
            self::Sales => 'Sales',
            self::Staff => 'Staff',
        };
    }
}
