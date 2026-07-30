<?php

namespace App\Support\Reports;

class ReportValueFormatter
{
    /** @var array<int, string> */
    private const MONEY_KEYS = [
        'amount', 'average_order_value', 'balance_due', 'cess', 'cost_of_goods_sold',
        'discounts', 'gross_profit', 'gross_sales', 'gross_total', 'igst', 'net_sales',
        'outstanding', 'paid', 'payments_received', 'purchase_total', 'return_value',
        'sgst', 'stock_value', 'tax', 'taxable_sales', 'total', 'unit_cost', 'value',
    ];

    /** @var array<int, string> */
    private const COUNT_KEYS = [
        'count', 'incomplete_count', 'invoice_count', 'low_stock_count',
        'movement_count', 'sales_count',
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
