<?php

namespace App\Support\Reports;

class ReportValueFormatter
{
    /** @var array<int, string> */
    private const MONEY_KEYS = [
        'amount', 'average_order_value', 'balance_due', 'cess', 'cost_of_goods_sold',
        'discount_impact_on_profit', 'discounts', 'gross_profit', 'gross_profit_before_discount', 'gross_sales', 'gross_total', 'igst', 'known_cost_net_sales', 'net_sales',
        'outstanding', 'paid', 'payments_received', 'purchase_total', 'return_value',
        'return_impact', 'sales_returns', 'sgst', 'stock_value', 'tax', 'taxable_sales', 'total', 'total_discounts', 'unit_cost', 'value',
    ];

    /** @var array<int, string> */
    private const COUNT_KEYS = [
        'count', 'incomplete_count', 'invoice_count', 'low_stock_count',
        'captured_item_count', 'item_count', 'movement_count', 'quantity_returned', 'quantity_sold', 'reconstructed_item_count', 'sales_count', 'unavailable_cost_item_count',
    ];

    public function display(string $key, mixed $value): string
    {
        if ($value === null) {
            return 'Unavailable';
        }

        if ($this->isMoney($key) && is_numeric($value)) {
            return number_format(((int) $value) / 100, 2);
        }

        if (in_array($key, self::COUNT_KEYS, true) && is_numeric($value)) {
            return number_format((int) $value);
        }

        if ((str_ends_with($key, 'margin_percent') || str_ends_with($key, 'coverage_percent')) && is_numeric($value)) {
            return number_format((float) $value, 2).'%';
        }

        return (string) $value;
    }

    public function csv(string $key, mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($this->isMoney($key) && is_numeric($value)) {
            return number_format(((int) $value) / 100, 2, '.', '');
        }

        return (string) $value;
    }

    private function isMoney(string $key): bool
    {
        return in_array($key, self::MONEY_KEYS, true);
    }
}
