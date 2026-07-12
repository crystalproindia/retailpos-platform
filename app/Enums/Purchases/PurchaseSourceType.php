<?php

namespace App\Enums\Purchases;

enum PurchaseSourceType: string
{
    case Manual = 'manual';
    case ReorderSuggestion = 'reorder_suggestion';
    case LowStock = 'low_stock';
    case StockoutPrevention = 'stockout_prevention';
    case FutureAi = 'future_ai';
}
