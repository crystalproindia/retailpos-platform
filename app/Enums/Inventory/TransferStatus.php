<?php

namespace App\Enums\Inventory;

enum TransferStatus: string
{
    case Draft = 'draft';
    case Requested = 'requested';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Packing = 'packing';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Discrepancy = 'discrepancy';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Received, self::Rejected, self::Cancelled], true);
    }
}
