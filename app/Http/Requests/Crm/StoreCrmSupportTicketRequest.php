<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\SupportTicketCategory;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmSupportTicketRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.support.create'); }
    public function rules(): array { return ['customer_id' => ['nullable', 'integer'], 'lead_id' => ['nullable', 'integer'], 'onboarding_id' => ['nullable', 'integer'], 'proforma_invoice_id' => ['nullable', 'integer'], 'subject' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:10000'], 'category' => ['required', Rule::enum(SupportTicketCategory::class)], 'priority' => ['required', Rule::enum(SupportTicketPriority::class)], 'source' => ['required', Rule::enum(SupportTicketSource::class)], 'assigned_to' => ['nullable', 'integer'], 'reported_by_name' => ['nullable', 'string', 'max:255'], 'reported_by_email' => ['nullable', 'email', 'max:255'], 'reported_by_phone' => ['nullable', 'string', 'max:80'], 'due_at' => ['nullable', 'date', 'after_or_equal:now'], 'internal_remarks' => ['nullable', 'string', 'max:5000']]; }
}
