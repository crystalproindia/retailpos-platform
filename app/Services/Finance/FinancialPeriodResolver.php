<?php

namespace App\Services\Finance;

use App\Models\Company;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Resolves finance/reporting periods in the tenant's configured timezone. */
class FinancialPeriodResolver
{
    /** @param array<string, mixed> $filters
     *  @return array{from: CarbonImmutable, to: CarbonImmutable, timezone: string}
     */
    public function resolve(Company $company, array $filters = [], ?CarbonImmutable $now = null): array
    {
        $timezone = $company->timezone ?: config('app.timezone');
        $today = ($now ?: CarbonImmutable::now($timezone))->setTimezone($timezone);
        $period = $filters['period'] ?? 'custom';

        [$from, $to] = match ($period) {
            'today' => [$today->startOfDay(), $today->endOfDay()],
            'this_week' => [$today->startOfWeek(), $today->endOfWeek()],
            'this_month' => [$today->startOfMonth(), $today->endOfMonth()],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'financial_year' => $this->financialYear($company, $today),
            'custom' => $this->custom($filters, $timezone),
            default => throw ValidationException::withMessages(['period' => 'Select a valid reporting period.']),
        };

        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['date_to' => 'Select a date range of up to 366 days.']);
        }

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay(), 'timezone' => $timezone];
    }

    /** @param array<string, mixed> $filters
     *  @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function custom(array $filters, string $timezone): array
    {
        if (! filled($filters['date_from'] ?? null) || ! filled($filters['date_to'] ?? null)) {
            throw ValidationException::withMessages(['date_range' => 'A start date and end date are required for a custom range.']);
        }

        try {
            return [CarbonImmutable::parse((string) $filters['date_from'], $timezone), CarbonImmutable::parse((string) $filters['date_to'], $timezone)];
        } catch (\Throwable) {
            throw ValidationException::withMessages(['date_range' => 'Enter valid reporting dates.']);
        }
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function financialYear(Company $company, CarbonImmutable $today): array
    {
        $month = $this->financialYearStartMonth($company);
        $from = $today->month < $month
            ? $today->subYearNoOverflow()->setMonth($month)->startOfMonth()
            : $today->setMonth($month)->startOfMonth();

        return [$from, $from->addYear()->subDay()->endOfDay()];
    }

    private function financialYearStartMonth(Company $company): int
    {
        $value = Setting::query()
            ->where('company_id', $company->id)
            ->where('group', 'business')
            ->where('key', 'fiscal_year_start')
            ->value('value');
        $name = is_array($value) ? ($value['value'] ?? null) : null;
        try {
            $month = $name ? CarbonImmutable::parse('1 '.(string) $name.' 2000')->month : 4;
        } catch (\Throwable) {
            $month = 4;
        }

        return $month >= 1 && $month <= 12 ? $month : 4;
    }
}
