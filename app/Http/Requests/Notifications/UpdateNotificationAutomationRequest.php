<?php

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('automation.manage');
    }

    public function rules(): array
    {
        return [
            'low_stock_enabled' => ['nullable', 'boolean'],
            'out_of_stock_enabled' => ['nullable', 'boolean'],
            'reorder_enabled' => ['nullable', 'boolean'],
            'payment_reminders_enabled' => ['nullable', 'boolean'],
            'payment_before_due_days' => ['required', 'string', 'max:80', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
            'payment_overdue_days' => ['required', 'string', 'max:80', 'regex:/^\s*\d+(\s*,\s*\d+)*\s*$/'],
            'customer_payment_emails_enabled' => ['nullable', 'boolean'],
            'quotation_expiry_enabled' => ['nullable', 'boolean'],
            'proforma_expiry_enabled' => ['nullable', 'boolean'],
            'document_expiry_notice_days' => ['required', 'integer', 'min:1', 'max:30'],
            'purchase_reminders_enabled' => ['nullable', 'boolean'],
            'internal_email_enabled' => ['nullable', 'boolean'],
            'daily_summary_enabled' => ['nullable', 'boolean'],
            'weekly_summary_enabled' => ['nullable', 'boolean'],
            'monthly_expense_summary_enabled' => ['nullable', 'boolean'],
            'monthly_profit_and_loss_summary_enabled' => ['nullable', 'boolean'],
            'summary_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_before_due_days.regex' => 'Enter reminder days separated by commas, such as 3, 7.',
            'payment_overdue_days.regex' => 'Enter overdue stages separated by commas, such as 1, 7, 30.',
        ];
    }
}
