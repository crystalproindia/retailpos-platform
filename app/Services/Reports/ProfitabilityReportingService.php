<?php

namespace App\Services\Reports;

use App\Models\Crm\CrmInvoice;
use App\Models\Pos\PosReturn;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds profitability from sale-time snapshots. Product master data is never
 * joined for metrics, so later catalog edits cannot rewrite history.
 */
class ProfitabilityReportingService
{
    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array{from: CarbonImmutable, to: CarbonImmutable, timezone: string} $range @param array<string, mixed> $context */
    public function report(User $user, array $scope, array $range, array $context): array
    {
        $rows = $this->rows($user, $scope, $range, $context);
        $summary = DB::query()->fromSub($rows, 'profitability_rows')
            ->selectRaw("COALESCE(SUM(gross_sales), 0) gross_sales, COALESCE(SUM(discounts), 0) discounts, COALESCE(SUM(net_sales), 0) net_sales, COALESCE(SUM(return_sales), 0) return_sales, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN net_sales ELSE 0 END), 0) known_cost_net_sales, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN cogs ELSE 0 END), 0) cogs, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN gross_profit ELSE 0 END), 0) gross_profit, COALESCE(SUM(CASE WHEN entry_type = 'sale' AND cost_status = 'captured' THEN 1 ELSE 0 END), 0) captured_item_count, COALESCE(SUM(CASE WHEN entry_type = 'sale' AND cost_status = 'reconstructed' THEN 1 ELSE 0 END), 0) reconstructed_item_count, COALESCE(SUM(CASE WHEN entry_type = 'sale' AND cost_status = 'unavailable' THEN 1 ELSE 0 END), 0) unavailable_cost_item_count, COALESCE(SUM(CASE WHEN entry_type = 'sale' THEN 1 ELSE 0 END), 0) item_count")
            ->first();

        $grossSales = $this->minor($summary->gross_sales);
        $discounts = $this->minor($summary->discounts);
        $netSales = $this->minor($summary->net_sales);
        $knownCostNetSales = $this->minor($summary->known_cost_net_sales);
        $cogs = $this->minor($summary->cogs);
        $grossProfit = $this->minor($summary->gross_profit);
        $returnImpact = abs($this->minor($summary->return_sales));
        $invoiceRows = $this->groups($rows, ['source', 'document_key', 'document_number', 'document_date', 'customer_name', 'outlet_name', 'salesperson_name'], $range, true);

        return [
            'gross_sales' => $grossSales,
            'net_sales' => $netSales,
            'known_cost_net_sales' => $knownCostNetSales,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $this->percent($grossProfit, $knownCostNetSales),
            'total_discounts' => $discounts,
            'discount_impact_on_profit' => $discounts,
            'sales_returns' => $returnImpact,
            'return_impact' => $returnImpact,
            'revenue_cost_coverage_percent' => $this->percent($knownCostNetSales, $netSales),
            'item_cost_coverage_percent' => $this->percent((int) $summary->captured_item_count + (int) $summary->reconstructed_item_count, (int) $summary->item_count),
            'captured_item_count' => (int) $summary->captured_item_count,
            'reconstructed_item_count' => (int) $summary->reconstructed_item_count,
            'unavailable_cost_item_count' => (int) $summary->unavailable_cost_item_count,
            'notice' => 'POS and CRM profitability uses immutable sale-time snapshots. Free-text CRM lines and historical items without verified cost remain revenue-only and are excluded from COGS, profit, and margin. Completed POS returns reverse the original sale-time classification. Cancelled CRM invoices are excluded; CRM partial return and credit-note COGS reversal is not yet supported.',
            // The CSV uses the same normalized invoice rollup as the primary report table.
            'rows' => $invoiceRows,
            'invoice_rows' => $invoiceRows,
            'product_rows' => $this->groups($rows, ['product_id', 'product_name', 'sku'], $range),
            'category_rows' => $this->groups($rows, ['category_id', 'category_name'], $range),
            'brand_rows' => $this->groups($rows, ['brand_id', 'brand_name'], $range),
            'outlet_rows' => $this->groups($rows, ['outlet_id', 'outlet_name'], $range),
            'salesperson_rows' => $this->groups($rows, ['salesperson_id', 'salesperson_name'], $range),
        ];
    }

    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array{from: CarbonImmutable, to: CarbonImmutable} $range @param array<string, mixed> $context */
    private function rows(User $user, array $scope, array $range, array $context): Builder
    {
        if (($context['source'] ?? null) === 'crm') {
            return $this->crmInvoices($user, $scope, $range, $context);
        }
        if (($context['source'] ?? null) === 'pos') {
            return $this->posSales($user, $scope, $range, $context)
                ->unionAll($this->posReturns($user, $scope, $range, $context));
        }

        return $this->posSales($user, $scope, $range, $context)
            ->unionAll($this->posReturns($user, $scope, $range, $context))
            ->unionAll($this->crmInvoices($user, $scope, $range, $context));
    }

    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array{from: CarbonImmutable, to: CarbonImmutable} $range @param array<string, mixed> $context */
    private function posSales(User $user, array $scope, array $range, array $context): Builder
    {
        $query = DB::table('pos_sale_items as item')
            ->join('pos_sales as sale', 'sale.id', '=', 'item.pos_sale_id')
            ->leftJoin('branches as outlet', 'outlet.id', '=', 'sale.branch_id')
            ->leftJoin('users as salesperson', 'salesperson.id', '=', 'sale.completed_by')
            ->where('item.company_id', $user->company_id)->where('sale.status', 'completed')
            ->whereBetween('sale.sold_at', [$range['from']->utc(), $range['to']->utc()]);
        $this->applySaleFilters($query, $scope, $context, 'sale', 'item');

        return $query->selectRaw("'pos' as source, 'sale' as entry_type, item.company_id, item.pos_sale_id as document_id, ".$this->concat(["'pos:'", 'item.pos_sale_id'])." as document_key, sale.sale_number as document_number, sale.sold_at as document_date, COALESCE(sale.customer_name_snapshot, 'Walk-in') as customer_name, sale.branch_id as outlet_id, COALESCE(outlet.name, 'Unassigned') as outlet_name, sale.completed_by as salesperson_id, COALESCE(salesperson.name, 'Unassigned') as salesperson_name, item.product_id, item.product_name, item.sku, item.category_id, COALESCE(item.category_name_snapshot, 'Uncategorized') as category_name, item.brand_id_snapshot as brand_id, COALESCE(item.brand_name_snapshot, 'Unbranded') as brand_name, item.quantity as quantity_sold, 0 as quantity_returned, item.quantity as net_quantity, COALESCE(item.gross_sales_snapshot, item.gross_amount, 0) as gross_sales, COALESCE(item.discount_amount, 0) as discounts, 0 as return_sales, COALESCE(item.net_sales_snapshot, item.taxable_amount, 0) as net_sales, item.unit_cost_snapshot, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN COALESCE(item.total_cost_snapshot, 0) ELSE 0 END as cogs, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN COALESCE(item.gross_profit_snapshot, 0) ELSE 0 END as gross_profit, COALESCE(item.cost_snapshot_status, 'unavailable') as cost_status, COALESCE(item.cost_snapshot_method, 'unavailable') as cost_provenance");
    }

    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array{from: CarbonImmutable, to: CarbonImmutable} $range @param array<string, mixed> $context */
    private function posReturns(User $user, array $scope, array $range, array $context): Builder
    {
        $query = DB::table('pos_return_items as return_item')
            ->join('pos_returns as return_document', 'return_document.id', '=', 'return_item.pos_return_id')
            ->join('pos_sale_items as item', 'item.id', '=', 'return_item.original_sale_item_id')
            ->join('pos_sales as sale', 'sale.id', '=', 'item.pos_sale_id')
            ->leftJoin('branches as outlet', 'outlet.id', '=', 'sale.branch_id')
            ->leftJoin('users as salesperson', 'salesperson.id', '=', 'sale.completed_by')
            ->where('return_document.company_id', $user->company_id)
            ->where('return_document.status', PosReturn::STATUS_COMPLETED)
            ->whereBetween('return_document.completed_at', [$range['from']->utc(), $range['to']->utc()]);
        $this->applySaleFilters($query, $scope, $context, 'sale', 'item');

        return $query->selectRaw("'pos' as source, 'return' as entry_type, item.company_id, item.pos_sale_id as document_id, ".$this->concat(["'pos:'", 'item.pos_sale_id'])." as document_key, sale.sale_number as document_number, sale.sold_at as document_date, COALESCE(sale.customer_name_snapshot, 'Walk-in') as customer_name, sale.branch_id as outlet_id, COALESCE(outlet.name, 'Unassigned') as outlet_name, sale.completed_by as salesperson_id, COALESCE(salesperson.name, 'Unassigned') as salesperson_name, item.product_id, item.product_name, item.sku, item.category_id, COALESCE(item.category_name_snapshot, 'Uncategorized') as category_name, item.brand_id_snapshot as brand_id, COALESCE(item.brand_name_snapshot, 'Unbranded') as brand_name, 0 as quantity_sold, -return_item.return_quantity as quantity_returned, -return_item.return_quantity as net_quantity, -return_item.gross_adjustment as gross_sales, -return_item.discount_adjustment as discounts, -return_item.taxable_adjustment as return_sales, -return_item.taxable_adjustment as net_sales, item.unit_cost_snapshot, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN -(return_item.return_quantity * item.unit_cost_snapshot) ELSE 0 END as cogs, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN -return_item.taxable_adjustment + (return_item.return_quantity * item.unit_cost_snapshot) ELSE 0 END as gross_profit, COALESCE(item.cost_snapshot_status, 'unavailable') as cost_status, COALESCE(item.cost_snapshot_method, 'unavailable') as cost_provenance");
    }

    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array{from: CarbonImmutable, to: CarbonImmutable} $range @param array<string, mixed> $context */
    private function crmInvoices(User $user, array $scope, array $range, array $context): Builder
    {
        $query = DB::table('crm_invoice_items as item')
            ->join('crm_invoices as invoice', 'invoice.id', '=', 'item.invoice_id')
            ->leftJoin('branches as outlet', 'outlet.id', '=', 'invoice.branch_id')
            ->where('invoice.company_id', $user->company_id)
            ->whereNotIn('invoice.status', ['cancelled', 'void'])
            ->whereDate('invoice.issue_date', '>=', $range['from']->toDateString())
            ->whereDate('invoice.issue_date', '<=', $range['to']->toDateString());
        if ($scope['ids'] !== null) {
            $query->whereIn('invoice.branch_id', $scope['ids']);
        }
        foreach (['product_id', 'category_id', 'brand_id'] as $filter) {
            if ($context[$filter] ?? null) {
                $column = $filter === 'brand_id' ? 'brand_id_snapshot' : ($filter === 'category_id' ? 'category_id_snapshot' : 'product_id');
                $query->where("item.{$column}", $context[$filter]);
            }
        }
        if ($context['customer_id'] ?? null) {
            $query->where('invoice.customer_id', $context['customer_id']);
        }
        if (($context['discounted'] ?? null) !== null) {
            $query->where('item.discount_amount', $context['discounted'] ? '>' : '=', 0);
        }

        return $query->selectRaw("'crm' as source, 'sale' as entry_type, invoice.company_id, invoice.id as document_id, ".$this->concat(["'crm:'", 'invoice.id'])." as document_key, invoice.invoice_number as document_number, invoice.issue_date as document_date, COALESCE(invoice.billing_name, invoice.billing_company, 'Unassigned') as customer_name, invoice.branch_id as outlet_id, COALESCE(outlet.name, 'Unassigned') as outlet_name, NULL as salesperson_id, 'Unassigned' as salesperson_name, item.product_id, item.name as product_name, item.sku_snapshot as sku, item.category_id_snapshot as category_id, COALESCE(item.category_name_snapshot, 'Unclassified') as category_name, item.brand_id_snapshot as brand_id, COALESCE(item.brand_name_snapshot, 'Unbranded') as brand_name, item.quantity as quantity_sold, 0 as quantity_returned, item.quantity as net_quantity, COALESCE(item.gross_sales_snapshot, item.line_subtotal + item.discount_amount, 0) as gross_sales, COALESCE(item.discount_amount, 0) as discounts, 0 as return_sales, COALESCE(item.net_sales_snapshot, item.line_subtotal, 0) as net_sales, item.unit_cost_snapshot, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN COALESCE(item.total_cost_snapshot, 0) ELSE 0 END as cogs, CASE WHEN item.cost_snapshot_status IN ('captured', 'reconstructed') THEN COALESCE(item.gross_profit_snapshot, 0) ELSE 0 END as gross_profit, COALESCE(item.cost_snapshot_status, 'unavailable') as cost_status, COALESCE(item.cost_snapshot_method, 'unavailable') as cost_provenance");
    }

