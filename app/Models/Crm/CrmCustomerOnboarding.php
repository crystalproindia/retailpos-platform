<?php

namespace App\Models\Crm;

use App\Enums\Crm\OnboardingPriority;
use App\Enums\Crm\OnboardingStatus;
use App\Models\Company;
use App\Models\AuditLog;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['company_id', 'customer_id', 'lead_id', 'quotation_id', 'proforma_invoice_id', 'onboarding_number', 'title', 'status', 'priority', 'assigned_to', 'implementation_owner_id', 'start_date', 'target_go_live_date', 'actual_go_live_date', 'progress_percent', 'customer_contact_name', 'customer_contact_phone', 'customer_contact_email', 'business_name', 'store_count', 'notes', 'internal_remarks', 'created_by', 'updated_by'])]
class CrmCustomerOnboarding extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return ['status' => OnboardingStatus::class, 'priority' => OnboardingPriority::class, 'start_date' => 'date', 'target_go_live_date' => 'date', 'actual_go_live_date' => 'date', 'progress_percent' => 'integer', 'store_count' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function customer(): BelongsTo { return $this->belongsTo(CrmCustomer::class, 'customer_id'); }
    public function lead(): BelongsTo { return $this->belongsTo(CrmLead::class, 'lead_id'); }
    public function quotation(): BelongsTo { return $this->belongsTo(CrmQuotation::class, 'quotation_id'); }
    public function proforma(): BelongsTo { return $this->belongsTo(CrmProformaInvoice::class, 'proforma_invoice_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function implementationOwner(): BelongsTo { return $this->belongsTo(User::class, 'implementation_owner_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function tasks(): HasMany { return $this->hasMany(CrmOnboardingTask::class, 'onboarding_id')->orderBy('sort_order'); }
    public function onboardingNotes(): HasMany { return $this->hasMany(CrmOnboardingNote::class, 'onboarding_id')->latest(); }
    public function documents(): HasMany { return $this->hasMany(CrmOnboardingDocument::class, 'onboarding_id')->latest(); }
    public function auditLogs(): MorphMany { return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at'); }
}
