<?php

namespace App\Enums\Crm;

enum CrmCustomerStatus: string
{
    case Active = 'active';
    case Onboarding = 'onboarding';
    case Inactive = 'inactive';
    case Churned = 'churned';

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Onboarding => 'info',
            self::Inactive => 'neutral',
            self::Churned => 'danger',
        };
    }
}