    /** @param array{ids: array<int, int>|null, warehouse_id: int|null} $scope @param array<string, mixed> $context */
    private function applySaleFilters(Builder $query, array $scope, array $context, string $sale, string $item): void
    {
        if ($scope['ids'] !== null) {
            $query->whereIn("{$sale}.branch_id", $scope['ids']);
        }
        if ($scope['warehouse_id']) {
            $query->whereExists(function (Builder $stock) use ($scope, $sale): void {
                $stock->selectRaw('1')->from('stock_movements')
                    ->whereColumn('stock_movements.reference_id', "{$sale}.id")
                    ->where('stock_movements.reference_type', \App\Models\Pos\PosSale::class)
                    ->where('stock_movements.movement_type', 'sale')
                    ->where('stock_movements.warehouse_id', $scope['warehouse_id']);
            });
        }
        foreach (['product_id', 'category_id'] as $filter) {
            if ($context[$filter] ?? null) {
                $query->where("{$item}.{$filter}", $context[$filter]);
            }
        }
        if ($context['brand_id'] ?? null) {
            $query->where("{$item}.brand_id_snapshot", $context['brand_id']);
        }
        if ($context['customer_id'] ?? null) {
            $query->where("{$sale}.customer_id", $context['customer_id']);
        }
        if ($context['cashier_id'] ?? null) {
            $query->where("{$sale}.completed_by", $context['cashier_id']);
        }
        if ($context['sale_channel'] ?? null) {
            $query->where("{$sale}.sale_type", $context['sale_channel']);
        }
        if (($context['discounted'] ?? null) !== null) {
            $query->where("{$item}.discount_amount", $context['discounted'] ? '>' : '=', 0);
        }
    }

