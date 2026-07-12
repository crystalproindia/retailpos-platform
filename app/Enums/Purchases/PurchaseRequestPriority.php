<?php

namespace App\Enums\Purchases;

enum PurchaseRequestPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
