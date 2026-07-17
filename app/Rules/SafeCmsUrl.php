<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeCmsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $url = trim((string) $value);
        $isRelative = str_starts_with($url, '/') && ! str_starts_with($url, '//');
        $isSecureExternal = filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://');

        if (! $isRelative && ! $isSecureExternal) {
            $fail('Use a website path beginning with / or a secure https:// URL.');
        }
    }
}
