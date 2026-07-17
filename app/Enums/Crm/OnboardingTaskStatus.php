<?php

namespace App\Enums\Crm;

enum OnboardingTaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function label(): string { return str($this->value)->headline()->toString(); }
}
