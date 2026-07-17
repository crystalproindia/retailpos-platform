<?php

namespace App\Models\Crm;

use App\Enums\Crm\SupportTicketCategory;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketSource;
use App\Enums\Crm\SupportTicketStatus;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'customer_id', 'customer_portal_user_id', 'lead_id', 'onboarding_id', 'proforma_invoice_id', 'ticket_number', 'subject', 'description', 'category', 'priority', 'status', 'source', 'assigned_to', 'reported_by_name', 'reported_by_email', 'reported_by_phone', 'due_at', 'first_response_due_at', 'resolved_at', 'closed_at', 'reopened_at', 'resolution_summary', 'internal_remarks', 'created_by', 'updated_by'])]
class CrmSupportTicket extends Model
{
    use Auditable;

    protected function casts(): array { return ['category' => SupportTicketCategory::class, 'priority' => SupportTicketPriority::class, 'status' => SupportTicketStatus::class, 'source' => SupportTicketSource::class, 'due_at' => 'datetime', 'first_response_due_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime', 'reopened_at' => 'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo { return $this->belongsTo(CrmCustomer::class, 'customer_id'); }
    public function portalUser(): BelongsTo { return $this->belongsTo(CrmCustomerPortalUser::class, 'customer_portal_user_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(CrmLead::class, 'lead_id'); }
    public function onboarding(): BelongsTo { return $this->belongsTo(CrmCustomerOnboarding::class, 'onboarding_id'); }
    public function proforma(): BelongsTo { return $this->belongsTo(CrmProformaInvoice::class, 'proforma_invoice_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function messages(): HasMany { return $this->hasMany(CrmSupportTicketMessage::class, 'ticket_id')->oldest('created_at'); }
    public function attachments(): HasMany { return $this->hasMany(CrmSupportTicketAttachment::class, 'ticket_id')->latest('created_at'); }
    public function statusHistories(): HasMany { return $this->hasMany(CrmSupportTicketStatusHistory::class, 'ticket_id')->latest('created_at'); }
}
