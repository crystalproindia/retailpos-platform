<?php

namespace App\Models\Crm;

use App\Enums\Crm\OnboardingTaskCategory;
use App\Enums\Crm\OnboardingTaskStatus;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['onboarding_id', 'task_key', 'title', 'description', 'category', 'status', 'assigned_to', 'due_date', 'completed_at', 'completed_by', 'sort_order', 'is_required', 'metadata'])]
class CrmOnboardingTask extends Model
{
    use Auditable;

    protected function casts(): array { return ['category' => OnboardingTaskCategory::class, 'status' => OnboardingTaskStatus::class, 'due_date' => 'date', 'completed_at' => 'datetime', 'is_required' => 'boolean', 'metadata' => 'array']; }
    public function onboarding(): BelongsTo { return $this->belongsTo(CrmCustomerOnboarding::class, 'onboarding_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class, 'completed_by'); }
}
