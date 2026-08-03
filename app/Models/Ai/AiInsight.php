<?php

namespace App\Models\Ai;

use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['company_id', 'outlet_id', 'insight_type', 'severity', 'entity_type', 'entity_id', 'title', 'explanation', 'recommended_action', 'evidence', 'status', 'reviewed_by', 'reviewed_at', 'expires_at'])]
class AiInsight extends Model
{
    protected function casts(): array { return ['evidence' => 'array', 'reviewed_at' => 'datetime', 'expires_at' => 'datetime']; }
    public function company() { return $this->belongsTo(Company::class); }
    public function outlet() { return $this->belongsTo(Branch::class, 'outlet_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
