<?php

namespace App\Services\Workforce;

use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Services\Reports\RetailReportingService;

class WorkforcePerformanceService
{
    public function __construct(private readonly RetailReportingService $reports) {}

    /** @return array{available: bool, period: string, sales_count: int, net_sales: int, discounts: int, average_order_value: ?int, notice: string} */
    public function forEmployee(User $viewer, WorkforceEmployee $employee): array
    {
        if (! $employee->user) {
            return $this->unavailable('No application account is linked to this employee.');
        }

        $overview = $this->reports->report($viewer, 'cashiers', [
            'date_from' => now()->subDays(29)->toDateString(),
            'date_to' => now()->toDateString(),
        ]);
        $row = collect($overview['detail']['rows'] ?? [])->firstWhere('cashier_id', $employee->user->id);
        if (! $row) {
            return $this->unavailable('No completed POS sales were recorded for this employee in the last 30 days.');
        }

        return [
            'available' => true,
            'period' => 'Last 30 days',
            'sales_count' => (int) $row['sales_count'],
            'net_sales' => (int) $row['net_sales'],
            'discounts' => (int) $row['discounts'],
            'average_order_value' => $row['average_order_value'] === null ? null : (int) $row['average_order_value'],
            'notice' => 'Completed POS sales only. This operational context does not measure overall employee quality.',
        ];
    }

    /** @return array{available: bool, period: string, sales_count: int, net_sales: int, discounts: int, average_order_value: ?int, notice: string} */
    private function unavailable(string $notice): array
    {
        return ['available' => false, 'period' => 'Last 30 days', 'sales_count' => 0, 'net_sales' => 0, 'discounts' => 0, 'average_order_value' => null, 'notice' => $notice];
    }
}
