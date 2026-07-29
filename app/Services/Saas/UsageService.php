<?php

namespace App\Services\Saas;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\User;
use App\Enums\Crm\InvoiceStatus;
use App\Models\SaasUsageSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UsageService
{
    public function __construct(private readonly EntitlementService $entitlements)
    {
    }

    public function current(Company $company, string $key): int
    {
        return match ($key) {
            'users' => User::where('company_id', $company->id)->where('is_active', true)->count(),
            'branches' => Branch::where('company_id', $company->id)->where('is_active', true)->count(),
            'warehouses' => Warehouse::where('company_id', $company->id)->where('is_active', true)->count(),
            'products' => Product::where('company_id', $company->id)->count(),
            'monthly_invoices' => $this->finalisedInvoiceCount($company),
            'monthly_pos_transactions' => PosSale::where('company_id', $company->id)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            // Storage, API and outbound email meters are deliberately not guessed.
            default => 0,
        };
    }

    public function assertWithinLimit(Company $company, string $key): void
    {
        // Callers create tenant-owned resources inside a transaction. Locking the
        // tenant row serializes concurrent create attempts for the same company.
        if (DB::transactionLevel() > 0) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
        }

        $limit = $this->entitlements->limit($company, $key);

        if ($limit !== null && $this->current($company, $key) >= $limit) {
            throw ValidationException::withMessages([$key => 'Your subscription limit has been reached. Upgrade your package to continue.']);
        }
    }

    /** @return array<string, array{current: int, limit: ?int, percentage: ?int, state: string}> */
    public function summary(Company $company): array
    {
        return collect(config('saas.usage_limits', []))->mapWithKeys(function (string $limitKey, string $name) use ($company): array {
            $current = $this->current($company, $limitKey);
            $limit = $this->entitlements->limit($company, $limitKey);
            $percentage = $limit === null ? null : (int) min(999, round(($current / $limit) * 100));

            return [$name => [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $percentage,
                'state' => $percentage === null ? 'unlimited' : ($percentage >= 100 ? 'exceeded' : ($percentage >= 80 ? 'near_limit' : 'within_limit')),
            ]];
        })->all();
    }

    /** @return array<string, array{current: int, limit: ?int, percentage: ?int, state: string}> */
    public function recalculate(Company $company, bool $persist = true): array
    {
        $summary = $this->summary($company);
        if ($persist) {
            foreach ($summary as $key => $metric) {
                SaasUsageSnapshot::updateOrCreate(['company_id' => $company->id, 'usage_key' => $key], [
                    'current_value' => $metric['current'], 'limit_value' => $metric['limit'], 'state' => $metric['state'], 'calculated_at' => now(),
                ]);
            }
        }
        return $summary;
    }

    public function invoiceUsage(Company $company): array
    {
        $used = $this->finalisedInvoiceCount($company);
        $limit = $this->entitlements->limit($company, 'monthly_invoices');
        $now = now($company->timezone ?: config('app.timezone'));

        return [
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'reset_at' => $now->copy()->addMonthNoOverflow()->startOfMonth()->toIso8601String(),
            'package' => $this->entitlements->subscription($company)?->plan?->name,
        ];
    }

    private function finalisedInvoiceCount(Company $company): int
    {
        $timezone = $company->timezone ?: config('app.timezone');
        $now = now($timezone);
        $start = $now->copy()->startOfMonth()->utc();
        $end = $now->copy()->endOfMonth()->utc();

        $salesInvoices = CrmInvoice::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [
                InvoiceStatus::Issued->value,
                InvoiceStatus::Sent->value,
                InvoiceStatus::Viewed->value,
                InvoiceStatus::PartiallyPaid->value,
                InvoiceStatus::Paid->value,
                InvoiceStatus::Overdue->value,
            ])
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $posInvoices = PosSale::query()
            ->where('company_id', $company->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        return $salesInvoices + $posInvoices;
    }
}
