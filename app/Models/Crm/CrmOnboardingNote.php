<?php

namespace App\Models\Crm;

use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['onboarding_id', 'note', 'visibility', 'created_by'])]
class CrmOnboardingNote extends Model
{
    use Auditable;

    public function onboarding(): BelongsTo { return $this->belongsTo(CrmCustomerOnboarding::class, 'onboarding_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
