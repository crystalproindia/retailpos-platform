<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsBrandingRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return collect(config('cms.branding_settings'))->mapWithKeys(fn (array $definition, string $key) => [$key => match ($definition['type']) { 'media' => ['nullable', 'integer', 'exists:cms_media,id'], 'url' => ['nullable', 'url', 'max:255'], default => ['nullable', 'string', 'max:255'] }])->all(); }
}
