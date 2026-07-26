<?php

namespace App\Models\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'reminder_setting_id', 'stage', 'enabled', 'offset_days', 'attach_pdf', 'include_secure_link', 'subject', 'intro_message', 'sort_order'])]
class CrmInvoiceReminderRule extends Model
{
    protected function casts(): array
    {
        return [
            'stage' => InvoiceReminderStage::class,
            'enabled' => 'boolean',
            'attach_pdf' => 'boolean',
            'include_secure_link' => 'boolean',
            'offset_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setting(): BelongsTo
    {
        return $this->belongsTo(CrmInvoiceReminderSetting::class, 'reminder_setting_id');
    }
}
