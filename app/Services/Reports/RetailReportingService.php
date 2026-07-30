<?php

namespace App\Services\Reports;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Inventory\StockLevel;
use App\Models\Pos\PosSale;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseReturnItem;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class RetailReportingService
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string, mixed> $filters */
    public function overview(User $user, array $filters): array
    {
        $scope = $this->scope($user, $filters);
        $range = $this->range($user, $filters);
        $invoices = $this->invoices($user, $scope, $range);
        $sales = $this->sales($user, $scope, $range);
        $purchases = $this->purchases($user, $scope, $range);
        $payments = $this->payments($user, $scope, $range);
        $returns = $this->returns($user, $scope, $range);
        $stock = $this->stock($user, $scope);
        $outlets = $this->outletPerformance($user, $scope, $range);
        $cashiers = $this->cashierPerformance($user, $scope, $range);

        return [
            'scope' => $scope,
            'range' => $range,
            'metrics' => [
                'gross_sales' => $sales['gross_sales'],
                'net_sales' => $sales['net_sales'],
                'invoice_count' => $sales['count'],
                'average_order_value' => $sales['count'] ? intdiv($sales['net_sales'], $sales['count']) : null,
                'purchase_total' => $purchases['total'],
                'payments_received' => $payments['received'],
                'outstanding_receivables' => $invoices['outstanding'],
                'return_value' => $returns['value'],
                'stock_value' => $stock['value'],
                'low_stock_count' => $stock['low_stock_count'],
            ],
            'reports' => [
                'sales' => $sales + $invoices + ['rows' => $this->salesRows($user, $scope, $range)],
                'purchases' => $purchases + ['rows' => $this->purchaseRows($user, $scope, $range)],
                'inventory' => $stock + ['rows' => $this->stockRows($user, $scope)],
                'profitability' => ['net_sales' => $sales['net_sales'], 'cost_of_goods_sold' => null, 'gross_profit' => null, 'notice' => 'Gross profit is unavailable until a reliable invoice-level cost snapshot exists.'],
                'gst' => $this->gst($user, $scope, $range),
                'payments' => $payments + ['rows' => $this->paymentRows($user, $scope, $range)],
                'outstanding' => $invoices + ['rows' => $this->outstandingRows($user, $scope, $range)],
                'returns' => $returns + ['rows' => $this->returnRows($user, $scope, $range)],
                'outlets' => $outlets,
                'cashiers' => $cashiers,
            ],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function report(User $user, string $report, array $filters): array
    {
        $overview = $this->overview($user, $filters);
        abort_unless(array_key_exists($report, $overview['reports']), 404);

        return $overview + ['selected_report' => $report, 'detail' => $overview['reports'][$report]];
    }

    /** @param array<string, mixed> $filters */
    private function scope(User $user, array $filters): array
    {
        $requested = $filters['outlet_id'] ?? null;
        if ($requested === 'all') {
            if (! $this->outlets->hasCompanyWideAccess($user)) {
                throw ValidationException::withMessages(['outlet_id' => 'Only a company administrator can view all outlets.']);
            }

            return ['ids' => null, 'label' => 'All Outlets'];
        }

        $available = $this->outlets->accessibleOutlets($user);
        if ($available->isEmpty()) throw ValidationException::withMessages(['outlet_id' => 'No active outlet is assigned to this user.']);
        $id = $requested ? (int) $requested : $this->outlets->current($user)->id;
        if (! $available->contains('id', $id)) throw ValidationException::withMessages(['outlet_id' => 'That outlet is not available to this user.']);

        return ['ids' => [$id], 'label' => $available->firstWhere('id', $id)->name];
    }

    /** @param array<string, mixed> $filters */
    private function range(User $user, array $filters): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone');
        $to = filled($filters['date_to'] ?? null) ? CarbonImmutable::parse($filters['date_to'], $timezone) : CarbonImmutable::now($timezone);
        $from = filled($filters['date_from'] ?? null) ? CarbonImmutable::parse($filters['date_from'], $timezone) : $to->startOfMonth();
        if ($from->gt($to) || $from->diffInDays($to) > 366) throw ValidationException::withMessages(['date_to' => 'Select a date range of up to 366 days.']);

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'timezone' => $timezone];
    }

    /** @param array<int, int>|null $scope */
    private function invoices(User $user, array $scope, array $range): array
    {
        $query = $this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'void']), $scope['ids'])
            ->whereBetween('issue_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
        return ['gross_sales' => $this->minor((clone $query)->sum('grand_total')), 'discounts' => $this->minor((clone $query)->sum('discount_total')), 'tax' => $this->minor((clone $query)->sum('tax_total')), 'outstanding' => $this->minor((clone $query)->sum('balance_due')), 'count' => (clone $query)->count()];
    }

    private function sales(User $user, array $scope, array $range): array
    {
        $pos = $this->branchScope(PosSale::query()->where('company_id', $user->company_id)->where('status', 'completed'), $scope['ids'])
            ->whereBetween('sold_at', [$range['from'], $range['to']]);
        return ['gross_sales' => $this->minor((clone $pos)->sum('subtotal')), 'discounts' => $this->minor((clone $pos)->sum('discount_amount')), 'tax' => $this->minor((clone $pos)->sum('tax_amount')), 'net_sales' => $this->minor((clone $pos)->sum('total_amount')), 'count' => (clone $pos)->count(), 'source' => 'Completed POS sales only; CRM invoice reporting remains separate to prevent unlinked records being double counted.'];
    }

    private function purchases(User $user, array $scope, array $range): array
    {
        $query = $this->branchScope(PurchaseInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'draft']), $scope['ids'])
            ->whereBetween('supplier_invoice_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
        return ['total' => $this->minor((clone $query)->sum('grand_total')), 'tax' => $this->minor((clone $query)->sum('input_cgst')) + $this->minor((clone $query)->sum('input_sgst')) + $this->minor((clone $query)->sum('input_igst')) + $this->minor((clone $query)->sum('input_cess')), 'paid' => $this->minor((clone $query)->sum('paid_total')), 'outstanding' => $this->minor((clone $query)->sum('outstanding_total')), 'count' => (clone $query)->count()];
    }

    private function payments(User $user, array $scope, array $range): array
    {
        $query = $this->branchScope(CrmInvoicePayment::query()->where('company_id', $user->company_id)->whereNotIn('status', ['failed', 'reversed']), $scope['ids'])
            ->whereBetween('payment_date', [$range['from']->toDateString(), $range['to']->toDateString()]);
        return ['received' => $this->minor((clone $query)->sum('amount')), 'count' => (clone $query)->count()];
    }

    private function returns(User $user, array $scope, array $range): array
    {
        $query = PurchaseReturnItem::query()->whereHas('purchaseReturn', function (Builder $returns) use ($user, $scope, $range): void {
            $this->branchScope($returns->where('company_id', $user->company_id)->where('status', 'approved')->whereBetween('return_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids']);
        });
        return ['value' => $this->minor((clone $query)->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')->value('total')), 'count' => (clone $query)->count(), 'notice' => 'Purchase returns are supported. Sales-return and refund totals are unavailable until a sales-return ledger is introduced.'];
    }

    private function stock(User $user, array $scope): array
    {
        $query = $this->branchScope(StockLevel::query()->where('stock_levels.company_id', $user->company_id)->join('products', 'products.id', '=', 'stock_levels.product_id'), $scope['ids']);
        return ['value' => $this->minor((clone $query)->selectRaw('COALESCE(SUM(stock_levels.quantity_on_hand * products.cost_price), 0) as total')->value('total')), 'low_stock_count' => (clone $query)->whereColumn('quantity_on_hand', '<=', 'minimum_stock')->count(), 'method' => 'Current on-hand quantity multiplied by the product current cost price. This is current valuation, not historical FIFO or weighted-average valuation.'];
    }

    private function gst(User $user, array $scope, array $range): array
    {
        $query = $this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'void'])->whereBetween('issue_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids']);
        return ['taxable_sales' => $this->minor((clone $query)->sum('taxable_total')), 'cgst' => $this->minor((clone $query)->sum('cgst_total')), 'sgst' => $this->minor((clone $query)->sum('sgst_total')), 'igst' => $this->minor((clone $query)->sum('igst_total')), 'cess' => $this->minor((clone $query)->sum('cess_total')), 'notice' => 'Preparation aid only. Review incomplete GSTIN, place-of-supply, and HSN/SAC data before filing.'];
    }

    private function salesRows(User $user, array $scope, array $range): array
    {
        return $this->branchScope(PosSale::query()->where('company_id', $user->company_id)->where('status', 'completed')->whereBetween('sold_at', [$range['from'], $range['to']]), $scope['ids'])
            ->with('branch:id,name')->latest('sold_at')->limit(500)->get()
            ->map(fn (PosSale $sale) => ['date' => $sale->sold_at?->setTimezone($range['timezone'])->toDateString(), 'reference' => $sale->sale_number, 'outlet' => $sale->branch?->name, 'net_sales' => $this->minor($sale->total_amount), 'paid' => $this->minor($sale->paid_amount), 'status' => $sale->status])->all();
    }

    private function purchaseRows(User $user, array $scope, array $range): array
    {
        return $this->branchScope(PurchaseInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'draft'])->whereBetween('supplier_invoice_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids'])
            ->with(['supplier:id,name', 'branch:id,name'])->latest('supplier_invoice_date')->limit(500)->get()
            ->map(fn (PurchaseInvoice $invoice) => ['date' => $invoice->supplier_invoice_date?->toDateString(), 'reference' => $invoice->invoice_number, 'supplier' => $invoice->supplier?->name, 'outlet' => $invoice->branch?->name, 'total' => $this->minor($invoice->grand_total), 'outstanding' => $this->minor($invoice->outstanding_total), 'status' => $invoice->status])->all();
    }

    private function stockRows(User $user, array $scope): array
    {
        return $this->branchScope(StockLevel::query()->where('company_id', $user->company_id), $scope['ids'])->with(['product:id,name,sku,cost_price', 'branch:id,name'])->orderByDesc('quantity_on_hand')->limit(500)->get()
            ->map(fn (StockLevel $level) => ['product' => $level->product?->name, 'sku' => $level->product?->sku, 'outlet' => $level->branch?->name, 'quantity' => (string) $level->quantity_on_hand, 'unit_cost' => $this->minor($level->product?->cost_price), 'value' => $this->quantityValue($level->quantity_on_hand, $level->product?->cost_price)])->all();
    }

    private function paymentRows(User $user, array $scope, array $range): array
    {
        return $this->branchScope(CrmInvoicePayment::query()->where('company_id', $user->company_id)->whereNotIn('status', ['failed', 'reversed'])->whereBetween('payment_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids'])
            ->with(['invoice:id,invoice_number', 'branch:id,name', 'recorder:id,name'])->latest('payment_date')->limit(500)->get()
            ->map(fn (CrmInvoicePayment $payment) => ['date' => $payment->payment_date?->toDateString(), 'reference' => $payment->payment_reference, 'invoice' => $payment->invoice?->invoice_number, 'outlet' => $payment->branch?->name, 'method' => $payment->payment_method, 'amount' => $this->minor($payment->amount), 'status' => $payment->status?->value ?? $payment->status])->all();
    }

    private function outstandingRows(User $user, array $scope, array $range): array
    {
        return $this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id)->whereNotIn('status', ['cancelled', 'void'])->where('balance_due', '>', 0)->whereBetween('issue_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids'])
            ->with(['branch:id,name', 'customer:id,company_name'])->orderByDesc('balance_due')->limit(500)->get()
            ->map(fn (CrmInvoice $invoice) => ['invoice' => $invoice->invoice_number, 'customer' => $invoice->customer?->company_name ?? $invoice->billing_name, 'outlet' => $invoice->branch?->name, 'due_date' => $invoice->due_date?->toDateString(), 'outstanding' => $this->minor($invoice->balance_due), 'status' => $invoice->status?->value ?? $invoice->status])->all();
    }

    private function returnRows(User $user, array $scope, array $range): array
    {
        return PurchaseReturnItem::query()->with(['purchaseReturn.branch:id,name', 'purchaseReturn.supplier:id,name', 'product:id,name'])->whereHas('purchaseReturn', function (Builder $returns) use ($user, $scope, $range): void { $this->branchScope($returns->where('company_id', $user->company_id)->where('status', 'approved')->whereBetween('return_date', [$range['from']->toDateString(), $range['to']->toDateString()]), $scope['ids']); })->limit(500)->get()
            ->map(fn (PurchaseReturnItem $item) => ['date' => $item->purchaseReturn?->return_date?->toDateString(), 'reference' => $item->purchaseReturn?->return_number, 'supplier' => $item->purchaseReturn?->supplier?->name, 'product' => $item->product?->name, 'outlet' => $item->purchaseReturn?->branch?->name, 'quantity' => (string) $item->quantity, 'value' => $this->quantityValue($item->quantity, $item->unit_cost)])->all();
    }

    private function outletPerformance(User $user, array $scope, array $range): array
    {
        $sales = $this->branchScope(PosSale::query()->where('pos_sales.company_id', $user->company_id)->where('status', 'completed')->whereBetween('sold_at', [$range['from'], $range['to']]), $scope['ids'])
            ->join('branches', 'branches.id', '=', 'pos_sales.branch_id')
            ->selectRaw('branches.id, branches.name, COUNT(*) as sale_count, COALESCE(SUM(pos_sales.total_amount), 0) as net_sales')
            ->groupBy('branches.id', 'branches.name')->orderByDesc('net_sales')->get()
            ->map(fn ($row) => ['outlet' => $row->name, 'sales_count' => (int) $row->sale_count, 'net_sales' => $this->minor($row->net_sales)]);

        return ['rows' => $sales, 'notice' => 'Outlet comparisons include only authorized outlets and completed POS sales.'];
    }

    private function cashierPerformance(User $user, array $scope, array $range): array
    {
        $sales = $this->branchScope(PosSale::query()->where('pos_sales.company_id', $user->company_id)->where('status', 'completed')->whereBetween('sold_at', [$range['from'], $range['to']]), $scope['ids'])
            ->leftJoin('users', 'users.id', '=', 'pos_sales.completed_by')
            ->selectRaw("COALESCE(users.name, 'Unassigned') as cashier, COUNT(*) as sale_count, COALESCE(SUM(pos_sales.total_amount), 0) as net_sales")
            ->groupBy('users.id', 'users.name')->orderByDesc('net_sales')->get()
            ->map(fn ($row) => ['cashier' => $row->cashier, 'sales_count' => (int) $row->sale_count, 'net_sales' => $this->minor($row->net_sales)]);

        return ['rows' => $sales, 'notice' => 'Operational sales metrics only; this report does not make quality judgments.'];
    }

    private function branchScope(Builder $query, ?array $ids): Builder { return $ids === null ? $query : $query->whereIn($query->getModel()->qualifyColumn('branch_id'), $ids); }
    private function minor(mixed $value): int { $value = (string) ($value ?? '0'); [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, ''); return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0'); }
    private function quantityValue(mixed $quantity, mixed $unitCost): int { $value = (string) ($quantity ?? '0'); [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, ''); $thousandths = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0'); return intdiv($thousandths * $this->minor($unitCost), 1000); }
}
