<?php

namespace App\Enums\Notifications;

enum EmailDeliveryStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case TemporarilyFailed = 'temporarily_failed';
    case PermanentlyFailed = 'permanently_failed';
    case Bounced = 'bounced';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TemporarilyFailed => 'Temporarily failed',
            self::PermanentlyFailed => 'Permanently failed',
            default => str($this->value)->headline()->toString(),
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Delivered, self::PermanentlyFailed, self::Bounced, self::Rejected, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Queued => in_array($next, [self::Processing, self::Rejected, self::PermanentlyFailed, self::Cancelled], true),
            self::Processing => in_array($next, [self::Sent, self::TemporarilyFailed, self::PermanentlyFailed, self::Rejected, self::Cancelled], true),
            self::Sent => in_array($next, [self::Delivered, self::Bounced, self::Rejected, self::PermanentlyFailed], true),
            self::TemporarilyFailed => in_array($next, [self::Processing, self::PermanentlyFailed, self::Cancelled], true),
            self::Delivered, self::PermanentlyFailed, self::Bounced, self::Rejected, self::Cancelled => false,
        };
    }
}
