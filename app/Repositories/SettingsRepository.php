<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

class SettingsRepository
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function sections(): array
    {
        return config('command-center.settings_sections', []);
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesFor(User $user, string $section): array
    {
        $storedValues = Setting::query()
            ->where('company_id', $user->company_id)
            ->where('group', $section)
            ->get()
            ->mapWithKeys(fn (Setting $setting) => [$setting->key => $setting->value['value'] ?? null])
            ->all();

        return collect($this->sections()[$section]['fields'] ?? [])
            ->mapWithKeys(fn (array $field, string $key): array => [$key => $storedValues[$key] ?? ($field['default'] ?? null)])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return Collection<int, Setting>
     */
    public function updateSection(User $user, string $section, array $values): Collection
    {
        return collect($values)->map(function (mixed $value, string $key) use ($user, $section): Setting {
            return Setting::updateOrCreate(
                [
                    'company_id' => $user->company_id,
                    'group' => $section,
                    'key' => $key,
                ],
                [
                    'value' => ['value' => $value],
                ],
            );
        })->values();
    }

    public function exists(string $section): bool
    {
        return array_key_exists($section, $this->sections());
    }
}
