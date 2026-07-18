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
                    'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s().-]{6,28}$/'],
                    'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{7,30}$/'],
                    default => ['nullable', 'string'],
                }];
            })->merge([
                'clear_settings' => ['nullable', 'array'],
                'clear_settings.*' => ['string', 'in:'.implode(',', array_keys(config('cms.settings')))],
            ])
            ->all();
    }
}
