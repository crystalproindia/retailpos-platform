<?php

namespace App\Enums\Crm;

enum OnboardingStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case WaitingForCustomer = 'waiting_for_customer';
    case WaitingForTeam = 'waiting_for_team';
    case TrainingPending = 'training_pending';
    case GoLiveReady = 'go_live_ready';
    case Live = 'live';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string { return str($this->value)->headline()->toString(); }
    public function tone(): string { return match ($this) { self::NotStarted => 'neutral', self::InProgress => 'info', self::WaitingForCustomer => 'warning', self::WaitingForTeam => 'indigo', self::TrainingPending => 'teal', self::GoLiveReady => 'success', self::Live => 'success', self::OnHold => 'warning', self::Cancelled => 'danger' }; }
    public function isActive(): bool { return ! in_array($this, [self::Live, self::Cancelled], true); }
}
