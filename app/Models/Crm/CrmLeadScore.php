<?php

namespace App\Models\Crm;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadScoreCategory;
use App\Enums\Crm\LeadScoreConfidence;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'lead_id', 'score', 'category', 'confidence', 'priority', 'next_best_action', 'reasons', 'risks', 'opportunities', 'metadata', 'analyzed_at', 'created_by'])]
class CrmLeadScore extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'category' => LeadScoreCategory::class,
            'confidence' => LeadScoreConfidence::class,
            'priority' => LeadPriority::class,
            'reasons' => 'array',
            'risks' => 'array',
            'opportunities' => 'array',
            'metadata' => 'array',
            'analyzed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
