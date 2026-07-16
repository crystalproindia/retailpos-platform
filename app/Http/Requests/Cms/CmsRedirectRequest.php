<?php

namespace App\Http\Requests\Cms;

use App\Models\Cms\CmsRedirect;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsRedirectRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.redirects.manage'); }

    public function rules(): array
    {
        $redirect = $this->route('redirect');
        $redirectId = $redirect instanceof CmsRedirect ? $redirect->id : $redirect;
        return [
            'source_url' => ['required', 'string', 'max:255', 'regex:/^\//', Rule::unique('cms_redirects', 'source_url')->where('company_id', $this->user()->company_id)->ignore($redirectId)],
            'target_url' => ['required', 'string', 'max:500', 'different:source_url'],
            'status_code' => ['required', Rule::in([301, 302])],
            'is_enabled' => ['required', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
