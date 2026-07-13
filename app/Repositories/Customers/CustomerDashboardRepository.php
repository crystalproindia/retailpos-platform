<?php

namespace App\Repositories\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerSetting;
use Illuminate\Support\Collection;

class CustomerDashboardRepository
{
    public function settings(int $companyId): CustomerSetting
    {
        return CustomerSetting::firstOrCreate(['company_id' => $companyId]);
    }

    /** @return Collection<int, Customer> */
    public function birthdays(int $companyId, int $days): Collection
    {
        $today = now()->startOfDay();

        return Customer::query()
            ->where('company_id', $companyId)
            ->whereNotNull('date_of_birth')
            ->get()
            ->filter(function (Customer $customer) use ($today, $days): bool {
                $birthday = $customer->date_of_birth->copy()->setYear($today->year);

                if ($birthday->lt($today)) {
                    $birthday->addYear();
                }

                return $today->diffInDays($birthday) <= $days;
            })
            ->values();
    }

    /** @return Collection<int, Customer> */
    public function flagged(int $companyId, string $flag): Collection
    {
        return Customer::query()
            ->with('insight')
            ->where('company_id', $companyId)
            ->whereHas('insight', fn ($query) => $query->where($flag, true))
            ->orderByDesc('total_purchase_amount')
            ->get();
    }
}
