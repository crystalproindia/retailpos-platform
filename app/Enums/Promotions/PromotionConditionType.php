<?php

namespace App\Enums\Promotions;

enum PromotionConditionType: string
{
    case Product = 'product';
    case Category = 'category';
    case Brand = 'brand';
    case Variant = 'variant';
    case Quantity = 'quantity';
    case BillAmount = 'bill_amount';
    case Branch = 'branch';
    case SalesChannel = 'sales_channel';
    case CustomerGroupFuture = 'customer_group_future';
    case DateRange = 'date_range';
    case TimeRange = 'time_range';
    case DayOfWeek = 'day_of_week';
    case PaymentMethodFuture = 'payment_method_future';
}
