<?php

namespace App\Models\Crm;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'automatic_enabled', 'minimum_cooldown_hours', 'updated_by'])]
class CrmInvoiceReminderSetting extends Model
{
    protected function casts(): array
    {
        return ['automatic_enabled' => 'boolean', 'minimum_cooldown_hours' => 'integer'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CrmInvoiceReminderRule::class, 'reminder_setting_id')->orderBy('sort_order');
    }
}
