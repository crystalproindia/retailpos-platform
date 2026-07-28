<?php

namespace App\Services\Outlets;

use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\UsageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutletService
{
    public function __construct(private readonly AuditLogger $audit, private readonly UsageService $usage, private readonly EntitlementService $entitlements) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Branch
    {
        return DB::transaction(function () use ($user, $data): Branch {
            $company = Company::query()->lockForUpdate()->findOrFail($user->company_id);
            $active = Branch::query()->where('company_id', $company->id)->where('is_active', true)->count();
            $limit = $this->entitlements->limit($company, 'branches');
            if ($limit !== null && $active >= $limit) {
                throw ValidationException::withMessages(['outlet' => 'Additional outlets require an eligible plan.']);
            }
            $this->usage->assertWithinLimit($company, 'branches');
            if (Branch::query()->where('company_id', $company->id)->where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'This outlet code is already in use.']);
            }
            $outlet = Branch::create(Arr::only($data, ['name', 'legal_name', 'code', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country', 'tax_number', 'invoice_prefix', 'receipt_prefix', 'timezone']) + [
                'company_id' => $company->id, 'country_code' => 'IN', 'currency' => $company->currency ?? 'INR', 'is_primary' => $active === 0, 'is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id,
            ]);
            $this->ensureWarehouse($outlet, $user);
            $this->assign($outlet, $user, $user, ['is_default' => true]);
            $this->audit->record('outlet.created', $outlet, 'Outlet created.', ['company_id' => $company->id, 'outlet_id' => $outlet->id]);

            return $outlet;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Branch $outlet, User $user, array $data): Branch
    {
        return DB::transaction(function () use ($outlet, $user, $data): Branch {
            $outlet = $this->lockedOutlet($outlet->id, $user);
            $outlet->update(Arr::only($data, ['name', 'legal_name', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country', 'tax_number', 'invoice_prefix', 'receipt_prefix', 'timezone']) + ['updated_by' => $user->id]);
            $this->audit->record('outlet.updated', $outlet, 'Outlet updated.', ['company_id' => $outlet->company_id, 'outlet_id' => $outlet->id]);
            return $outlet->refresh();
        });
    }

    public function makeDefault(Branch $outlet, User $user): Branch
    {
        return DB::transaction(function () use ($outlet, $user): Branch {
            $outlet = $this->lockedOutlet($outlet->id, $user);
            if (! $outlet->is_active) throw ValidationException::withMessages(['outlet' => 'Only an active outlet can become the default.']);
            Branch::query()->where('company_id', $user->company_id)->lockForUpdate()->update(['is_primary' => false]);
            $outlet->update(['is_primary' => true, 'updated_by' => $user->id]);
            $this->audit->record('outlet.default_changed', $outlet, 'Default outlet changed.', ['company_id' => $outlet->company_id, 'outlet_id' => $outlet->id]);
            return $outlet->refresh();
        });
    }

    public function archive(Branch $outlet, User $user): Branch
    {
        return DB::transaction(function () use ($outlet, $user): Branch {
            $outlet = $this->lockedOutlet($outlet->id, $user);
            if ($outlet->is_primary) throw ValidationException::withMessages(['outlet' => 'Choose another active default outlet before archiving this outlet.']);
            if (Branch::query()->where('company_id', $user->company_id)->where('is_active', true)->lockForUpdate()->count() <= 1) throw ValidationException::withMessages(['outlet' => 'A company must retain one active outlet.']);
            $outlet->update(['is_active' => false, 'archived_at' => now(), 'updated_by' => $user->id]);
            $this->audit->record('outlet.archived', $outlet, 'Outlet archived.', ['company_id' => $outlet->company_id, 'outlet_id' => $outlet->id]);
            return $outlet->refresh();
        });
    }

    public function restore(Branch $outlet, User $user): Branch
    {
        $outlet = $this->lockedOutlet($outlet->id, $user);
        $outlet->update(['is_active' => true, 'archived_at' => null, 'updated_by' => $user->id]);
        $this->audit->record('outlet.activated', $outlet, 'Outlet restored.', ['company_id' => $outlet->company_id, 'outlet_id' => $outlet->id]);
        return $outlet->refresh();
    }

    /** @param array<string, mixed> $data */
    public function assign(Branch $outlet, User $target, User $actor, array $data): BranchUserAssignment
    {
        return DB::transaction(function () use ($outlet, $target, $actor, $data): BranchUserAssignment {
            $outlet = $this->lockedOutlet($outlet->id, $actor);
            if ($target->company_id !== $actor->company_id) throw ValidationException::withMessages(['user_id' => 'Users can only be assigned within the same company.']);
            $assignment = BranchUserAssignment::updateOrCreate(['branch_id' => $outlet->id, 'user_id' => $target->id], ['company_id' => $actor->company_id, 'is_active' => true, 'is_default' => (bool) ($data['is_default'] ?? false), 'assigned_by' => $actor->id]);
            if ($assignment->is_default) {
                BranchUserAssignment::query()->where('company_id', $actor->company_id)->where('user_id', $target->id)->where('id', '!=', $assignment->id)->update(['is_default' => false]);
                $target->update(['branch_id' => $outlet->id]);
            }
            $this->audit->record('outlet.user_assigned', $assignment, 'User assigned to outlet.', ['company_id' => $actor->company_id, 'outlet_id' => $outlet->id, 'assigned_user_id' => $target->id]);
            return $assignment;
        });
    }

    private function lockedOutlet(int $outletId, User $user): Branch
    {
        return Branch::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($outletId);
    }

    private function ensureWarehouse(Branch $outlet, User $user): void
    {
        Warehouse::query()->firstOrCreate(['company_id' => $outlet->company_id, 'branch_id' => $outlet->id], ['name' => $outlet->name.' Store', 'code' => 'OUT-'.$outlet->code, 'type' => 'store', 'address_line_1' => $outlet->address, 'city' => $outlet->city, 'state' => $outlet->state, 'country' => $outlet->country ?: 'India', 'postal_code' => $outlet->postal_code, 'phone' => $outlet->phone, 'email' => $outlet->email, 'is_primary' => true, 'is_active' => true]);
    }
}
