<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Supplier;
use App\Services\Purchases\PurchaseInvoiceService;
use App\Services\Purchases\SupplierPayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request, SupplierPayableService $payables): View
    {
        $invoices = PurchaseInvoice::query()->with('supplier')->where('company_id', $request->user()->company_id)
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->supplier_id, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->latest('supplier_invoice_date')->paginate(20)->withQueryString();
        return view('command-center.purchases.invoices.index', ['invoices' => $invoices, 'suppliers' => Supplier::query()->where('company_id', $request->user()->company_id)->orderBy('name')->get(['id', 'name']), 'payable' => $payables->summary($request->user()->company_id)]);
    }

    public function create(Request $request): View
    {
        $receipts = GoodsReceipt::query()->with(['supplier', 'items.product'])->where('company_id', $request->user()->company_id)->whereIn('status', ['received', 'partially_accepted', 'closed'])->latest('receipt_date')->get();
        return view('command-center.purchases.invoices.create', ['receipts' => $receipts, 'suppliers' => Supplier::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request, PurchaseInvoiceService $service): RedirectResponse
    {
        $invoice = $service->create($request->user(), $this->validated($request));
        return redirect()->route('purchases.invoices.show', $invoice)->with('status', 'Purchase invoice saved as draft.');
    }

    public function show(Request $request, int $purchaseInvoice): View
    {
        return view('command-center.purchases.invoices.show', ['invoice' => $this->invoice($request, $purchaseInvoice)->load(['supplier', 'items.product', 'payments.payment'])]);
    }

    public function verify(Request $request, PurchaseInvoiceService $service, int $purchaseInvoice): RedirectResponse
    {
        $service->verify($this->invoice($request, $purchaseInvoice), $request->user());
        return back()->with('status', 'Purchase invoice sent for approval.');
    }

    public function approve(Request $request, PurchaseInvoiceService $service, int $purchaseInvoice): RedirectResponse
    {
        $service->approve($this->invoice($request, $purchaseInvoice), $request->user());
        return back()->with('status', 'Purchase invoice approved and included in supplier payables.');
    }

    public function cancel(Request $request, PurchaseInvoiceService $service, int $purchaseInvoice): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $service->cancel($this->invoice($request, $purchaseInvoice), $request->user(), $request->string('reason')->toString());
        return back()->with('status', 'Purchase invoice cancelled.');
    }

    private function invoice(Request $request, int $id): PurchaseInvoice
    {
        return PurchaseInvoice::query()->where('company_id', $request->user()->company_id)->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
            'supplier_invoice_number' => ['required', 'string', 'max:160'], 'supplier_invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date'],
            'place_of_supply_state_code' => ['nullable', 'string', 'size:2'], 'supplier_state_code' => ['nullable', 'string', 'size:2'], 'reverse_charge' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string'], 'idempotency_key' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'], 'items.*.goods_receipt_item_id' => ['nullable', 'integer'], 'items.*.product_id' => ['nullable', 'integer'], 'items.*.name_snapshot' => ['nullable', 'string', 'max:255'], 'items.*.hsn_sac' => ['nullable', 'string', 'max:16'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.unit_price' => ['required', 'numeric', 'min:0'], 'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
