<?php

namespace App\Services\Pos;

use App\Models\Customers\Customer;
use App\Models\User;
use App\Services\Customers\CustomerService;
use Illuminate\Support\Collection;

class PosCustomerLookupService
{
    public function __construct(private readonly CustomerService $customers) {}

    public function findByMobile(int $companyId, string $mobile): ?Customer
    {
        $digits = preg_replace('/\D+/', '', $mobile);

        return Customer::query()
            ->with(['groups.group', 'loyaltyAccount', 'insight'])
            ->where('company_id', $companyId)
            ->where(function ($query) use ($mobile, $digits): void {
                $query->where('phone', $mobile)->orWhere('whatsapp', $mobile);
                if ($digits !== '' && $digits !== $mobile) {
                    $query->orWhere('phone', 'like', "%{$digits}%")->orWhere('whatsapp', 'like', "%{$digits}%");
                }
            })
            ->first();
    }

    /** @return Collection<int, Customer> */
    public function search(int $companyId, string $term): Collection
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term);

        return Customer::query()
            ->with(['groups.group', 'loyaltyAccount', 'insight'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where(function ($query) use ($term, $digits): void {
                $query->where('display_name', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('whatsapp', 'like', "%{$term}%");
                if ($digits !== '' && $digits !== $term) {
                    $query->orWhere('phone', 'like', "%{$digits}%")
                        ->orWhere('whatsapp', 'like', "%{$digits}%");
                }
            })
            ->orderByDesc('last_purchase_at')
            ->orderBy('display_name')
            ->limit(8)
            ->get();
    }

    /** @return Collection<int, Customer> */
    public function recent(int $companyId): Collection
    {
        return Customer::query()
            ->with(['groups.group', 'loyaltyAccount', 'insight'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereNotNull('last_purchase_at')
            ->latest('last_purchase_at')
            ->limit(5)
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function quickCreate(User $user, array $data): Customer
    {
        $mobile = (string) $data['mobile'];
        $name = trim((string) ($data['name'] ?? 'Walk-in customer'));
        $parts = preg_split('/\s+/', $name) ?: ['Walk-in'];
        $first = array_shift($parts);

        return $this->customers->create($user, [
            'first_name' => $first ?: 'Walk-in',
            'last_name' => implode(' ', $parts) ?: null,
            'phone' => $mobile,
            'whatsapp' => $mobile,
            'customer_type' => 'walk_in',
            'status' => 'active',
            'source' => 'POS quick customer capture',
        ])->load(['groups.group', 'loyaltyAccount', 'insight']);
    }
}
