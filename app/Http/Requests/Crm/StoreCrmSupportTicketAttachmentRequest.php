<?php

namespace App\Http\Requests\Crm;

use App\Rules\SafeCmsUrl;
use Illuminate\Foundation\Http\FormRequest;

class StoreCrmSupportTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('crm.support.update'); }
    public function rules(): array { return ['title' => ['required', 'string', 'max:255'], 'external_url' => ['required', 'max:1000', new SafeCmsUrl], 'message_id' => ['nullable', 'integer'], 'mime_type' => ['nullable', 'string', 'max:191'], 'file_size' => ['nullable', 'integer', 'min:0']]; }
}
