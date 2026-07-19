<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Crm\PublicInvoiceLink;
use Illuminate\Support\Facades\DB;

class PublicInvoiceService
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function issue(CrmInvoice $invoice, User $user, bool $regenerate = false): PublicInvoiceLink
    {
        return DB::transaction(function () use ($invoice, $user, $regenerate): PublicInvoiceLink {
            do { $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token); } while (CrmInvoice::query()->where('public_token_hash', $hash)->exists());
            $invoice->update(['public_token_hash' => $hash, 'public_token_expires_at' => $invoice->due_date?->copy()->endOfDay(), 'public_token_revoked_at' => null, 'updated_by' => $user->id]);
            $this->audit->record('crm.invoice.public_link_'.($regenerate ? 'regenerated' : 'generated'), $invoice, 'Secure invoice link issued', ['company_id' => $invoice->company_id]);
            return new PublicInvoiceLink($invoice->refresh(), route('invoices.public.show', $token));
        });
    }
    public function find(string $token): CrmInvoice
    {
        $invoice = CrmInvoice::query()->where('public_token_hash', hash('sha256', $token))->whereNull('public_token_revoked_at')->with(['items', 'payments'])->firstOrFail();
        abort_if($invoice->public_token_expires_at?->isPast(), 404);
        return $invoice;
    }

    public function revoke(CrmInvoice $invoice, User $user): CrmInvoice
    {
        $invoice->update([
            'public_token_revoked_at' => now(),
            'updated_by' => $user->id,
        ]);

        $this->audit->record('crm.invoice.public_link_revoked', $invoice, 'Secure invoice link revoked', [
            'company_id' => $invoice->company_id,
        ]);

        return $invoice->refresh();
    }
    public function view(CrmInvoice $invoice): CrmInvoice
    {
        $invoice->update(['first_viewed_at' => $invoice->first_viewed_at ?? now(), 'last_viewed_at' => now(), 'public_view_count' => $invoice->public_view_count + 1, 'status' => $invoice->status === InvoiceStatus::Sent ? InvoiceStatus::Viewed : $invoice->status]);
        $this->audit->record('crm.invoice.viewed', $invoice, 'Invoice viewed through public link', ['company_id' => $invoice->company_id]);
        return $invoice->refresh()->load(['items', 'payments']);
    }
}
