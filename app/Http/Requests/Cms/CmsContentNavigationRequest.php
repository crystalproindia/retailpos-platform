<?php

namespace App\Http\Requests\Cms;

use App\Rules\SafeCmsUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsContentNavigationRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('cms.navigation.manage'); }
    public function rules(): array { return ['label' => ['required', 'string', 'max:120'], 'url' => ['required', 'max:1000', new SafeCmsUrl], 'parent_id' => ['nullable', 'integer'], 'location' => ['required', Rule::in(array_keys(config('cms-content.navigation_locations')))], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'], 'is_enabled' => ['required', 'boolean'], 'opens_new_tab' => ['required', 'boolean']]; }
}
