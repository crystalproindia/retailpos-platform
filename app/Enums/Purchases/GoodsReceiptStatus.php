<?php

namespace App\Enums\Purchases;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Closed = 'closed';
}
