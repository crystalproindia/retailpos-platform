<?php

namespace App\Http\Requests\Settings;

use App\Repositories\SettingsRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
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
        $section = $this->route('section');
        $fields = app(SettingsRepository::class)->sections()[$section]['fields'] ?? [];

        return collect($fields)
            ->mapWithKeys(fn (array $field, string $key) => [$key => $this->rulesForField($field)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, mixed>
     */
    private function rulesForField(array $field): array
    {
        return match ($field['type']) {
            'checkbox' => ['required', 'boolean'],
            'email' => ['nullable', 'email', 'max:255'],
            'number' => ['nullable', 'numeric'],
            'select' => ['required', Rule::in(array_keys($field['options'] ?? []))],
            default => ['nullable', 'string', 'max:1000'],
        };
    }
}
