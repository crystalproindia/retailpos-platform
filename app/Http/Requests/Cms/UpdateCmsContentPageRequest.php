<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCmsContentPageRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.content.update'); }
    public function rules(): array { $id = (int) $this->route('page'); return ['page_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/', Rule::unique('cms_content_pages', 'page_key')->ignore($id)->where('company_id', $this->user()?->company_id)], 'route_path' => ['nullable', 'string', 'max:500', 'regex:/^\/[^\s]*$/', Rule::unique('cms_content_pages', 'route_path')->ignore($id)->where('company_id', $this->user()?->company_id)], 'page_type' => ['required', Rule::in(config('cms-content.page_types'))], 'title' => ['required', 'string', 'max:255']]; }
}
