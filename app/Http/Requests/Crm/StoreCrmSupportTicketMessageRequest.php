<?php

namespace App\Http\Requests\Crm;

use App\Enums\Crm\SupportTicketMessageVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCrmSupportTicketMessageRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.support.reply'); }
    public function rules(): array { return ['message' => ['required', 'string', 'max:10000'], 'visibility' => ['required', Rule::enum(SupportTicketMessageVisibility::class)]]; }
}
