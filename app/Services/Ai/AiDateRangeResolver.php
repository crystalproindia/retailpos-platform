<?php

namespace App\Services\Ai;

use App\Models\User;
use Carbon\CarbonImmutable;

class AiDateRangeResolver
{
    /** @return array{label:string,date_from:string,date_to:string} */
    public function resolve(User $user, string $question): array
    {
        $timezone = $user->company?->timezone ?: config('app.timezone');
        $today = CarbonImmutable::today($timezone);
        $normalized = str($question)->lower()->toString();

        [$label, $from, $to] = match (true) {
            str_contains($normalized, 'yesterday') => ['Yesterday', $today->subDay(), $today->subDay()],
            str_contains($normalized, 'last week') => ['Last week', $today->subWeek()->startOfWeek(), $today->subWeek()->endOfWeek()],
            str_contains($normalized, 'this week') => ['This week', $today->startOfWeek(), $today],
            str_contains($normalized, 'last month') => ['Last month', $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            str_contains($normalized, 'last 30 days') => ['Last 30 days', $today->subDays(29), $today],
            str_contains($normalized, 'today') => ['Today', $today, $today],
            default => ['This month', $today->startOfMonth(), $today],
        };

        return ['label' => $label, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()];
    }
}
