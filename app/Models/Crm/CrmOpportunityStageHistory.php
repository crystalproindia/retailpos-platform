<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['opportunity_id', 'from_stage', 'to_stage', 'note', 'changed_by', 'changed_at'])]
class CrmOpportunityStageHistory extends Model
{
    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo { return $this->belongsTo(CrmOpportunity::class, 'opportunity_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
