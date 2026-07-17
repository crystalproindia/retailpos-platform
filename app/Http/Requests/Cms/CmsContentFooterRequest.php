<?php

namespace App\Http\Requests\Cms;

use App\Rules\SafeCmsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsContentFooterRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.footer.manage'); }
    public function rules(): array { return ['block_key' => ['required', Rule::in(config('cms-content.footer_blocks'))], 'title' => ['nullable', 'string', 'max:255'], 'content' => ['nullable', 'string', 'max:5000'], 'links' => ['nullable', 'array', 'max:20'], 'links.*.label' => ['nullable', 'string', 'max:120'], 'links.*.url' => ['nullable', 'max:1000', new SafeCmsUrl], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'], 'is_enabled' => ['required', 'boolean']]; }
}
