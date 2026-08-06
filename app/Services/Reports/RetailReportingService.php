<?php

namespace App\Services\Reports;

use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosReturn;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\PurchaseReturnItem;
use App\Models\Purchases\Supplier;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RetailReportingService
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @param array<string, mixed> $filters */
    public function overview(User $user, array $filters): array
    {
        $scope = $this->scope($user, $filters);
        $range = $this->range($user, $filters);
        $context = $this->context($user, $filters);
        $invoices = $this->invoices($user, $scope, $range, $context);
        $sales = $this->sales($user, $scope, $range, $context);
        $returns = $this->returns($user, $scope, $range, $context);
        $salesReturns = $this->salesReturns($user, $scope, $range, $context);
        $purchases = $this->purchases($user, $scope, $range, $returns['value'], $context);
        $payments = $this->payments($user, $scope, $range, $context);
        $stock = $this->stock($user, $scope, $context);
        $outlets = $this->outletPerformance($user, $scope, $range, $context);
        $cashiers = $this->cashierPerformance($user, $scope, $range, $context);
        $netSalesAfterReturns = max(0, $sales['net_sales'] - $salesReturns['refund_total']);

        return [
            'scope' => $scope,
            'range' => $range,
            'metrics' => [
                'gross_sales' => $sales['gross_sales'],
                'net_sales' => $netSalesAfterReturns,
                'invoice_count' => $sales['count'],
                'average_order_value' => $sales['count'] ? intdiv($netSalesAfterReturns, $sales['count']) : null,
                'purchase_total' => $purchases['total'],
                'payments_received' => $payments['received'],
                'outstanding_receivables' => $invoices['outstanding'],
                'return_value' => $returns['value'],
                'sales_return_value' => $salesReturns['refund_total'],
                'stock_value' => $stock['value'],
                'low_stock_count' => $stock['low_stock_count'],
            ],
            'reports' => [
                'sales' => $sales + $invoices + ['returns_total' => $salesReturns['refund_total'], 'net_sales_after_returns' => $netSalesAfterReturns, 'rows' => $this->salesRows($user, $scope, $range, $context)],
                'purchases' => $purchases + ['rows' => $this->purchaseRows($user, $scope, $range, $context)],
                'inventory' => $stock + ['rows' => $this->stockRows($user, $scope, $context)],
                'movements' => $this->stockMovements($user, $scope, $range, $context),
                'profitability' => ['net_sales' => $netSalesAfterReturns, 'cost_of_goods_sold' => null, 'gross_profit' => null, 'notice' => 'Gross profit is unavailable until a reliable invoice-level cost snapshot exists.'],
                'gst' => $this->gst($user, $scope, $range, $context) + ['rows' => $this->gstRows($user, $scope, $range, $context)],
                'payments' => $payments + ['rows' => $this->paymentRows($user, $scope, $range, $context)],
                'outstanding' => $invoices + ['rows' => $this->outstandingRows($user, $scope, $range, $context)],
                'returns' => $returns + ['rows' => $this->returnRows($user, $scope, $range, $context)],
                'sales_returns' => $salesReturns + ['rows' => $this->salesReturnRows($user, $scope, $range, $context)],
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

            return ['ids' => null, 'label' => 'All Outlets', 'warehouse_id' => $this->warehouse($user, null, $filters)];
        }

        $available = $this->outlets->accessibleOutlets($user);
        if ($available->isEmpty()) {
            throw ValidationException::withMessages(['outlet_id' => 'No active outlet is assigned to this user.']);
        }
        $id = $requested ? (int) $requested : $this->outlets->current($user)->id;
        if (! $available->contains('id', $id)) {
            throw ValidationException::withMessages(['outlet_id' => 'That outlet is not available to this user.']);
        }

        return ['ids' => [$id], 'label' => $available->firstWhere('id', $id)->name, 'warehouse_id' => $this->warehouse($user, [$id], $filters)];
    }

    /** @param array<string, mixed> $filters */
    private function range(User $user, array $filters): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone');
        $to = filled($filters['date_to'] ?? null) ? CarbonImmutable::parse($filters['date_to'], $timezone) : CarbonImmutable::now($timezone);
        $from = filled($filters['date_from'] ?? null) ? CarbonImmutable::parse($filters['date_from'], $timezone) : $to->startOfMonth();
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_to' => 'Select a date range of up to 366 days.']);
        }

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'timezone' => $timezone];
    }

    /** @param array<int, int>|null $outletIds */
    private function warehouse(User $user, ?array $outletIds, array $filters): ?int
    {
        if (! filled($filters['warehouse_id'] ?? null)) {
            return null;
        }

        $warehouse = Warehouse::query()
            ->where('company_id', $user->company_id)
            ->when($outletIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $outletIds))
            ->find((int) $filters['warehouse_id']);

        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => 'That warehouse is not available in the selected outlet scope.']);
        }

        return $warehouse->id;
    }

    /** @param array<string, mixed> $filters */
    private function context(User $user, array $filters): array
    {
        return [
            'product_id' => $this->companyModelId($user, $filters, 'product_id', Product::class),
            'category_id' => $this->companyModelId($user, $filters, 'category_id', InventoryCategory::class),
            'customer_id' => $this->companyModelId($user, $filters, 'customer_id', Customer::class),
            'supplier_id' => $this->companyModelId($user, $filters, 'supplier_id', Supplier::class),
            'cashier_id' => $this->companyModelId($user, $filters, 'cashier_id', User::class),
            'payment_method' => $filters['payment_method'] ?? null,
            'status' => $filters['status'] ?? null,
            'sale_channel' => $filters['sale_channel'] ?? null,
            'discounted' => array_key_exists('discounted', $filters) ? filter_var($filters['discounted'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null,
            'tax_classification' => $filters['tax_classification'] ?? null,
            'movement_type' => $filters['movement_type'] ?? null,
            'stock_status' => $filters['stock_status'] ?? null,
        ];
    }

    /** @param class-string<Model> $model */
    private function companyModelId(User $user, array $filters, string $key, string $model): ?int
    {
        if (! filled($filters[$key] ?? null)) {
            return null;
        }

        $id = (int) $filters[$key];
        if (! $model::query()->where('company_id', $user->company_id)->whereKey($id)->exists()) {
            throw ValidationException::withMessages([$key => 'That selection is not available for this company.']);
        }

        return $id;
    }

    /** @param array<int, int>|null $scope */
    private function invoices(User $user, array $scope, array $range, array $context): array
    {
        $query = $this->invoiceFilters($this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereDate('issue_date', '>=', $range['from']->toDateString())
            ->whereDate('issue_date', '<=', $range['to']->toDateString());
        $aging = ['Current' => 0, '1-30 days' => 0, '31-60 days' => 0, '61-90 days' => 0, '91+ days' => 0];
        foreach ((clone $query)->where('balance_due', '>', 0)->get(['due_date', 'balance_due']) as $invoice) {
            $dueDate = $invoice->due_date?->setTimezone($range['timezone'])->startOfDay();
            $daysPastDue = $dueDate ? max(0, $dueDate->diffInDays($range['to']->startOfDay(), false)) : 0;
            $bucket = match (true) {
                $daysPastDue === 0 => 'Current',
                $daysPastDue <= 30 => '1-30 days',
                $daysPastDue <= 60 => '31-60 days',
                $daysPastDue <= 90 => '61-90 days',
                default => '91+ days',
            };
            $aging[$bucket] += $this->minor($invoice->balance_due);
        }

        return ['gross_sales' => $this->minor((clone $query)->sum('grand_total')), 'discounts' => $this->minor((clone $query)->sum('discount_total')), 'tax' => $this->minor((clone $query)->sum('tax_total')), 'outstanding' => $this->minor((clone $query)->sum('balance_due')), 'count' => (clone $query)->count(), 'aging' => collect($aging)->map(fn (int $amount, string $bucket) => ['bucket' => $bucket, 'outstanding' => $amount])->values()->all()];
    }

    private function sales(User $user, array $scope, array $range, array $context): array
    {
        $pos = $this->salesFilters($this->branchScope(PosSale::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereBetween('sold_at', $this->timestampRange($range));

        return ['gross_sales' => $this->minor((clone $pos)->sum('subtotal')), 'discounts' => $this->minor((clone $pos)->sum('discount_amount')), 'tax' => $this->minor((clone $pos)->sum('tax_amount')), 'net_sales' => $this->minor((clone $pos)->sum('total_amount')), 'count' => (clone $pos)->count(), 'source' => filled($context['status']) ? 'POS sales filtered by the selected status; CRM invoice reporting remains separate to prevent unlinked records being double counted.' : 'Completed POS sales only; CRM invoice reporting remains separate to prevent unlinked records being double counted.'];
    }

    private function purchases(User $user, array $scope, array $range, int $returnValue, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];
        $query = $this->purchaseFilters($this->branchScope(PurchaseInvoice::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->whereDate('supplier_invoice_date', '>=', $range['from']->toDateString())
            ->whereDate('supplier_invoice_date', '<=', $range['to']->toDateString());
        $grossTotal = $this->minor((clone $query)->sum('grand_total'));

        return ['gross_total' => $grossTotal, 'return_value' => $returnValue, 'total' => $grossTotal - $returnValue, 'tax' => $this->minor((clone $query)->sum('input_cgst')) + $this->minor((clone $query)->sum('input_sgst')) + $this->minor((clone $query)->sum('input_igst')) + $this->minor((clone $query)->sum('input_cess')), 'paid' => $this->minor((clone $query)->sum('paid_total')), 'outstanding' => $this->minor((clone $query)->sum('outstanding_total')), 'count' => (clone $query)->count()];
    }

    private function payments(User $user, array $scope, array $range, array $context): array
    {
        $query = $this->paymentFilters($this->paymentScope(CrmInvoicePayment::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereDate('payment_date', '>=', $range['from']->toDateString())
            ->whereDate('payment_date', '<=', $range['to']->toDateString());

        return ['received' => $this->minor((clone $query)->sum('amount')), 'count' => (clone $query)->count()];
    }

    private function returns(User $user, array $scope, array $range, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];
        $query = $this->returnItemFilters(PurchaseReturnItem::query(), $context)->whereHas('purchaseReturn', function (Builder $returns) use ($user, $scope, $range, $warehouseId, $context): void {
            $this->purchaseReturnFilters($this->branchScope($returns->where('company_id', $user->company_id), $scope['ids']), $context)
                ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
                ->whereDate('return_date', '>=', $range['from']->toDateString())
                ->whereDate('return_date', '<=', $range['to']->toDateString());
        });
        $items = (clone $query)->get(['quantity', 'unit_cost']);

        return ['value' => $items->sum(fn (PurchaseReturnItem $item) => $this->quantityValue($item->quantity, $item->unit_cost)), 'count' => $items->count(), 'notice' => 'Purchase returns are supported. Sales-return and refund totals are unavailable until a sales-return ledger is introduced.'];
    }

    private function salesReturns(User $user, array $scope, array $range, array $context): array
    {
        $query = $this->branchScope(PosReturn::query()->where('company_id', $user->company_id)->where('status', PosReturn::STATUS_COMPLETED), $scope['ids'])
            ->whereBetween('completed_at', $this->timestampRange($range))
            ->when($context['customer_id'] ?? null, fn (Builder $returns, $customerId) => $returns->where('customer_id', $customerId));

        return ['refund_total' => $this->minor((clone $query)->sum('refund_total')), 'store_credit_total' => $this->minor((clone $query)->sum('store_credit_total')), 'tax_adjustment_total' => $this->minor((clone $query)->sum('tax_adjustment_total')), 'exchange_payable_total' => $this->minor((clone $query)->sum('exchange_payable_total')), 'exchange_refund_total' => $this->minor((clone $query)->sum('exchange_refund_total')), 'count' => (clone $query)->count(), 'notice' => 'Completed POS returns only. Manual refund records are operational records; no payment-gateway action is implied.'];
    }

    private function stock(User $user, array $scope, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];
        $query = $this->stockFilters($this->branchScope(StockLevel::query()->where('stock_levels.company_id', $user->company_id)->when($warehouseId, fn (Builder $query) => $query->where('stock_levels.warehouse_id', $warehouseId))->join('products', 'products.id', '=', 'stock_levels.product_id'), $scope['ids']), $context);

        return ['value' => $this->minor((clone $query)->selectRaw('COALESCE(ROUND(SUM(stock_levels.quantity_on_hand * products.cost_price), 2), 0) as total')->value('total')), 'low_stock_count' => (clone $query)->whereColumn('quantity_on_hand', '<=', 'minimum_stock')->count(), 'method' => 'Current on-hand quantity multiplied by the product current cost price. This is current valuation, not historical FIFO or weighted-average valuation.'];
    }

    private function stockMovements(User $user, array $scope, array $range, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];
        $query = $this->movementFilters($this->branchScope(
            StockMovement::query()
                ->where('company_id', $user->company_id)
                ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
                ->whereBetween('occurred_at', $this->timestampRange($range)),
            $scope['ids'],
        ), $context);
        $movements = (clone $query)->get(['direction', 'quantity']);
        $inQuantity = $movements->where('direction', 'in')->sum(fn (StockMovement $movement) => $this->quantityThousandths($movement->quantity));
        $outQuantity = $movements->where('direction', 'out')->sum(fn (StockMovement $movement) => $this->quantityThousandths($movement->quantity));

        return [
            'movement_count' => $movements->count(),
            'in_quantity' => $this->quantityDisplay($inQuantity),
            'out_quantity' => $this->quantityDisplay($outQuantity),
            'notice' => 'Movement quantities are operational ledger entries. Transfers appear at both source and destination and do not change consolidated stock on hand.',
            'rows' => (clone $query)->with(['branch:id,name', 'warehouse:id,name', 'product:id,name,sku'])
                ->latest('occurred_at')->limit(500)->get()
                ->map(fn (StockMovement $movement) => [
                    'date' => $movement->occurred_at?->setTimezone($range['timezone'])->toDateString(),
                    'movement_type' => $movement->movement_type,
                    'direction' => $movement->direction,
                    'product' => $movement->product?->name,
                    'sku' => $movement->product?->sku,
                    'outlet' => $movement->branch?->name,
                    'warehouse' => $movement->warehouse?->name,
                    'quantity' => (string) $movement->quantity,
                    'quantity_before' => (string) $movement->quantity_before,
                    'quantity_after' => (string) $movement->quantity_after,
                    'unit_cost' => $this->minor($movement->unit_cost),
                ])->all(),
        ];
    }

    private function gst(User $user, array $scope, array $range, array $context): array
    {
        $query = $this->invoiceFilters($this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereDate('issue_date', '>=', $range['from']->toDateString())
            ->whereDate('issue_date', '<=', $range['to']->toDateString());

        return ['taxable_sales' => $this->minor((clone $query)->sum('taxable_total')), 'cgst' => $this->minor((clone $query)->sum('cgst_total')), 'sgst' => $this->minor((clone $query)->sum('sgst_total')), 'igst' => $this->minor((clone $query)->sum('igst_total')), 'cess' => $this->minor((clone $query)->sum('cess_total')), 'incomplete_count' => (clone $query)->whereNull('place_of_supply_state_code')->count(), 'notice' => 'Preparation aid only. Review incomplete GSTIN, place-of-supply, and HSN/SAC data before filing.'];
    }

    private function salesRows(User $user, array $scope, array $range, array $context): array
    {
        return $this->salesFilters($this->branchScope(PosSale::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereBetween('sold_at', $this->timestampRange($range))
            ->with('branch:id,name')->latest('sold_at')->limit(500)->get()
            ->map(fn (PosSale $sale) => ['date' => $sale->sold_at?->setTimezone($range['timezone'])->toDateString(), 'reference' => $sale->sale_number, 'outlet' => $sale->branch?->name, 'net_sales' => $this->minor($sale->total_amount), 'paid' => $this->minor($sale->paid_amount), 'status' => $sale->status])->all();
    }

    private function purchaseRows(User $user, array $scope, array $range, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];

        return $this->purchaseFilters($this->branchScope(PurchaseInvoice::query()->where('company_id', $user->company_id)->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId)), $scope['ids']), $context)
            ->whereDate('supplier_invoice_date', '>=', $range['from']->toDateString())
            ->whereDate('supplier_invoice_date', '<=', $range['to']->toDateString())
            ->with(['supplier:id,name', 'branch:id,name'])->latest('supplier_invoice_date')->limit(500)->get()
            ->map(fn (PurchaseInvoice $invoice) => ['date' => $invoice->supplier_invoice_date?->toDateString(), 'reference' => $invoice->invoice_number, 'supplier' => $invoice->supplier?->name, 'outlet' => $invoice->branch?->name, 'total' => $this->minor($invoice->grand_total), 'outstanding' => $this->minor($invoice->outstanding_total), 'status' => $invoice->status])->all();
    }

    private function stockRows(User $user, array $scope, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];

        return $this->stockFilters($this->branchScope(StockLevel::query()->where('company_id', $user->company_id)->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId)), $scope['ids']), $context)
            ->with(['product:id,name,sku,cost_price', 'branch:id,name'])->orderByDesc('quantity_on_hand')->limit(500)->get()
            ->map(fn (StockLevel $level) => ['product' => $level->product?->name, 'sku' => $level->product?->sku, 'outlet' => $level->branch?->name, 'quantity' => (string) $level->quantity_on_hand, 'unit_cost' => $this->minor($level->product?->cost_price), 'value' => $this->quantityValue($level->quantity_on_hand, $level->product?->cost_price)])->all();
    }

    private function paymentRows(User $user, array $scope, array $range, array $context): array
    {
        return $this->paymentFilters($this->paymentScope(CrmInvoicePayment::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereDate('payment_date', '>=', $range['from']->toDateString())
            ->whereDate('payment_date', '<=', $range['to']->toDateString())
            ->with(['invoice:id,invoice_number,branch_id', 'invoice.branch:id,name', 'branch:id,name', 'recorder:id,name'])->latest('payment_date')->limit(500)->get()
            ->map(fn (CrmInvoicePayment $payment) => ['date' => $payment->payment_date?->toDateString(), 'reference' => $payment->payment_reference, 'invoice' => $payment->invoice?->invoice_number, 'outlet' => $payment->branch?->name ?? $payment->invoice?->branch?->name, 'method' => $payment->payment_method, 'amount' => $this->minor($payment->amount), 'status' => $payment->status?->value ?? $payment->status])->all();
    }

    private function outstandingRows(User $user, array $scope, array $range, array $context): array
    {
        return $this->invoiceFilters($this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->where('balance_due', '>', 0)
            ->whereDate('issue_date', '>=', $range['from']->toDateString())
            ->whereDate('issue_date', '<=', $range['to']->toDateString())
            ->with(['branch:id,name', 'customer:id,company_name'])->orderByDesc('balance_due')->limit(500)->get()
            ->map(fn (CrmInvoice $invoice) => ['invoice' => $invoice->invoice_number, 'customer' => $invoice->customer?->company_name ?? $invoice->billing_name, 'outlet' => $invoice->branch?->name, 'due_date' => $invoice->due_date?->toDateString(), 'outstanding' => $this->minor($invoice->balance_due), 'status' => $invoice->status?->value ?? $invoice->status])->all();
    }

    private function gstRows(User $user, array $scope, array $range, array $context): array
    {
        return $this->invoiceFilters($this->branchScope(CrmInvoice::query()->where('company_id', $user->company_id), $scope['ids']), $context)
            ->whereDate('issue_date', '>=', $range['from']->toDateString())
            ->whereDate('issue_date', '<=', $range['to']->toDateString())
            ->with('branch:id,name')->latest('issue_date')->limit(500)->get()
            ->map(fn (CrmInvoice $invoice) => [
                'date' => $invoice->issue_date?->toDateString(),
                'invoice' => $invoice->invoice_number,
                'outlet' => $invoice->branch?->name,
                'taxable_sales' => $this->minor($invoice->taxable_total),
                'cgst' => $this->minor($invoice->cgst_total),
                'sgst' => $this->minor($invoice->sgst_total),
                'igst' => $this->minor($invoice->igst_total),
                'cess' => $this->minor($invoice->cess_total),
                'place_of_supply' => $invoice->place_of_supply_state_code ?: 'Missing',
            ])->all();
    }

    private function returnRows(User $user, array $scope, array $range, array $context): array
    {
        $warehouseId = $scope['warehouse_id'];

        return $this->returnItemFilters(PurchaseReturnItem::query(), $context)->with(['purchaseReturn.branch:id,name', 'purchaseReturn.supplier:id,name', 'product:id,name'])->whereHas('purchaseReturn', function (Builder $returns) use ($user, $scope, $range, $warehouseId, $context): void {
            $this->purchaseReturnFilters($this->branchScope($returns->where('company_id', $user->company_id), $scope['ids']), $context)->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))->whereDate('return_date', '>=', $range['from']->toDateString())->whereDate('return_date', '<=', $range['to']->toDateString());
        })->limit(500)->get()
            ->map(fn (PurchaseReturnItem $item) => ['date' => $item->purchaseReturn?->return_date?->toDateString(), 'reference' => $item->purchaseReturn?->return_number, 'supplier' => $item->purchaseReturn?->supplier?->name, 'product' => $item->product?->name, 'outlet' => $item->purchaseReturn?->branch?->name, 'quantity' => (string) $item->quantity, 'value' => $this->quantityValue($item->quantity, $item->unit_cost)])->all();
    }

    private function salesReturnRows(User $user, array $scope, array $range, array $context): array
    {
        return $this->branchScope(PosReturn::query()->where('company_id', $user->company_id)->where('status', PosReturn::STATUS_COMPLETED), $scope['ids'])
            ->whereBetween('completed_at', $this->timestampRange($range))
            ->when($context['customer_id'] ?? null, fn (Builder $returns, $customerId) => $returns->where('customer_id', $customerId))
            ->with(['branch:id,name', 'customer:id,display_name', 'originalSale:id,sale_number,receipt_number'])
            ->latest('completed_at')->limit(500)->get()
            ->map(fn (PosReturn $return) => ['date' => $return->completed_at?->setTimezone($range['timezone'])->toDateString(), 'return_number' => $return->return_number, 'credit_note' => $return->credit_note_number, 'original_sale' => $return->originalSale?->receipt_number ?: $return->originalSale?->sale_number, 'customer' => $return->customer?->display_name ?: 'Walk-in', 'outlet' => $return->branch?->name, 'refund_total' => $this->minor($return->refund_total), 'store_credit_total' => $this->minor($return->store_credit_total), 'tax_adjustment_total' => $this->minor($return->tax_adjustment_total), 'status' => $return->status])->all();
    }

    private function outletPerformance(User $user, array $scope, array $range, array $context): array
    {
        $sales = $this->salesFilters($this->branchScope(PosSale::query()->where('pos_sales.company_id', $user->company_id), $scope['ids']), $context)
            ->whereBetween('sold_at', $this->timestampRange($range))
            ->join('branches', 'branches.id', '=', 'pos_sales.branch_id')
            ->selectRaw('branches.id, branches.name, COUNT(*) as sale_count, COALESCE(SUM(pos_sales.total_amount), 0) as net_sales, COALESCE(SUM(pos_sales.discount_amount), 0) as discounts')
            ->groupBy('branches.id', 'branches.name')->orderByDesc('net_sales')->get()
            ->map(fn ($row) => ['outlet' => $row->name, 'sales_count' => (int) $row->sale_count, 'net_sales' => $this->minor($row->net_sales), 'discounts' => $this->minor($row->discounts), 'average_order_value' => $row->sale_count ? intdiv($this->minor($row->net_sales), (int) $row->sale_count) : null])->all();

        return ['rows' => $sales, 'notice' => 'Outlet comparisons include only authorized outlets and completed POS sales.'];
    }

    private function cashierPerformance(User $user, array $scope, array $range, array $context): array
    {
        $sales = $this->salesFilters($this->branchScope(PosSale::query()->where('pos_sales.company_id', $user->company_id), $scope['ids']), $context)
            ->whereBetween('sold_at', $this->timestampRange($range))
            ->leftJoin('users', 'users.id', '=', 'pos_sales.completed_by')
            ->selectRaw("users.id as cashier_id, COALESCE(users.name, 'Unassigned') as cashier, COUNT(*) as sale_count, COALESCE(SUM(pos_sales.total_amount), 0) as net_sales, COALESCE(SUM(pos_sales.discount_amount), 0) as discounts")
            ->groupBy('users.id', 'users.name')->orderByDesc('net_sales')->get()
            ->map(fn ($row) => ['cashier_id' => $row->cashier_id ? (int) $row->cashier_id : null, 'cashier' => $row->cashier, 'sales_count' => (int) $row->sale_count, 'net_sales' => $this->minor($row->net_sales), 'discounts' => $this->minor($row->discounts), 'average_order_value' => $row->sale_count ? intdiv($this->minor($row->net_sales), (int) $row->sale_count) : null])->all();

        return ['rows' => $sales, 'notice' => 'Operational sales metrics only; this report does not make quality judgments.'];
    }

    private function salesFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['status'], fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->where('status', 'completed'))
            ->when($context['product_id'], fn (Builder $query, int $productId) => $query->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)))
            ->when($context['category_id'], fn (Builder $query, int $categoryId) => $query->whereHas('items', fn (Builder $items) => $items->where('category_id', $categoryId)))
            ->when($context['customer_id'], fn (Builder $query, int $customerId) => $query->where('customer_id', $customerId))
            ->when($context['cashier_id'], fn (Builder $query, int $cashierId) => $query->where('completed_by', $cashierId))
            ->when($context['payment_method'], fn (Builder $query, string $method) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('payment_method', $method)))
            ->when($context['sale_channel'], fn (Builder $query, string $channel) => $query->where('sale_type', $channel))
            ->when($context['discounted'] !== null, fn (Builder $query) => $context['discounted'] ? $query->where('discount_amount', '>', 0) : $query->where('discount_amount', '=', 0));
    }

    private function purchaseFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['status'], fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->whereNotIn('status', ['cancelled', 'draft']))
            ->when($context['supplier_id'], fn (Builder $query, int $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($context['product_id'], fn (Builder $query, int $productId) => $query->whereHas('items', fn (Builder $items) => $items->where('product_id', $productId)))
            ->when($context['category_id'], fn (Builder $query, int $categoryId) => $query->whereHas('items', fn (Builder $items) => $items->whereHas('product', fn (Builder $product) => $product->where('category_id', $categoryId))));
    }

    private function paymentFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['status'], fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->whereNotIn('status', ['failed', 'reversed']))
            ->when($context['payment_method'], fn (Builder $query, string $method) => $query->where('payment_method', $method));
    }

    private function invoiceFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['status'], fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->whereNotIn('status', ['cancelled', 'void']))
            ->when($context['tax_classification'], fn (Builder $query, string $classification) => $query->where('tax_classification', $classification));
    }

    private function purchaseReturnFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['status'], fn (Builder $query, string $status) => $query->where('status', $status), fn (Builder $query) => $query->where('status', 'approved'))
            ->when($context['supplier_id'], fn (Builder $query, int $supplierId) => $query->where('supplier_id', $supplierId));
    }

    private function returnItemFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['product_id'], fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($context['category_id'], fn (Builder $query, int $categoryId) => $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $categoryId)));
    }

    private function stockFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['product_id'], fn (Builder $query, int $productId) => $query->where('stock_levels.product_id', $productId))
            ->when($context['category_id'], fn (Builder $query, int $categoryId) => $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $categoryId)))
            ->when($context['stock_status'] === 'negative', fn (Builder $query) => $query->where('stock_levels.quantity_on_hand', '<', 0))
            ->when($context['stock_status'] === 'out', fn (Builder $query) => $query->where('stock_levels.quantity_on_hand', '=', 0))
            ->when($context['stock_status'] === 'low', fn (Builder $query) => $query->where('stock_levels.quantity_on_hand', '>', 0)->whereColumn('stock_levels.quantity_on_hand', '<=', 'stock_levels.minimum_stock'))
            ->when($context['stock_status'] === 'available', fn (Builder $query) => $query->where('stock_levels.quantity_on_hand', '>', 0));
    }

    private function movementFilters(Builder $query, array $context): Builder
    {
        return $query
            ->when($context['product_id'], fn (Builder $query, int $productId) => $query->where('product_id', $productId))
            ->when($context['category_id'], fn (Builder $query, int $categoryId) => $query->whereHas('product', fn (Builder $product) => $product->where('category_id', $categoryId)))
            ->when($context['movement_type'], fn (Builder $query, string $type) => $query->where('movement_type', $type));
    }

    private function branchScope(Builder $query, ?array $ids): Builder
    {
        return $ids === null ? $query : $query->whereIn($query->getModel()->qualifyColumn('branch_id'), $ids);
    }

    /** @param array{from: CarbonImmutable, to: CarbonImmutable, timezone: string} $range @return array<int, CarbonImmutable> */
    private function timestampRange(array $range): array
    {
        return [$range['from']->utc(), $range['to']->utc()];
    }

    /** @param array<int, int>|null $ids */
    private function paymentScope(Builder $query, ?array $ids): Builder
    {
        if ($ids === null) {
            return $query;
        }

        return $query->where(function (Builder $payments) use ($ids): void {
            $payments->whereIn('branch_id', $ids)->orWhere(function (Builder $legacyPayments) use ($ids): void {
                $legacyPayments->whereNull('branch_id')->whereHas('invoice', fn (Builder $invoice) => $invoice->whereIn('branch_id', $ids));
            });
        });
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

    private function quantityThousandths(mixed $value): int
    {
        $value = (string) ($value ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $quantity = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');

        return $negative ? -$quantity : $quantity;
    }

    private function quantityDisplay(int $thousandths): string
    {
        return number_format($thousandths / 1000, 3, '.', '');
    }

    private function quantityValue(mixed $quantity, mixed $unitCost): int
    {
        $value = (string) ($quantity ?? '0');
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $thousandths = ((int) $whole * 1000) + (int) str_pad(substr($fraction, 0, 3), 3, '0');
        $thousandths = $negative ? -$thousandths : $thousandths;
        $numerator = $thousandths * $this->minor($unitCost);

        return $numerator < 0 ? -intdiv(abs($numerator) + 500, 1000) : intdiv($numerator + 500, 1000);
    }
}
