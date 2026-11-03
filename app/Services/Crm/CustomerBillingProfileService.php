<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerBillingProfile;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\AuditLogger;
use App\Services\Compliance\GstinValidator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerBillingProfileService
{
    public function __construct(private readonly AuditLogger $audit, private readonly GstinValidator $gstin, private readonly CrmCustomerRepository $customers) {}

    /** @param array<string, mixed> $data */
    public function create(CrmCustomer $customer, User $user, array $data): CrmCustomerBillingProfile
    {
        return DB::transaction(function () use ($customer, $user, $data): CrmCustomerBillingProfile {
            $customer = $this->lockCustomer($customer);
            $this->assertCustomer($customer, $user);
            $this->assertTaxNumber($data['tax_number'] ?? null);
            $status = $data['status'] ?? 'active';
            $primary = $status === 'active' && ((bool) ($data['is_primary'] ?? false) || ! $customer->billingProfiles()->where('status', 'active')->exists());
            if ($primary) {
                $customer->billingProfiles()->update(['is_primary' => false]);
            }
            $profile = $customer->billingProfiles()->create(Arr::only($data, ['profile_name', 'legal_name', 'trade_name', 'tax_number', 'billing_address', 'city', 'state', 'postal_code', 'country', 'email', 'phone', 'status']) + [
                'company_id' => $user->company_id,
                'is_primary' => $primary,
                'status' => $status,
            ]);
            $this->audit->record('crm.customer.billing_profile_created', $profile, 'Customer billing profile created', ['company_id' => $user->company_id, 'customer_id' => $customer->id]);

            return $profile;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CrmCustomer $customer, CrmCustomerBillingProfile $profile, User $user, array $data): CrmCustomerBillingProfile
    {
        return DB::transaction(function () use ($customer, $profile, $user, $data): CrmCustomerBillingProfile {
            $customer = $this->lockCustomer($customer);
            $this->assertProfile($customer, $profile, $user);
            $this->assertTaxNumber($data['tax_number'] ?? null);
            $status = $data['status'] ?? $profile->status;
            $primary = $status === 'active' && (array_key_exists('is_primary', $data) ? (bool) $data['is_primary'] : $profile->is_primary);
            if ($primary) {
                $customer->billingProfiles()->whereKeyNot($profile->id)->update(['is_primary' => false]);
            }
            $profile->update(Arr::only($data, ['profile_name', 'legal_name', 'trade_name', 'tax_number', 'billing_address', 'city', 'state', 'postal_code', 'country', 'email', 'phone']) + ['status' => $status, 'is_primary' => $primary]);
            $this->ensurePrimary($customer);
            $this->audit->record('crm.customer.billing_profile_updated', $profile, 'Customer billing profile updated', ['company_id' => $user->company_id, 'customer_id' => $customer->id]);

            return $profile->refresh();
        });
    }

    public function setPrimary(CrmCustomer $customer, CrmCustomerBillingProfile $profile, User $user): void
    {
        DB::transaction(function () use ($customer, $profile, $user): void {
            $customer = $this->lockCustomer($customer);
            $this->assertProfile($customer, $profile, $user);
            if (! $profile->isActive()) {
                throw ValidationException::withMessages(['billing_profile' => 'Only an active billing profile can be primary.']);
            }
            $customer->billingProfiles()->update(['is_primary' => false]);
            $profile->update(['is_primary' => true]);
        });
    }

    public function disable(CrmCustomer $customer, CrmCustomerBillingProfile $profile, User $user): void
    {
        DB::transaction(function () use ($customer, $profile, $user): void {
            $customer = $this->lockCustomer($customer);
            $this->assertProfile($customer, $profile, $user);
            $profile->update(['status' => 'inactive', 'is_primary' => false]);
            if (! $customer->billingProfiles()->where('status', 'active')->where('is_primary', true)->exists()) {
                $customer->billingProfiles()->where('status', 'active')->oldest('id')->first()?->update(['is_primary' => true]);
            }
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function applyToInvoice(array $data, User $user): array
    {
        if (empty($data['billing_profile_id'])) {
            return $data;
        }
        $profile = CrmCustomerBillingProfile::query()->where('company_id', $user->company_id)->find($data['billing_profile_id']);
        if (! $profile || ! $profile->isActive() || (isset($data['customer_id']) && (int) $data['customer_id'] !== $profile->customer_id)) {
            throw ValidationException::withMessages(['billing_profile_id' => 'Select an active billing profile belonging to the selected customer.']);
        }
        $this->customers->findForUser($user, $profile->customer_id);

        return array_merge($data, ['customer_id' => $profile->customer_id, 'billing_profile_id' => $profile->id], $profile->invoiceSnapshot());
    }

    private function assertCustomer(CrmCustomer $customer, User $user): void
    {
        abort_unless($customer->company_id === $user->company_id, 404);
    }

    private function assertProfile(CrmCustomer $customer, CrmCustomerBillingProfile $profile, User $user): void
    {
        $this->assertCustomer($customer, $user);
        abort_unless($profile->company_id === $user->company_id && $profile->customer_id === $customer->id, 404);
    }

    private function assertTaxNumber(?string $taxNumber): void
    {
        if (! $this->gstin->isStructurallyValid($taxNumber)) {
            throw ValidationException::withMessages(['tax_number' => 'GSTIN format is invalid.']);
        }
    }

    private function ensurePrimary(CrmCustomer $customer): void
    {
        if (! $customer->billingProfiles()->where('status', 'active')->where('is_primary', true)->exists()) {
            $customer->billingProfiles()->where('status', 'active')->oldest('id')->first()?->update(['is_primary' => true]);
        }
    }

    private function lockCustomer(CrmCustomer $customer): CrmCustomer
    {
        return CrmCustomer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
    }
}
