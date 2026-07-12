<?php

namespace App\Enums\Promotions;

enum PromotionType: string
{
    case PercentageDiscount = 'percentage_discount';
    case FixedAmountDiscount = 'fixed_amount_discount';
    case BuyXGetY = 'buy_x_get_y';
    case QuantityDiscount = 'quantity_discount';
    case BundleDiscount = 'bundle_discount';
    case FixedBundlePrice = 'fixed_bundle_price';
    case MinimumBillDiscount = 'minimum_bill_discount';
    case CouponDiscount = 'coupon_discount';
    case FreeItem = 'free_item';
    case ChannelDiscount = 'channel_discount';
    case BranchDiscount = 'branch_discount';
    case CustomerGroupFuture = 'customer_group_future';
}
