<?php

namespace App\Services\Saas;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSale;
use App\Models\User;
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
            'monthly_invoices' => CrmInvoice::where('company_id', $company->id)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'monthly_pos_transactions' => PosSale::where('company_id', $company->id)->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            // Storage, API and outbound email meters are deliberately not guessed.
            default => 0,
        };
    }

    public function assertWithinLimit(Company $company, string $key): void
    {
        $limit = $this->entitlements->limit($company, $key);

        if ($limit !== null && $this->current($company, $key) >= $limit) {
            throw ValidationException::withMessages([$key => 'Your subscription limit has been reached.']);
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
}
