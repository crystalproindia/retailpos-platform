<?php

namespace App\Models\Promotions;

use App\Enums\Promotions\CampaignType;
use App\Enums\Promotions\PromotionStatus;
use App\Models\Company;
use App\Models\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'slug', 'description', 'campaign_type', 'start_at', 'end_at', 'status', 'priority', 'is_active', 'created_by', 'approved_by', 'approved_at'])]
class PromotionCampaign extends Model
{
    use Auditable, SoftDeletes;

    protected function casts(): array
    {
        return ['campaign_type' => CampaignType::class, 'status' => PromotionStatus::class, 'start_at' => 'datetime', 'end_at' => 'datetime', 'approved_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function rules(): HasMany { return $this->hasMany(PromotionRule::class, 'campaign_id'); }
}
