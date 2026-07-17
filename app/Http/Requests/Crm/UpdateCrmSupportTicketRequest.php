<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\SupportTicketCategory;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketSource;
use App\Enums\Crm\SupportTicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCrmSupportTicketRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.support.update'); }
    public function rules(): array { return ['subject' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'required', 'string', 'max:10000'], 'category' => ['sometimes', Rule::enum(SupportTicketCategory::class)], 'priority' => ['sometimes', Rule::enum(SupportTicketPriority::class)], 'source' => ['sometimes', Rule::enum(SupportTicketSource::class)], 'status' => ['nullable', Rule::enum(SupportTicketStatus::class)], 'status_note' => ['nullable', 'string', 'max:2000'], 'assigned_to' => ['nullable', 'integer'], 'reported_by_name' => ['nullable', 'string', 'max:255'], 'reported_by_email' => ['nullable', 'email', 'max:255'], 'reported_by_phone' => ['nullable', 'string', 'max:80'], 'due_at' => ['nullable', 'date'], 'resolution_summary' => ['nullable', 'string', 'max:10000'], 'internal_remarks' => ['nullable', 'string', 'max:5000']]; }
}