    /** @param array<int, string> $dimensions @param array{timezone: string} $range */
    private function groups(Builder $rows, array $dimensions, array $range, bool $invoices = false): array
    {
        $grouped = DB::query()->fromSub($rows, 'profitability_rows')
            ->select($dimensions)
            ->selectRaw("COUNT(DISTINCT document_key) as invoice_count, COALESCE(SUM(quantity_sold), 0) as quantity_sold, COALESCE(SUM(ABS(quantity_returned)), 0) as quantity_returned, COALESCE(SUM(net_quantity), 0) as net_quantity, COALESCE(SUM(gross_sales), 0) as gross_sales, COALESCE(SUM(discounts), 0) as discounts, COALESCE(SUM(return_sales), 0) as return_sales, COALESCE(SUM(net_sales), 0) as net_sales, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN net_sales ELSE 0 END), 0) as known_cost_net_sales, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN cogs ELSE 0 END), 0) as cost, COALESCE(SUM(CASE WHEN cost_status IN ('captured', 'reconstructed') THEN gross_profit ELSE 0 END), 0) as gross_profit, COALESCE(SUM(CASE WHEN entry_type = 'sale' AND cost_status = 'unavailable' THEN 1 ELSE 0 END), 0) as unavailable_cost_item_count")
            ->groupBy($dimensions)->orderByDesc('gross_profit')->limit(500)->get();

        return $grouped->map(function ($row) use ($dimensions, $range, $invoices): array {
            $netSales = $this->minor($row->net_sales);
            $knownCostNetSales = $this->minor($row->known_cost_net_sales);
            $profit = $this->minor($row->gross_profit);
            $base = [
                'invoice_count' => (int) $row->invoice_count,
                'quantity_sold' => (string) $row->quantity_sold,
                'quantity_returned' => (string) $row->quantity_returned,
                'net_quantity' => (string) $row->net_quantity,
                'gross_sales' => $this->minor($row->gross_sales),
                'discounts' => $this->minor($row->discounts),
                'return_impact' => abs($this->minor($row->return_sales)),
                'net_sales' => $netSales,
                'known_cost_net_sales' => $knownCostNetSales,
                'cost' => $this->minor($row->cost),
                'gross_profit' => $profit,
                'margin_percent' => $this->percent($profit, $knownCostNetSales),
                'revenue_cost_coverage_percent' => $this->percent($knownCostNetSales, $netSales),
                'unavailable_cost_item_count' => (int) $row->unavailable_cost_item_count,
            ];
            if ($invoices) {
                return ['source' => $row->source, 'invoice' => $row->document_number, 'date' => CarbonImmutable::parse($row->document_date)->setTimezone($range['timezone'])->toDateString(), 'customer' => $row->customer_name, 'outlet' => $row->outlet_name, 'salesperson' => $row->salesperson_name] + $base;
            }
            $label = match ($dimensions[0]) {
                'product_id' => $row->product_name ?: 'Unclassified',
                'category_id' => $row->category_name ?: 'Uncategorized',
                'brand_id' => $row->brand_name ?: 'Unbranded',
                'outlet_id' => $row->outlet_name ?: 'Unassigned',
                default => $row->salesperson_name ?: 'Unassigned',
            };

            return ['dimension' => $label, 'sku' => $dimensions[0] === 'product_id' ? $row->sku : null] + $base;
        })->all();
    }

    /** @param array<int, string> $parts */
    private function concat(array $parts): string
    {
        return DB::getDriverName() === 'sqlite' ? implode(' || ', $parts) : 'CONCAT('.implode(', ', $parts).')';
    }

    private function minor(mixed $value): int
    {
        $value = (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $amount = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$amount : $amount;
    }

    private function percent(int $numerator, int $denominator): string
    {
        if ($denominator === 0) {
            return '0.0000';
        }
        $scaled = intdiv((abs($numerator) * 1000000) + intdiv(abs($denominator), 2), abs($denominator));
        $scaled = $numerator < 0 ? -$scaled : $scaled;

        return intdiv($scaled, 10000).'.'.str_pad((string) abs($scaled % 10000), 4, '0', STR_PAD_LEFT);
    }
}
