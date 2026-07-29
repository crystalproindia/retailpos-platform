<?php

namespace App\Enums\Crm;

enum InvoicePaymentStatus: string
{
    case Recorded = 'recorded';
    case Cleared = 'cleared';
    case Pending = 'pending';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->headline()->toString();
    }
}
