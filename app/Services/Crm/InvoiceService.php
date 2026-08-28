<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\InvoicePaymentStatus;
use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadPriority;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Crm\CrmQuotation;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\AuditLogger;
use App\Services\Branding\CompanyBrandingService;
use App\Services\Finance\CreditLimitService;
use App\Services\Finance\CrmPaymentNumberService;
use App\Services\Finance\FinanceBalanceService;
use App\Services\Outlets\OutletAccessService;
use App\Services\Saas\UsageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly UsageService $usage,
        private readonly OutletAccessService $outlets,
        private readonly CrmCustomerRepository $customers,
        private readonly SalesDocumentNumberService $numbers,
        private readonly DocumentTaxModeService $taxModes,
        private readonly CompanyBrandingService $branding,
        private readonly SalesDocumentPresentationService $presentations,
        private readonly FinanceBalanceService $financeBalances,
        private readonly CreditLimitService $creditLimits,
        private readonly CrmPaymentNumberService $paymentNumbers,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): CrmInvoice
    {
        return DB::transaction(function () use ($user, $data): CrmInvoice {
            $this->usage->assertWithinLimit($user->company, 'monthly_invoices');
            $data = $this->customerData($user, $data);
            $taxMode = $this->taxModes->normalize($user->company, $data['tax_mode'] ?? null);
            $outlet = $this->outlets->current($user);
            $calculation = $this->calculate($this->profitabilityItems($user, $data['items']), $data['adjustment_total'] ?? '0', $taxMode);
            $invoice = CrmInvoice::create(Arr::only($data, ['quotation_id', 'opportunity_id', 'lead_id', 'customer_id', 'crm_contact_id', 'billing_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address', 'billing_country', 'customer_tax_number', 'place_of_supply', 'tax_classification', 'currency', 'issue_date', 'due_date', 'notes', 'terms_conditions', 'internal_notes', 'do_not_remind_before']) + $calculation + $this->signatureSnapshot($user->company, (bool) ($data['show_authorized_signature'] ?? true)) + $this->presentations->snapshot($user->company, SalesDocumentPresentationService::INVOICE) + [
                'company_id' => $user->company_id,
                'branch_id' => $outlet->id,
                'invoice_number' => $this->numbers->nextInvoiceNumber($user->company_id),
                'tax_mode' => $taxMode,
                'status' => InvoiceStatus::Draft,
                'amount_paid' => '0.00',
                'balance_due' => $calculation['grand_total'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $invoice->items()->createMany($calculation['items']);
            $this->recordActivity($invoice, $user, 'Invoice '.$invoice->invoice_number.' created.');
            $this->audit->record('crm.invoice.created', $invoice, 'Sales invoice created', ['company_id' => $invoice->company_id]);
            if ($invoice->customer_id) {
                $this->audit->record('crm.invoice.created_from_customer', $invoice, 'Invoice created for CRM customer', [
                    'company_id' => $invoice->company_id,
                    'customer_id' => $invoice->customer_id,
                ]);
            }

            return $invoice->load(['items', 'quotation', 'lead']);
        });
    }

    public function createFromQuotation(CrmQuotation $quotation, User $user): CrmInvoice
    {
        if ($quotation->status?->value !== 'accepted') {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be converted to an invoice.']);
        }
        if ($quotation->invoices()->exists()) {
            throw ValidationException::withMessages(['quotation' => 'An invoice already exists for this quotation.']);
        }
        $quotation->loadMissing(['items', 'lead']);
        $items = $quotation->items->map(fn ($item): array => [
            'name' => $item->name, 'description' => $item->description, 'quantity' => $item->quantity,
            'unit' => $item->unit ?? 'unit', 'unit_price' => $item->unit_price,
            'discount_type' => $item->discount_type ?? 'fixed',
            'discount_value' => ($item->discount_type ?? 'fixed') === 'percentage' ? ($item->discount_percentage ?? 0) : $item->discount_amount,
            'tax_rate' => $item->tax_rate,
        ])->all();

        $invoice = $this->create($user, [
            'quotation_id' => $quotation->id, 'opportunity_id' => $quotation->opportunity_id, 'lead_id' => $quotation->lead_id,
            'customer_id' => $quotation->crmCustomer?->id, 'billing_name' => $quotation->customer_name,
            'billing_company' => $quotation->customer_company, 'billing_email' => $quotation->customer_email,
            'billing_phone' => $quotation->customer_phone, 'billing_address' => $quotation->billing_address,
            'currency' => $quotation->currency, 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(),
            'notes' => $quotation->notes, 'terms_conditions' => $quotation->terms_conditions, 'tax_mode' => $quotation->tax_mode, 'show_authorized_signature' => $quotation->show_authorized_signature, 'adjustment_total' => '0', 'items' => $items,
        ]);
        $this->audit->record('crm.invoice.converted_from_quotation', $invoice, 'Invoice created from accepted quotation', ['company_id' => $invoice->company_id, 'quotation_id' => $quotation->id]);

        return $invoice;
    }

    public function issue(CrmInvoice $invoice, User $user, bool $creditLimitOverride = false, ?string $overrideReason = null): CrmInvoice
    {
        return DB::transaction(function () use ($invoice, $user, $creditLimitOverride, $overrideReason): CrmInvoice {
            $invoice = CrmInvoice::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($invoice->id);
            $this->assertMutationAccess($invoice, $user);
            $this->ensureDraft($invoice);
            $this->usage->assertWithinLimit($user->company, 'monthly_invoices');
            $this->creditLimits->assertCanIssue($invoice, $user, $creditLimitOverride, $overrideReason);
            $invoice->update(['status' => InvoiceStatus::Issued, 'issue_date' => $invoice->issue_date ?? today(), 'updated_by' => $user->id]);
            $this->audit->record('crm.invoice.issued', $invoice, 'Invoice issued', ['company_id' => $invoice->company_id]);

            return $invoice->refresh();
        });
    }

    /** @param array<string,mixed> $data */
    public function update(CrmInvoice $invoice, User $user, array $data): CrmInvoice
    {
        return DB::transaction(function () use ($invoice, $user, $data): CrmInvoice {
            $invoice = $this->findInvoiceForUpdate($invoice->id, $user);
            $this->ensureDraft($invoice);
            $previousCustomerId = $invoice->customer_id;
            $data = $this->customerData($user, $data);
            $taxMode = $this->taxModes->normalize($user->company, $data['tax_mode'] ?? $invoice->tax_mode);
            $calculation = $this->calculate($this->profitabilityItems($user, $data['items']), $data['adjustment_total'] ?? '0', $taxMode);
            $invoice->update(Arr::only($data, [
                'customer_id', 'billing_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address', 'billing_country',
                'customer_tax_number', 'place_of_supply', 'tax_classification', 'currency', 'issue_date', 'due_date',
                'notes', 'terms_conditions', 'internal_notes', 'do_not_remind_before',
            ]) + $calculation + $this->signatureSnapshot($user->company, (bool) ($data['show_authorized_signature'] ?? $invoice->show_authorized_signature)) + $this->presentations->snapshot($user->company, SalesDocumentPresentationService::INVOICE) + [
                'tax_mode' => $taxMode,
                'balance_due' => $calculation['grand_total'],
                'updated_by' => $user->id,
            ]);
            $invoice->items()->delete();
            $invoice->items()->createMany($calculation['items']);
            $this->recordActivity($invoice, $user, 'Draft invoice '.$invoice->invoice_number.' updated.');
            $this->audit->record('crm.invoice.updated', $invoice, 'Draft invoice updated', ['company_id' => $invoice->company_id]);
            if ((int) $previousCustomerId !== (int) $invoice->customer_id) {
                $this->audit->record('crm.invoice.customer_changed', $invoice, 'Invoice customer changed before issue', [
                    'company_id' => $invoice->company_id,
                    'previous_customer_id' => $previousCustomerId,
                    'customer_id' => $invoice->customer_id,
                ]);
            }

            return $invoice->refresh()->load('items');
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function customerData(User $user, array $data): array
    {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = null;

            return $data;
        }

        $customer = $this->customers->findForUser($user, (int) $data['customer_id']);
        $data['customer_id'] = $customer->id;
        $data['billing_name'] = ($data['billing_name'] ?? null) ?: $customer->display_name;
        $data['billing_company'] = ($data['billing_company'] ?? null) ?: $customer->company_name;
        $data['billing_email'] = ($data['billing_email'] ?? null) ?: $customer->email;
        $data['billing_phone'] = ($data['billing_phone'] ?? null) ?: $customer->phone;
        $data['billing_address'] = ($data['billing_address'] ?? null) ?: $customer->billing_address;
        $data['billing_country'] = ($data['billing_country'] ?? null) ?: $customer->country;
        $data['customer_tax_number'] = ($data['customer_tax_number'] ?? null) ?: $customer->tax_number;

        return $data;
    }

    /** @param array<string,mixed> $data */
    public function recordPayment(CrmInvoice $invoice, User $user, array $data): CrmInvoicePayment
    {
        return DB::transaction(function () use ($invoice, $user, $data): CrmInvoicePayment {
            $invoice = $this->findInvoiceForUpdate($invoice->id, $user);
            if ($invoice->status?->isTerminal() || $invoice->status === InvoiceStatus::Draft) {
                throw ValidationException::withMessages(['invoice' => 'Payments can only be recorded against an issued invoice.']);
            }
            if (($data['currency'] ?? $invoice->currency) !== $invoice->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match the invoice currency.']);
            }
            $amountCents = $this->cents((string) $data['amount']);
            if ($amountCents <= 0 || $amountCents > $this->cents((string) $invoice->balance_due)) {
                throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero and cannot exceed the outstanding balance.']);
            }
            $key = hash('sha256', implode('|', [$invoice->id, $data['payment_date'], $this->decimal($amountCents), $data['payment_method'], $data['transaction_reference'] ?? '']));
            $existing = CrmInvoicePayment::query()->where('company_id', $invoice->company_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $payment = $invoice->payments()->create(Arr::only($data, ['amount', 'currency', 'payment_date', 'payment_method', 'transaction_reference', 'bank_name', 'cheque_number', 'notes', 'status']) + [
                'company_id' => $invoice->company_id, 'branch_id' => $invoice->branch_id, 'payment_reference' => $this->paymentNumbers->nextPaymentReference($invoice->company_id),
                'customer_id' => $invoice->customer_id, 'allocated_amount' => $data['amount'], 'unallocated_amount' => '0.00',
                'receipt_number' => $this->paymentNumbers->nextReceiptNumber(), 'recorded_by' => $user->id,
                'cleared_by' => ($data['status'] ?? 'recorded') === 'cleared' ? $user->id : null,
                'cleared_at' => ($data['status'] ?? 'recorded') === 'cleared' ? now() : null, 'idempotency_key' => $key,
            ]);
            CrmInvoicePaymentAllocation::create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $payment->amount,
                'idempotency_key' => hash('sha256', 'direct|'.$payment->id.'|'.$invoice->id),
                'created_by' => $user->id,
            ]);
            $this->refreshBalance($invoice, $user);
            $this->recordActivity($invoice, $user, 'Payment '.$payment->receipt_number.' recorded for '.$invoice->currency.' '.$payment->amount.'.');
            $this->audit->record('crm.invoice.payment_recorded', $payment, 'Invoice payment recorded', ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id]);

            return $payment->refresh();
        });
    }

    public function reversePayment(CrmInvoicePayment $payment, User $user, string $reason): CrmInvoicePayment
    {
        return DB::transaction(function () use ($payment, $user, $reason): CrmInvoicePayment {
            $payment = $this->findPaymentForUpdate($payment->id, $user);
            if ($payment->status === InvoicePaymentStatus::Reversed) {
                throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            }
            $payment->update(['status' => InvoicePaymentStatus::Reversed, 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            $this->refreshAllocatedInvoices($payment, $user);
            $this->audit->record('crm.invoice.payment_reversed', $payment, 'Invoice payment reversed', ['company_id' => $payment->company_id, 'invoice_id' => $payment->invoice_id]);

            return $payment->refresh();
        });
    }

    public function clearPayment(CrmInvoicePayment $payment, User $user): CrmInvoicePayment
    {
        return DB::transaction(function () use ($payment, $user): CrmInvoicePayment {
            $payment = $this->findPaymentForUpdate($payment->id, $user);
            if ($payment->status !== InvoicePaymentStatus::Pending) {
                throw ValidationException::withMessages(['payment' => 'Only pending payments can be marked as cleared.']);
            }
            $payment->update([
                'status' => InvoicePaymentStatus::Cleared,
                'cleared_by' => $user->id,
                'cleared_at' => now(),
            ]);
            $this->refreshAllocatedInvoices($payment, $user);
            $this->audit->record('crm.invoice.payment_cleared', $payment, 'Invoice payment cleared', [
                'company_id' => $payment->company_id,
                'invoice_id' => $payment->invoice_id,
            ]);

            return $payment->refresh();
        });
    }

    public function cancel(CrmInvoice $invoice, User $user): CrmInvoice
    {
        $this->assertMutationAccess($invoice, $user);
        if ($invoice->returns()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'An invoice with a finalized credit note cannot be cancelled. The original invoice and credit history must remain intact.']);
        }
        if ($invoice->amount_paid > 0) {
            throw ValidationException::withMessages(['invoice' => 'An invoice with payments cannot be cancelled without reversing its payments.']);
        }
        $invoice->update(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now(), 'updated_by' => $user->id]);
        $this->audit->record('crm.invoice.cancelled', $invoice, 'Invoice cancelled', ['company_id' => $invoice->company_id]);

        return $invoice->refresh();
    }

    public function refreshStatus(CrmInvoice $invoice, ?User $user = null): CrmInvoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) {
            return $invoice;
        }
        $status = $invoice->return_status === 'full'
            ? InvoiceStatus::Credited
            : ($invoice->balance_due <= 0 ? InvoiceStatus::Paid : ($invoice->amount_paid > 0 ? InvoiceStatus::PartiallyPaid : ($invoice->due_date?->isPast() ? InvoiceStatus::Overdue : $invoice->status)));
        $invoice->update(['status' => $status, 'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null, 'updated_by' => $user?->id ?? $invoice->updated_by]);

        return $invoice->refresh();
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    public function calculate(array $items, string|int|float $adjustment = '0', string $taxMode = DocumentTaxModeService::GST): array
    {
        $subtotal = $discountTotal = $taxTotal = 0;
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            $quantityMilli = $this->milli((string) $item['quantity']);
            $priceCents = $this->cents((string) $item['unit_price']);
            if ($quantityMilli <= 0 || $priceCents < 0) {
                throw ValidationException::withMessages(['items' => 'Invoice quantities must be positive and prices cannot be negative.']);
            }
            $gross = intdiv($quantityMilli * $priceCents + 500, 1000);
            $discountType = $item['discount_type'] ?? 'fixed';
            $discountValue = $item['discount_value'] ?? ($item['discount_amount'] ?? 0);
            $discount = $discountType === 'percentage' ? intdiv($gross * $this->milli((string) $discountValue) + 50000, 100000) : $this->cents((string) $discountValue);
            $discount = min(max(0, $discount), $gross);
            $taxRateMilli = $taxMode === DocumentTaxModeService::NO_GST ? 0 : $this->milli((string) ($item['tax_rate'] ?? 0));
            if ($taxRateMilli < 0 || $taxRateMilli > 100000) {
                throw ValidationException::withMessages(['items' => 'Tax rate must be between zero and 100 percent.']);
            }
            $lineSubtotal = $gross - $discount;
            $tax = intdiv($lineSubtotal * $taxRateMilli + 50000, 100000);
            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;
            $cost = $item['snapshot_unit_cost_cents'] ?? null;
            $totalCost = $cost === null ? null : intdiv($quantityMilli * $cost + 500, 1000);
            $normalized[] = ['product_id' => $item['product_id'] ?? null, 'category_id_snapshot' => $item['category_id_snapshot'] ?? null, 'brand_id_snapshot' => $item['brand_id_snapshot'] ?? null, 'name' => $item['name'], 'sku_snapshot' => $item['sku_snapshot'] ?? null, 'category_name_snapshot' => $item['category_name_snapshot'] ?? null, 'brand_name_snapshot' => $item['brand_name_snapshot'] ?? null, 'description' => $item['description'] ?? null, 'hsn_sac' => $item['hsn_sac'] ?? null, 'quantity' => $this->decimal($quantityMilli, 3), 'unit' => $item['unit'] ?? 'unit', 'unit_price' => $this->decimal($priceCents), 'unit_cost_snapshot' => $cost === null ? null : $this->decimal($cost), 'total_cost_snapshot' => $totalCost === null ? null : $this->decimal($totalCost), 'gross_sales_snapshot' => $this->decimal($gross), 'net_sales_snapshot' => $this->decimal($lineSubtotal), 'gross_profit_before_discount' => $totalCost === null ? null : $this->decimal($gross - $totalCost), 'gross_profit_snapshot' => $totalCost === null ? null : $this->decimal($lineSubtotal - $totalCost), 'gross_margin_percent_snapshot' => $totalCost === null ? null : $this->margin($lineSubtotal - $totalCost, $lineSubtotal), 'cost_snapshot_method' => $cost === null ? null : 'standard_cost', 'cost_snapshot_status' => $cost === null ? 'unavailable' : 'captured', 'discount_type' => $discountType, 'discount_value' => $discountType === 'percentage' ? $this->decimal($this->milli((string) $discountValue), 3) : $this->decimal($this->cents((string) $discountValue)), 'discount_amount' => $this->decimal($discount), 'tax_rate' => $this->decimal($taxRateMilli, 3), 'tax_amount' => $this->decimal($tax), 'line_subtotal' => $this->decimal($lineSubtotal), 'line_total' => $this->decimal($lineSubtotal + $tax), 'sort_order' => $index + 1];
        }
        $adjustmentCents = $this->cents((string) $adjustment);
        $grandTotal = $subtotal - $discountTotal + $taxTotal + $adjustmentCents;
        if ($grandTotal < 0) {
            throw ValidationException::withMessages(['adjustment_total' => 'Adjustment cannot reduce an invoice below zero.']);
        }

        return ['subtotal' => $this->decimal($subtotal), 'discount_total' => $this->decimal($discountTotal), 'taxable_total' => $this->decimal($subtotal - $discountTotal), 'tax_total' => $this->decimal($taxTotal), 'adjustment_total' => $this->decimal($adjustmentCents), 'grand_total' => $this->decimal($grandTotal), 'items' => $normalized];
    }

    /** @param array<int, array<string, mixed>> $items @return array<string, mixed> */
    public function calculateForUser(User $user, array $items, string $taxMode): array
    {
        return $this->calculate($this->profitabilityItems($user, $items), '0', $taxMode);
    }

    private function refreshBalance(CrmInvoice $invoice, User $user): void
    {
        $this->financeBalances->refreshInvoice($invoice, $user->id);
    }

    public function refreshFinancialBalance(CrmInvoice $invoice, User $user): CrmInvoice
    {
        $this->refreshBalance($invoice, $user);

        return $invoice->refresh();
    }

    private function findInvoiceForUpdate(int $invoiceId, User $user): CrmInvoice
    {
        $invoice = CrmInvoice::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($invoiceId);
        $this->assertMutationAccess($invoice, $user);

        return $invoice;
    }

    private function findPaymentForUpdate(int $paymentId, User $user): CrmInvoicePayment
    {
        $payment = CrmInvoicePayment::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($paymentId);
        if ($payment->branch_id === null) {
            abort_unless($this->outlets->hasCompanyWideAccess($user), 403);
        } else {
            $branch = $payment->branch()->first();
            abort_unless($branch && $this->outlets->canAccess($user, $branch), 403);
        }

        return $payment;
    }

    private function refreshAllocatedInvoices(CrmInvoicePayment $payment, User $user): void
    {
        $payment->allocations()->with('invoice')->get()->each(function (CrmInvoicePaymentAllocation $allocation) use ($user): void {
            $this->refreshBalance($this->findInvoiceForUpdate($allocation->invoice_id, $user), $user);
        });
    }

    private function assertMutationAccess(CrmInvoice $invoice, User $user): void
    {
        if ($invoice->company_id !== $user->company_id) {
            throw ValidationException::withMessages(['outlet' => 'This invoice is not available to your company.']);
        }

        if ($invoice->branch_id === null) {
            if (! $this->outlets->hasCompanyWideAccess($user)) {
                throw ValidationException::withMessages(['outlet' => 'Only a company administrator can change a historical invoice without an outlet.']);
            }

            return;
        }

        $outlet = $invoice->branch()->first();
        if (! $outlet || ! $this->outlets->canAccess($user, $outlet)) {
            throw ValidationException::withMessages(['outlet' => 'You are not assigned to this invoice outlet.']);
        }
    }

    private function ensureDraft(CrmInvoice $invoice): void
    {
        if (! $invoice->status?->isEditable()) {
            throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be issued.']);
        }
    }

    /** @return array<string,mixed> */
    private function signatureSnapshot($company, bool $show): array
    {
        $signature = $this->branding->signatureForCompany($company);

        return [
            'show_authorized_signature' => $show,
            'signature_path_snapshot' => $show ? $signature['path'] : null,
            'signatory_name_snapshot' => $show ? $signature['name'] : null,
            'signatory_designation_snapshot' => $show ? $signature['designation'] : null,
        ];
    }

    private function recordActivity(CrmInvoice $invoice, User $user, string $subject): void
    {
        CrmActivity::create(['company_id' => $invoice->company_id, 'crm_lead_id' => $invoice->lead_id, 'opportunity_id' => $invoice->opportunity_id, 'assigned_user_id' => $invoice->lead?->assigned_user_id, 'created_by' => $user->id, 'type' => ActivityType::Note, 'subject' => $subject, 'scheduled_at' => now(), 'completed_at' => now(), 'completed_by' => $user->id, 'follow_up_status' => 'completed', 'priority' => $invoice->lead?->priority ?? LeadPriority::Medium]);
    }

    private function cents(string $value): int
    {
        return $this->minor($value, 2);
    }

    /** @param array<int,array<string,mixed>> $items @return array<int,array<string,mixed>> */
    private function profitabilityItems(User $user, array $items): array
    {
        $ids = collect($items)->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $products = Product::query()->with(['category:id,name', 'brand:id,name'])->where('company_id', $user->company_id)->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

        return collect($items)->map(function (array $item) use ($products): array {
            if (empty($item['product_id'])) {
                return $item;
            }
            $product = $products->get((int) $item['product_id']);
            if (! $product) {
                throw ValidationException::withMessages(['items' => 'The selected product is unavailable.']);
            }

            return array_replace($item, ['product_id' => $product->id, 'name' => $product->name, 'snapshot_unit_cost_cents' => $this->cents((string) ($product->cost_price ?? '0.00')), 'sku_snapshot' => $product->sku, 'category_id_snapshot' => $product->category_id, 'category_name_snapshot' => $product->category?->name, 'brand_id_snapshot' => $product->brand_id, 'brand_name_snapshot' => $product->brand?->name]);
        })->all();
    }

    private function margin(int $profit, int $sales): string
    {
        if ($sales === 0) {
            return '0.0000';
        } $scaled = intdiv(abs($profit) * 1000000 + intdiv(abs($sales), 2), abs($sales));
        $scaled = $profit < 0 ? -$scaled : $scaled;

        return intdiv($scaled, 10000).'.'.str_pad((string) abs($scaled % 10000), 4, '0', STR_PAD_LEFT);
    }

    private function milli(string $value): int
    {
        return $this->minor($value, 3);
    }

    private function minor(string $value, int $scale): int
    {
        $value = trim($value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw ValidationException::withMessages(['amount' => 'A valid decimal amount is required.']);
        }
        $fraction = $matches[3] ?? '';
        $roundUp = strlen($fraction) > $scale && (int) $fraction[$scale] >= 5;
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $minor = ((int) $matches[2] * (10 ** $scale)) + (int) $fraction + ($roundUp ? 1 : 0);

        return $matches[1] === '-' ? -$minor : $minor;
    }

    private function decimal(int $minor, int $scale = 2): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);
        $factor = 10 ** $scale;

        return $sign.intdiv($minor, $factor).'.'.str_pad((string) ($minor % $factor), $scale, '0', STR_PAD_LEFT);
    }
}
