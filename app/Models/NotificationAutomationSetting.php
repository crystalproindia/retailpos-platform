<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'company_id', 'low_stock_enabled', 'out_of_stock_enabled', 'reorder_enabled',
    'payment_reminders_enabled', 'payment_before_due_days', 'payment_overdue_days',
    'customer_payment_emails_enabled', 'quotation_expiry_enabled', 'proforma_expiry_enabled',
    'document_expiry_notice_days', 'purchase_reminders_enabled', 'internal_email_enabled',
    'daily_summary_enabled', 'weekly_summary_enabled', 'monthly_expense_summary_enabled', 'monthly_profit_and_loss_summary_enabled', 'summary_time', 'timezone', 'updated_by',
])]
class NotificationAutomationSetting extends Model
{
    protected function casts(): array
    {
        return [
            'low_stock_enabled' => 'boolean',
            'out_of_stock_enabled' => 'boolean',
            'reorder_enabled' => 'boolean',
            'payment_reminders_enabled' => 'boolean',
            'payment_before_due_days' => 'array',
            'payment_overdue_days' => 'array',
            'customer_payment_emails_enabled' => 'boolean',
            'quotation_expiry_enabled' => 'boolean',
            'proforma_expiry_enabled' => 'boolean',
            'purchase_reminders_enabled' => 'boolean',
            'internal_email_enabled' => 'boolean',
            'daily_summary_enabled' => 'boolean',
            'weekly_summary_enabled' => 'boolean',
            'monthly_expense_summary_enabled' => 'boolean',
            'monthly_profit_and_loss_summary_enabled' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaults(int $companyId, ?string $timezone = null): self
    {
        return new self([
            'company_id' => $companyId,
            'payment_before_due_days' => [3],
            'payment_overdue_days' => [1, 7, 30],
            'timezone' => $timezone ?: config('app.timezone'),
        ]);
    }
}
