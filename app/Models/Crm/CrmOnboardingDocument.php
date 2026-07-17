<?php

namespace App\Models\Crm;

use App\Enums\Crm\OnboardingDocumentStatus;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['onboarding_id', 'document_type', 'title', 'file_path', 'external_url', 'status', 'notes', 'uploaded_by'])]
class CrmOnboardingDocument extends Model
{
    use Auditable;

    protected function casts(): array { return ['status' => OnboardingDocumentStatus::class]; }
    public function onboarding(): BelongsTo { return $this->belongsTo(CrmCustomerOnboarding::class, 'onboarding_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
