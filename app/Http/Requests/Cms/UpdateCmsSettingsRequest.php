<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(config('cms.settings'))
            ->mapWithKeys(function (array $definition, string $key): array {
                return [$key => match ($definition['type']) {
                    'email' => ['nullable', 'email', 'max:255'],
                    'url' => ['nullable', 'string', 'max:255'],
                    'media' => ['nullable', 'integer', 'exists:cms_media,id'],
                    'boolean' => ['nullable', 'boolean'],
                    default => ['nullable', 'string'],
                }];
            })
            ->all();
    }
}
