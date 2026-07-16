<?php

namespace App\Enums\Crm;

enum QuotationStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Accepted, self::Converted => 'success',
            self::Rejected, self::Expired => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
