<?php

namespace App\Enums\Purchases;

enum PurchaseReturnStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
