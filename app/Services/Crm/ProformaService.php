<?php
namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\ProformaStatus;
use App\Events\Domain\Crm\ProformaEvent;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProformaService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data, ?int $leadId = null, ?int $customerId = null, ?int $quotationId = null): CrmProformaInvoice
    {
        return DB::transaction(function () use ($user, $data, $leadId, $customerId, $quotationId): CrmProformaInvoice {
            $calculation = $this->calc($data['items']);
            $proforma = CrmProformaInvoice::create(array_merge(collect($data)->except('items')->all(), $calculation, [
                'company_id' => $user->company_id,
                'lead_id' => $leadId,
                'customer_id' => $customerId,
                'quotation_id' => $quotationId,
                'proforma_number' => $this->number($user->company_id),
                'status' => ProformaStatus::Draft,
                'paid_amount' => 0,
                'balance_amount' => $calculation['grand_total'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]));
            $proforma->items()->createMany($calculation['items']);
            $this->activity($proforma, $user, "Proforma invoice {$proforma->proforma_number} created.");
            $this->audit->record('crm.proforma.created', $proforma, 'Proforma invoice created', ['company_id' => $user->company_id]);
            $this->dispatch('crm.proforma.created', $proforma, $user);

            return $proforma->load(['lead', 'items']);
        });
    }

    public function markSent(CrmProformaInvoice $proforma, User $user): CrmProformaInvoice
    {
        if ($proforma->status !== ProformaStatus::Draft) {
            throw ValidationException::withMessages(['proforma' => 'Only draft proformas can be sent.']);
        }

        $proforma->update(['status' => ProformaStatus::Sent, 'sent_at' => $proforma->sent_at ?: now(), 'updated_by' => $user->id]);
        $this->activity($proforma, $user, 'Proforma invoice sent.');
        $this->audit->record('crm.proforma.sent', $proforma, 'Proforma invoice sent', ['company_id' => $proforma->company_id]);
        $this->dispatch('crm.proforma.sent', $proforma, $user);

        return $proforma->refresh();
    }

    /** @param array<string, mixed> $data */
    public function payment(CrmProformaInvoice $proforma, User $user, array $data): CrmProformaInvoice
    {
        if ((float) $data['amount'] > (float) $proforma->balance_amount) {
            throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
        }

        return DB::transaction(function () use ($proforma, $user, $data): CrmProformaInvoice {
            $proforma->payments()->create($data + ['recorded_by' => $user->id]);
            $paid = (float) $proforma->payments()->sum('amount');
            $balance = max(0, (float) $proforma->grand_total - $paid);
            $status = $paid >= (float) $proforma->grand_total
                ? ProformaStatus::Paid
                : ($paid > 0 ? ProformaStatus::PartiallyPaid : $proforma->status);
            $proforma->update([
                'paid_amount' => $paid,
                'balance_amount' => $balance,
                'status' => $status,
                'paid_at' => $status === ProformaStatus::Paid ? ($proforma->paid_at ?: now()) : null,
                'updated_by' => $user->id,
            ]);
            $this->activity($proforma, $user, 'Payment of '.$proforma->currency.' '.number_format((float) $data['amount'], 2).' recorded.');
            if ($status === ProformaStatus::Paid) {
                $this->activity($proforma, $user, 'Proforma invoice fully paid.');
            }
            $this->audit->record('crm.proforma.payment_recorded', $proforma, 'Proforma payment recorded', ['amount' => $data['amount'], 'company_id' => $proforma->company_id]);
            $this->dispatch('crm.proforma.payment_recorded', $proforma, $user, ['amount' => $data['amount'], 'currency' => $proforma->currency]);
            if ($status === ProformaStatus::Paid) {
                $this->dispatch('crm.proforma.fully_paid', $proforma, $user, ['amount' => $data['amount'], 'currency' => $proforma->currency]);
            }

            return $proforma->refresh();
        });
    }

    public function link(CrmProformaInvoice $proforma, User $user, bool $regenerate = false): CrmProformaInvoice
    {
        if (! $proforma->public_token || $regenerate) {
            do {
                $token = Str::random(48);
            } while (CrmProformaInvoice::where('public_token', $token)->exists());
            $proforma->update(['public_token' => $token, 'public_url' => route('proformas.public.show', $token), 'updated_by' => $user->id]);
        }

        return $proforma->refresh();
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function calc(array $items): array
    {
        $subtotal = $discountTotal = $taxTotal = 0;
        $normalized = [];
        foreach (array_values($items) as $index => $item) {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $gross = $quantity * $unitPrice;
            $discount = min((float) ($item['discount_amount'] ?? 0), $gross);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $tax = round(($gross - $discount) * $taxRate / 100, 2);
            $lineTotal = round($gross - $discount + $tax, 2);
            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $tax;
            $normalized[] = ['name' => $item['name'], 'description' => $item['description'] ?? null, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'discount_amount' => $discount, 'tax_rate' => $taxRate, 'tax_amount' => $tax, 'line_total' => $lineTotal, 'sort_order' => $index + 1];
        }

        return ['subtotal' => $subtotal, 'discount_total' => $discountTotal, 'tax_total' => $taxTotal, 'grand_total' => round($subtotal - $discountTotal + $taxTotal, 2), 'items' => $normalized];
    }

    private function number(int $companyId): string
    {
        $year = now()->format('Y');
        $last = CrmProformaInvoice::where('company_id', $companyId)->where('proforma_number', 'like', "RPI-{$year}-%")->lockForUpdate()->latest('id')->value('proforma_number');

        return "RPI-{$year}-".str_pad((string) ((int) substr((string) $last, -6) + 1), 6, '0', STR_PAD_LEFT);
    }

    private function activity(CrmProformaInvoice $proforma, User $user, string $subject): void
    {
        CrmActivity::create(['company_id' => $proforma->company_id, 'crm_lead_id' => $proforma->lead_id, 'created_by' => $user->id, 'assigned_user_id' => $proforma->lead?->assigned_user_id, 'type' => ActivityType::Note, 'subject' => $subject, 'description' => $subject, 'scheduled_at' => now(), 'completed_at' => now(), 'priority' => $proforma->lead?->priority ?? LeadPriority::Medium]);
    }

    /** @param array<string, mixed> $extra */
    private function dispatch(string $key, CrmProformaInvoice $proforma, User $user, array $extra = []): void
    {
        $this->domainEvents->dispatch(new ProformaEvent($key, $proforma->company_id, $user->id, CrmProformaInvoice::class, $proforma->id, array_merge([
            'proforma_id' => $proforma->id,
            'proforma_number' => $proforma->proforma_number,
            'lead_id' => $proforma->lead_id,
            'assigned_user_id' => $proforma->lead?->assigned_user_id,
        ], $extra)));
    }
}
