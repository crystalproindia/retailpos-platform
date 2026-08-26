<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\Supplier;
use App\Services\Finance\PayableService;
use App\Services\Purchases\SupplierPayableService;
use App\Services\Purchases\SupplierPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierPaymentController extends Controller
{
    public function index(Request $request, SupplierPayableService $payables, PayableService $finance): View
    {
        return view('command-center.purchases.payments.index', ['payments' => $finance->paymentQuery($request->user())->with('supplier')->latest('payment_date')->paginate(20), 'payable' => $payables->summary($request->user()->company_id)]);
    }

    public function create(Request $request, PayableService $finance): View
    {
        $companyId = $request->user()->company_id;

        return view('command-center.purchases.payments.create', ['suppliers' => Supplier::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']), 'invoices' => $finance->openQuery($request->user())->with('supplier')->orderBy('due_date')->limit(100)->get()]);
    }

    public function store(Request $request, SupplierPaymentService $service): RedirectResponse
    {
        $data = $this->validated($request);
        $data['allocations'] = collect($data['allocations'] ?? [])->filter(fn (array $allocation): bool => ! empty($allocation['amount']))->values()->all();
        $payment = $service->record($request->user(), $data);

        return redirect()->route('purchases.payments.show', $payment)->with('status', 'Supplier payment recorded.');
    }

    public function show(Request $request, PayableService $finance, int $supplierPayment): View
    {
        return view('command-center.purchases.payments.show', ['payment' => $finance->paymentQuery($request->user())->with(['supplier', 'allocations.invoice'])->findOrFail($supplierPayment)]);
    }

    public function reverse(Request $request, SupplierPaymentService $service, PayableService $finance, int $supplierPayment): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $payment = $finance->paymentQuery($request->user())->findOrFail($supplierPayment);
        $service->reverse($payment, $request->user(), $request->string('reason')->toString());

        return back()->with('status', 'Supplier payment reversed.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        return $request->validate(['supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)], 'payment_date' => ['required', 'date'], 'payment_type' => ['required', Rule::in(['invoice_payment', 'supplier_advance', 'advance_adjustment', 'supplier_refund', 'debit_note_adjustment'])], 'payment_method' => ['required', 'string', 'max:32'], 'amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:160'], 'cheque_number' => ['nullable', 'string', 'max:80'], 'cheque_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string'], 'idempotency_key' => ['nullable', 'string', 'max:80'], 'allocations' => ['nullable', 'array'], 'allocations.*.purchase_invoice_id' => ['nullable', 'integer'], 'allocations.*.amount' => ['nullable', 'numeric', 'gt:0']]);
    }
}
