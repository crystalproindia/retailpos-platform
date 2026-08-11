<?php

namespace App\Enums\Inventory;

enum StockCountStatus: string
{
    case Draft = 'draft';
    case Counting = 'counting';
    case Submitted = 'submitted';
    case Review = 'review';
    case Approved = 'approved';
    case Posted = 'posted';
}
