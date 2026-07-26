<?php

namespace App\Services\Saas;

use App\Models\SaasIndustry;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class IndustryRegistry
{
    /** @return Collection<int, SaasIndustry> */
    public function enabled(): Collection
    {
        $this->ensureDefaults();
        return SaasIndustry::query()->where('is_enabled', true)->orderBy('sort_order')->orderBy('label')->get();
    }

    public function selectable(string $key): SaasIndustry
    {
        $this->ensureDefaults();
        $industry = SaasIndustry::query()->where('key', $key)->where('is_enabled', true)->first();
        if (! $industry) throw ValidationException::withMessages(['industry' => 'Select an enabled industry.']);
        return $industry;
    }

    private function ensureDefaults(): void
    {
        if (SaasIndustry::query()->exists()) return;

        foreach (config('saas.industries', []) as $industry) {
            SaasIndustry::query()->firstOrCreate(['key' => $industry['key']], $industry);
        }
    }
}
