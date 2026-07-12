<?php

namespace App\Enums\Purchases;

enum PurchaseRequestStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ConvertedToPo = 'converted_to_po';
    case Cancelled = 'cancelled';
}
