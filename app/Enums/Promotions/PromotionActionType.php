<?php

namespace App\Enums\Promotions;

enum PromotionActionType: string
{
    case PercentageOff = 'percentage_off';
    case AmountOff = 'amount_off';
    case SetFixedPrice = 'set_fixed_price';
    case FreeQuantity = 'free_quantity';
    case FreeProduct = 'free_product';
    case BundlePrice = 'bundle_price';
}
