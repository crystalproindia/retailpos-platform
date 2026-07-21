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
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Saas\UsageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private readonly AuditLogger $audit, private readonly UsageService $usage) {}

    /** @param array<string,mixed> $data */
    public function create(User $user, array $data): CrmInvoice
    {
        return DB::transaction(function () use ($user, $data): CrmInvoice {
            $this->usage->assertWithinLimit($user->company, 'monthly_invoices');
            $calculation = $this->calculate($data['items'], $data['adjustment_total'] ?? '0');
            $invoice = CrmInvoice::create(Arr::only($data, ['quotation_id', 'opportunity_id', 'lead_id', 'customer_id', 'crm_contact_id', 'billing_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address', 'billing_country', 'customer_tax_number', 'place_of_supply', 'tax_classification', 'currency', 'issue_date', 'due_date', 'notes', 'terms_conditions', 'internal_notes', 'do_not_remind_before']) + $calculation + [
                'company_id' => $user->company_id,
                'invoice_number' => $this->nextNumber($user->company_id),
                'status' => InvoiceStatus::Draft,
                'amount_paid' => '0.00',
                'balance_due' => $calculation['grand_total'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $invoice->items()->createMany($calculation['items']);
            $this->recordActivity($invoice, $user, 'Invoice '.$invoice->invoice_number.' created.');
            $this->audit->record('crm.invoice.created', $invoice, 'Sales invoice created', ['company_id' => $invoice->company_id]);

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
            'notes' => $quotation->notes, 'terms_conditions' => $quotation->terms_conditions, 'adjustment_total' => '0', 'items' => $items,
        ]);
        $this->audit->record('crm.invoice.converted_from_quotation', $invoice, 'Invoice created from accepted quotation', ['company_id' => $invoice->company_id, 'quotation_id' => $quotation->id]);

        return $invoice;
    }

    public function issue(CrmInvoice $invoice, User $user): CrmInvoice
    {
        $this->ensureDraft($invoice);
        $invoice->update(['status' => InvoiceStatus::Issued, 'issue_date' => $invoice->issue_date ?? today(), 'updated_by' => $user->id]);
        $this->audit->record('crm.invoice.issued', $invoice, 'Invoice issued', ['company_id' => $invoice->company_id]);

        return $invoice->refresh();
    }

    /** @param array<string,mixed> $data */
    public function update(CrmInvoice $invoice, User $user, array $data): CrmInvoice
    {
        $this->ensureDraft($invoice);

        return DB::transaction(function () use ($invoice, $user, $data): CrmInvoice {
            $calculation = $this->calculate($data['items'], $data['adjustment_total'] ?? '0');
            $invoice->update(Arr::only($data, [
                'billing_name', 'billing_company', 'billing_email', 'billing_phone', 'billing_address', 'billing_country',
                'customer_tax_number', 'place_of_supply', 'tax_classification', 'currency', 'issue_date', 'due_date',
                'notes', 'terms_conditions', 'internal_notes', 'do_not_remind_before',
            ]) + $calculation + [
                'balance_due' => $calculation['grand_total'],
                'updated_by' => $user->id,
            ]);
            $invoice->items()->delete();
            $invoice->items()->createMany($calculation['items']);
            $this->recordActivity($invoice, $user, 'Draft invoice '.$invoice->invoice_number.' updated.');
            $this->audit->record('crm.invoice.updated', $invoice, 'Draft invoice updated', ['company_id' => $invoice->company_id]);

            return $invoice->refresh()->load('items');
        });
    }

    /** @param array<string,mixed> $data */
    public function recordPayment(CrmInvoice $invoice, User $user, array $data): CrmInvoicePayment
    {
        if ($invoice->status?->isTerminal() || $invoice->status === InvoiceStatus::Draft) {
            throw ValidationException::withMessages(['invoice' => 'Payments can only be recorded against an issued invoice.']);
        }
        if (($data['currency'] ?? $invoice->currency) !== $invoice->currency) {
            throw ValidationException::withMessages(['currency' => 'Payment currency must match the invoice currency.']);
        }
        $amountCents = $this->cents((string) $data['amount']);

        return DB::transaction(function () use ($invoice, $user, $data, $amountCents): CrmInvoicePayment {
            $invoice->refresh();
            if ($amountCents <= 0 || $amountCents > $this->cents((string) $invoice->balance_due)) {
                throw ValidationException::withMessages(['amount' => 'Payment must be greater than zero and cannot exceed the outstanding balance.']);
            }
            $key = hash('sha256', implode('|', [$invoice->id, $data['payment_date'], $data['amount'], $data['payment_method'], $data['transaction_reference'] ?? '']));
            $existing = CrmInvoicePayment::query()->where('company_id', $invoice->company_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                return $existing;
            }
            $payment = $invoice->payments()->create(Arr::only($data, ['amount', 'currency', 'payment_date', 'payment_method', 'transaction_reference', 'bank_name', 'cheque_number', 'notes', 'status']) + [
                'company_id' => $invoice->company_id, 'payment_reference' => $this->nextPaymentReference($invoice->company_id),
                'receipt_number' => $this->nextReceiptNumber($invoice->company_id), 'recorded_by' => $user->id,
                'cleared_by' => ($data['status'] ?? 'recorded') === 'cleared' ? $user->id : null,
                'cleared_at' => ($data['status'] ?? 'recorded') === 'cleared' ? now() : null, 'idempotency_key' => $key,
            ]);
            $this->refreshBalance($invoice, $user);
            $this->recordActivity($invoice, $user, 'Payment '.$payment->receipt_number.' recorded for '.$invoice->currency.' '.$payment->amount.'.');
            $this->audit->record('crm.invoice.payment_recorded', $payment, 'Invoice payment recorded', ['company_id' => $invoice->company_id, 'invoice_id' => $invoice->id]);

            return $payment->refresh();
        });
    }

    public function reversePayment(CrmInvoicePayment $payment, User $user, string $reason): CrmInvoicePayment
    {
        if ($payment->status === InvoicePaymentStatus::Reversed) {
            throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
        }
        return DB::transaction(function () use ($payment, $user, $reason): CrmInvoicePayment {
            $payment->update(['status' => InvoicePaymentStatus::Reversed, 'reversed_by' => $user->id, 'reversed_at' => now(), 'reversal_reason' => $reason]);
            $this->refreshBalance($payment->invoice()->firstOrFail(), $user);
            $this->audit->record('crm.invoice.payment_reversed', $payment, 'Invoice payment reversed', ['company_id' => $payment->company_id, 'invoice_id' => $payment->invoice_id]);

            return $payment->refresh();
        });
    }

    public function clearPayment(CrmInvoicePayment $payment, User $user): CrmInvoicePayment
    {
        if ($payment->status !== InvoicePaymentStatus::Pending) {
            throw ValidationException::withMessages(['payment' => 'Only pending payments can be marked as cleared.']);
        }

        return DB::transaction(function () use ($payment, $user): CrmInvoicePayment {
            $payment->update([
                'status' => InvoicePaymentStatus::Cleared,
                'cleared_by' => $user->id,
                'cleared_at' => now(),
            ]);
            $this->refreshBalance($payment->invoice()->firstOrFail(), $user);
            $this->audit->record('crm.invoice.payment_cleared', $payment, 'Invoice payment cleared', [
                'company_id' => $payment->company_id,
                'invoice_id' => $payment->invoice_id,
            ]);

            return $payment->refresh();
        });
    }

    public function cancel(CrmInvoice $invoice, User $user): CrmInvoice
    {
        if ($invoice->amount_paid > 0) { throw ValidationException::withMessages(['invoice' => 'An invoice with payments cannot be cancelled without reversing its payments.']); }
        $invoice->update(['status' => InvoiceStatus::Cancelled, 'cancelled_at' => now(), 'updated_by' => $user->id]);
        $this->audit->record('crm.invoice.cancelled', $invoice, 'Invoice cancelled', ['company_id' => $invoice->company_id]);

        return $invoice->refresh();
    }

    public function refreshStatus(CrmInvoice $invoice, ?User $user = null): CrmInvoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled, InvoiceStatus::Void], true)) { return $invoice; }
        $status = $invoice->balance_due <= 0 ? InvoiceStatus::Paid : ($invoice->amount_paid > 0 ? InvoiceStatus::PartiallyPaid : ($invoice->due_date?->isPast() ? InvoiceStatus::Overdue : $invoice->status));
        $invoice->update(['status' => $status, 'paid_at' => $status === InvoiceStatus::Paid ? ($invoice->paid_at ?? now()) : null, 'updated_by' => $user?->id ?? $invoice->updated_by]);

        return $invoice->refresh();
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,mixed> */
    public function calculate(array $items, string|int|float $adjustment = '0'): array
    {
        $subtotal = $discountTotal = $taxTotal = 0;
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            $quantityMilli = $this->milli((string) $item['quantity']);
            $priceCents = $this->cents((string) $item['unit_price']);
            if ($quantityMilli <= 0 || $priceCents < 0) { throw ValidationException::withMessages(['items' => 'Invoice quantities must be positive and prices cannot be negative.']); }
            $gross = intdiv($quantityMilli * $priceCents + 500, 1000);
            $discountType = $item['discount_type'] ?? 'fixed';
            $discountValue = $item['discount_value'] ?? ($item['discount_amount'] ?? 0);
            $discount = $discountType === 'percentage' ? intdiv($gross * $this->milli((string) $discountValue) + 50000, 100000) : $this->cents((string) $discountValue);
            $discount = min(max(0, $discount), $gross);
            $taxRateMilli = $this->milli((string) ($item['tax_rate'] ?? 0));
            if ($taxRateMilli < 0 || $taxRateMilli > 100000) { throw ValidationException::withMessages(['items' => 'Tax rate must be between zero and 100 percent.']); }
            $lineSubtotal = $gross - $discount;
            $tax = intdiv($lineSubtotal * $taxRateMilli + 50000, 100000);
            $subtotal += $gross; $discountTotal += $discount; $taxTotal += $tax;
            $normalized[] = ['name' => $item['name'], 'description' => $item['description'] ?? null, 'quantity' => $this->decimal($quantityMilli, 3), 'unit' => $item['unit'] ?? 'unit', 'unit_price' => $this->decimal($priceCents), 'discount_type' => $discountType, 'discount_value' => $discountType === 'percentage' ? $this->decimal($this->milli((string) $discountValue), 3) : $this->decimal($this->cents((string) $discountValue)), 'discount_amount' => $this->decimal($discount), 'tax_rate' => $this->decimal($taxRateMilli, 3), 'tax_amount' => $this->decimal($tax), 'line_subtotal' => $this->decimal($lineSubtotal), 'line_total' => $this->decimal($lineSubtotal + $tax), 'sort_order' => $index + 1];
        }
        $adjustmentCents = $this->cents((string) $adjustment);
        $grandTotal = $subtotal - $discountTotal + $taxTotal + $adjustmentCents;
        if ($grandTotal < 0) { throw ValidationException::withMessages(['adjustment_total' => 'Adjustment cannot reduce an invoice below zero.']); }
        return ['subtotal' => $this->decimal($subtotal), 'discount_total' => $this->decimal($discountTotal), 'taxable_total' => $this->decimal($subtotal - $discountTotal), 'tax_total' => $this->decimal($taxTotal), 'adjustment_total' => $this->decimal($adjustmentCents), 'grand_total' => $this->decimal($grandTotal), 'items' => $normalized];
    }

    private function refreshBalance(CrmInvoice $invoice, User $user): void
    {
        $paidCents = (int) $invoice->payments()->whereNotIn('status', [
            InvoicePaymentStatus::Reversed->value,
            InvoicePaymentStatus::Failed->value,
            InvoicePaymentStatus::Pending->value,
        ])->get()->sum(fn (CrmInvoicePayment $payment) => $this->cents((string) $payment->amount));
        $totalCents = $this->cents((string) $invoice->grand_total);
        $invoice->update(['amount_paid' => $this->decimal($paidCents), 'balance_due' => $this->decimal(max(0, $totalCents - $paidCents)), 'updated_by' => $user->id]);
        $this->refreshStatus($invoice->refresh(), $user);
    }

    private function ensureDraft(CrmInvoice $invoice): void { if (! $invoice->status?->isEditable()) { throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be issued.']); } }
    private function nextNumber(int $companyId): string { $year = now()->format('Y'); $last = CrmInvoice::query()->where('company_id', $companyId)->where('invoice_number', 'like', "RPOS-INV-{$year}-%")->lockForUpdate()->latest('id')->value('invoice_number'); return "RPOS-INV-{$year}-".str_pad((string) ((int) substr((string) $last, -5) + 1), 5, '0', STR_PAD_LEFT); }
    private function nextPaymentReference(int $companyId): string { return 'RPOS-PAY-'.now()->format('Y').'-'.str_pad((string) (CrmInvoicePayment::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT); }
    private function nextReceiptNumber(int $companyId): string { return 'RPOS-RCPT-'.now()->format('Y').'-'.str_pad((string) (CrmInvoicePayment::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1), 5, '0', STR_PAD_LEFT); }
    private function recordActivity(CrmInvoice $invoice, User $user, string $subject): void { CrmActivity::create(['company_id' => $invoice->company_id, 'crm_lead_id' => $invoice->lead_id, 'opportunity_id' => $invoice->opportunity_id, 'assigned_user_id' => $invoice->lead?->assigned_user_id, 'created_by' => $user->id, 'type' => ActivityType::Note, 'subject' => $subject, 'scheduled_at' => now(), 'completed_at' => now(), 'completed_by' => $user->id, 'follow_up_status' => 'completed', 'priority' => $invoice->lead?->priority ?? LeadPriority::Medium]); }
    private function cents(string $value): int { return $this->minor($value, 2); }
    private function milli(string $value): int { return $this->minor($value, 3); }
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
