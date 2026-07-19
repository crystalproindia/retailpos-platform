<?php

namespace App\Enums\Crm;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent, self::Viewed => 'info',
            self::Accepted, self::Converted => 'success',
            self::Rejected, self::Expired, self::Cancelled, self::Superseded => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
