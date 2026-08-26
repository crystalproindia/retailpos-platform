<?php

namespace App\Http\Controllers\CommandCenter\Finance;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmCustomer;
use App\Models\Finance\FinanceReconciliation;
use App\Models\Purchases\Supplier;
use App\Services\AuditLogger;
use App\Services\Finance\CustomerCreditService;
use App\Services\Finance\CustomerPaymentAllocationService;
use App\Services\Finance\FinanceStatementPdfService;
use App\Services\Finance\PayableService;
use App\Services\Finance\ReceivableService;
use App\Support\Finance\FinanceAmount;
use App\Support\Finance\FinanceCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function receivables(Request $request, ReceivableService $service): View
    {
        return view('command-center.finance.receivables', $service->dashboard($request->user(), $request->only(['search', 'outlet_id', 'customer_id', 'bucket', 'from', 'to'])));
    }

    public function receivablesCsv(Request $request, ReceivableService $service): StreamedResponse
    {
        $query = $service->openQuery($request->user(), $request->only(['search', 'outlet_id', 'customer_id', 'bucket', 'from', 'to']))->with('customer');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Invoice', 'Customer', 'Invoice date', 'Due date', 'Original total', 'Paid', 'Credits', 'Outstanding']);
            $query->orderBy('id')->lazyById(500)->each(function ($invoice) use ($handle): void {
                fputcsv($handle, array_map([FinanceCsv::class, 'cell'], [$invoice->invoice_number, $invoice->billing_company ?: $invoice->billing_name, $invoice->issue_date?->toDateString(), $invoice->due_date?->toDateString(), $invoice->grand_total, $invoice->amount_paid, $invoice->credited_total, $invoice->balance_due]));
            });
            fclose($handle);
        }, 'receivable-aging.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function payables(Request $request, PayableService $service): View
    {
        return view('command-center.finance.payables', $service->dashboard($request->user(), $request->only(['search', 'outlet_id', 'supplier_id', 'bucket'])));
    }

    public function payablesCsv(Request $request, PayableService $service): StreamedResponse
    {
        $query = $service->openQuery($request->user(), $request->only(['search', 'outlet_id', 'supplier_id', 'bucket', 'from', 'to']))->with('supplier');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Bill', 'Supplier', 'Bill date', 'Due date', 'Original total', 'Paid', 'Outstanding']);
            $query->orderBy('id')->lazyById(500)->each(function ($invoice) use ($handle): void {
                fputcsv($handle, array_map([FinanceCsv::class, 'cell'], [$invoice->invoice_number, $invoice->supplier?->name, $invoice->supplier_invoice_date?->toDateString(), $invoice->due_date?->toDateString(), $invoice->grand_total, $invoice->paid_total, $invoice->outstanding_total]));
            });
            fclose($handle);
        }, 'payable-aging.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function customerStatement(Request $request, ReceivableService $service, int $customer): View
    {
        $record = $this->customer($request, $customer);
        [$from, $to] = $this->period($request);

        return view('command-center.finance.customer-statement', [
            'customer' => $record,
            'from' => $from,
            'to' => $to,
            'statement' => $service->statement($request->user(), $record, $from, $to),
            'summary' => $service->customerSummary($request->user(), $record),
            'credits' => $service->availableCredits($request->user(), $record->id),
            'openInvoices' => $service->openQuery($request->user(), ['customer_id' => $record->id])->orderBy('due_date')->limit(100)->get(),
        ]);
    }

    public function supplierStatement(Request $request, PayableService $service, int $supplier): View
    {
        $record = $this->supplier($request, $supplier);
        [$from, $to] = $this->period($request);

        return view('command-center.finance.supplier-statement', ['supplier' => $record, 'from' => $from, 'to' => $to, 'statement' => $service->statement($request->user(), $record, $from, $to), 'summary' => $service->supplierSummary($request->user(), $record)]);
    }

    public function customerPdf(Request $request, FinanceStatementPdfService $pdf, int $customer): Response
    {
        $record = $this->customer($request, $customer);
        [$from, $to] = $this->period($request);

        return $pdf->customer($request->user(), $record, $from, $to)->download('customer-statement-'.$record->id.'.pdf');
    }

    public function supplierPdf(Request $request, FinanceStatementPdfService $pdf, int $supplier): Response
    {
        $record = $this->supplier($request, $supplier);
        [$from, $to] = $this->period($request);

        return $pdf->supplier($request->user(), $record, $from, $to)->download('supplier-statement-'.$record->id.'.pdf');
    }

    public function customerCsv(Request $request, ReceivableService $service, int $customer): StreamedResponse
    {
        $record = $this->customer($request, $customer);
        [$from, $to] = $this->period($request);

        return $this->statementCsv('customer-statement-'.$record->id.'.csv', $service->statement($request->user(), $record, $from, $to));
    }

    public function supplierCsv(Request $request, PayableService $service, int $supplier): StreamedResponse
    {
        $record = $this->supplier($request, $supplier);
        [$from, $to] = $this->period($request);

        return $this->statementCsv('supplier-statement-'.$record->id.'.csv', $service->statement($request->user(), $record, $from, $to));
    }

    public function paymentCreate(Request $request, ReceivableService $service): View
    {
        $payment = $request->integer('payment_id') ? $service->paymentQuery($request->user())->with('customer')->findOrFail($request->integer('payment_id')) : null;
        $customer = $payment?->customer ?: ($request->integer('customer_id') ? $this->customer($request, $request->integer('customer_id')) : null);
        $invoices = $customer ? $service->openQuery($request->user(), ['customer_id' => $customer->id])->orderBy('due_date')->limit(100)->get() : collect();

        return view('command-center.finance.payment-allocation', compact('customer', 'invoices', 'payment'));
    }

    public function allocatePayment(Request $request, CustomerPaymentAllocationService $service, ReceivableService $receivables, int $payment): RedirectResponse
    {
        $record = $receivables->paymentQuery($request->user())->findOrFail($payment);
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'allocations' => ['required', 'array', 'min:1', 'max:100'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'decimal:0,2', 'min:0'],
        ]);
        $allocations = collect($data['allocations'])->filter(fn (array $allocation): bool => FinanceAmount::minor($allocation['amount'] ?? 0) > 0)->values()->all();
        if ($allocations === []) {
            return back()->withErrors(['allocations' => 'Apply an amount to at least one invoice.']);
        }
        $service->allocate($record, $request->user(), $allocations, $data['idempotency_key']);

        return redirect()->route('finance.reconciliation.index')->with('status', 'The payment allocation was saved.');
    }

    public function paymentStore(Request $request, CustomerPaymentAllocationService $service): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'upi', 'card', 'cheque', 'other'])],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'allocations' => ['nullable', 'array', 'max:100'],
            'allocations.*.invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['nullable', 'decimal:0,2', 'min:0'],
        ]);
        $data['allocations'] = collect($data['allocations'] ?? [])->filter(fn (array $allocation): bool => (float) ($allocation['amount'] ?? 0) > 0)->values()->all();
        $payment = $service->record($request->user(), $data);

        return redirect()->route('finance.reconciliation.index')->with('status', 'Customer payment recorded. '.($payment->unallocated_amount > 0 ? 'The remaining amount is available for allocation.' : 'The payment is fully allocated.'));
    }

    public function applyCredit(Request $request, CustomerCreditService $service): RedirectResponse
    {
        $data = $request->validate(['return_id' => ['required', 'integer'], 'invoice_id' => ['required', 'integer'], 'amount' => ['required', 'decimal:0,2', 'gt:0'], 'idempotency_key' => ['required', 'uuid']]);
        $service->apply($request->user(), (int) $data['return_id'], (int) $data['invoice_id'], $data['amount'], $data['idempotency_key']);

        return back()->with('status', 'Customer credit applied to the invoice.');
    }

    public function updateCreditLimit(Request $request, AuditLogger $audit, int $customer): RedirectResponse
    {
        $record = $this->customer($request, $customer);
        $data = $request->validate(['credit_limit' => ['nullable', 'decimal:0,2', 'min:0'], 'credit_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650']]);
        $before = $record->only(['credit_limit', 'credit_terms_days']);
        $record->update($data);
        $audit->record('finance.customer_credit_limit.updated', $record, 'Customer credit terms updated', ['company_id' => $record->company_id, 'before' => $before, 'after' => $record->only(['credit_limit', 'credit_terms_days'])]);

        return back()->with('status', 'Customer credit terms updated.');
    }

    public function reconciliation(Request $request, ReceivableService $receivables, PayableService $payables): View
    {
        return view('command-center.finance.reconciliation', [
            'customerPayments' => $receivables->paymentQuery($request->user())->with('customer')->where('unallocated_amount', '>', 0)->whereNotIn('status', ['failed', 'reversed'])->latest('payment_date')->paginate(20, ['*'], 'customers'),
            'supplierPayments' => $payables->paymentQuery($request->user())->with('supplier')->where('unallocated_amount', '>', 0)->where('status', 'recorded')->latest('payment_date')->paginate(20, ['*'], 'suppliers'),
        ]);
    }

    public function reconcile(Request $request, ReceivableService $receivables, PayableService $payables, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['payment_type' => ['required', Rule::in(['customer', 'supplier'])], 'payment_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:2000']]);
        $payment = $data['payment_type'] === 'customer'
            ? $receivables->paymentQuery($request->user())->findOrFail($data['payment_id'])
            : $payables->paymentQuery($request->user())->findOrFail($data['payment_id']);
        $reconciliation = FinanceReconciliation::query()->updateOrCreate(
            ['company_id' => $request->user()->company_id, 'payment_type' => $data['payment_type'], 'payment_id' => $payment->id],
            ['branch_id' => $payment->branch_id, 'status' => 'reviewed', 'note' => $data['note'] ?? null, 'reconciled_by' => $request->user()->id, 'reconciled_at' => now()],
        );
        $audit->record('finance.payment.reconciled', $reconciliation, 'Finance payment reconciliation reviewed', ['company_id' => $request->user()->company_id]);

        return back()->with('status', 'Reconciliation review saved.');
    }

    private function customer(Request $request, int $id): CrmCustomer
    {
        return CrmCustomer::query()->where('company_id', $request->user()->company_id)->findOrFail($id);
    }

    private function supplier(Request $request, int $id): Supplier
    {
        return Supplier::query()->where('company_id', $request->user()->company_id)->findOrFail($id);
    }

    /** @return array{CarbonImmutable,CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $to = CarbonImmutable::parse($data['to'] ?? today()->toDateString());
        $from = CarbonImmutable::parse($data['from'] ?? $to->subYear()->addDay()->toDateString());

        return [$from, $to];
    }

    /** @param array{opening:int,closing:int,rows:mixed} $statement */
    private function statementCsv(string $filename, array $statement): StreamedResponse
    {
        return response()->streamDownload(function () use ($statement): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Balance']);
            fputcsv($handle, ['', '', 'Opening balance', '', '', number_format($statement['opening'] / 100, 2, '.', '')]);
            foreach ($statement['rows'] as $row) {
                fputcsv($handle, array_map([FinanceCsv::class, 'cell'], [$row['date'], $row['reference'], $row['description'], number_format($row['debit'] / 100, 2, '.', ''), number_format($row['credit'] / 100, 2, '.', ''), number_format($row['balance'] / 100, 2, '.', '')]));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
