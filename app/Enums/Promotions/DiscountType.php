<?php

namespace App\Enums\Promotions;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FreeQuantity = 'free_quantity';
    case FixedPrice = 'fixed_price';
}
