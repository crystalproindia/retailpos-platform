<?php

namespace App\Services\Crm;

use App\Enums\Crm\CrmCustomerStatus;
use App\Models\Crm\CrmCustomer;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrmCustomerService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function quickCreate(User $user, array $data): CrmCustomer
    {
        return DB::transaction(function () use ($user, $data): CrmCustomer {
            $this->assertNotDuplicate($user, $data);

            $customer = CrmCustomer::create(Arr::only($data, [
                'business_type', 'email', 'phone', 'billing_address', 'tax_number', 'notes',
            ]) + [
                'company_id' => $user->company_id,
                'customer_code' => $this->nextCustomerCode($user->company_id),
                'company_name' => ($data['company_name'] ?? null) ?: $data['name'],
                'display_name' => $data['name'],
                'status' => CrmCustomerStatus::Active,
                'source' => 'Sales invoice',
                'converted_at' => now(),
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $customer->contacts()->create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_primary' => true,
            ]);

            $this->audit->record('crm.customer.quick_created_from_invoice', $customer, 'Customer created from sales invoice', [
                'company_id' => $customer->company_id,
            ]);

            return $customer->load('primaryContact');
        });
    }

    public function nextCustomerCode(int $companyId): string
    {
        $prefix = 'RPC-'.now()->format('Y').'-';
        $lastCode = CrmCustomer::query()
            ->where('company_id', $companyId)
            ->where('customer_code', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('customer_code');

        return $prefix.str_pad((string) (($lastCode ? (int) substr($lastCode, -6) : 0) + 1), 6, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $data */
    private function assertNotDuplicate(User $user, array $data): void
    {
        $email = isset($data['email']) ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = isset($data['phone']) ? trim((string) $data['phone']) : null;

        if (! $email && ! $phone) {
            return;
        }

        $duplicate = CrmCustomer::query()
            ->where('company_id', $user->company_id)
            ->where(function ($query) use ($email, $phone): void {
                $query->when($email, fn ($query) => $query->whereRaw('LOWER(email) = ?', [$email]));
                if ($phone) {
                    $query->when($email, fn ($query) => $query->orWhere('phone', $phone))
                        ->when(! $email, fn ($query) => $query->where('phone', $phone));
                }
            })
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'customer' => 'A customer with this email or phone already exists. Select the existing customer instead.',
            ]);
        }
    }
}
