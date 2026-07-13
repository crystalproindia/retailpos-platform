<?php

namespace App\Services\Customers;

use App\Models\Customers\Customer;
use App\Models\Customers\CustomerActivityLog;
use App\Models\Customers\CustomerSetting;
use App\Models\User;
use App\Repositories\Customers\CustomerDashboardRepository;
use Illuminate\Support\Collection;

class BirthdayReminderService
{
    public function __construct(
        private readonly CustomerDashboardRepository $dashboard,
        private readonly CustomerEventService $events,
    ) {
    }

    /** @return Collection<int, Customer> */
    public function upcoming(int $companyId): Collection
    {
        $settings = CustomerSetting::firstOrCreate(['company_id' => $companyId]);

        return $this->dashboard->birthdays($companyId, $settings->birthday_reminder_days_before);
    }

    public function recordReminder(Customer $customer, User $user): void
    {
        CustomerActivityLog::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'activity_type' => 'reminder',
            'title' => 'Birthday reminder prepared',
            'user_id' => $user->id,
            'occurred_at' => now(),
        ]);

        $this->events->dispatch('customer.birthday.upcoming', $user, $customer, [
            'customer_name' => $customer->display_name,
        ]);
    }
}
