<?php

namespace App\Enums\Crm;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';
    case Void = 'void';

    public function label(): string { return str($this->value)->replace('_', ' ')->headline()->toString(); }
    public function isEditable(): bool { return $this === self::Draft; }
    public function isTerminal(): bool { return in_array($this, [self::Paid, self::Cancelled, self::Void], true); }
}
