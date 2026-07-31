<?php

namespace App\Services\Workforce;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosRegister;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Models\WorkforceEmployeeOutletAssignment;
use App\Models\WorkforceEmployeeRegisterAssignment;
use App\Models\WorkforceEmployeeWarehouseAssignment;
use App\Models\WorkforceInvitation;
use App\Models\WorkforceRole;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkforceService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EmailDeliveryService $email,
    ) {}

    /** @param array<string, mixed> $data */
    public function createEmployee(User $actor, array $data): WorkforceEmployee
    {
        return DB::transaction(function () use ($actor, $data): WorkforceEmployee {
            $this->assertBranch($actor, $data['primary_branch_id'] ?? null);
            $this->assertManager($actor, $data['reporting_manager_id'] ?? null);

            $employee = WorkforceEmployee::create(
                Arr::only($data, [
                    'primary_branch_id', 'reporting_manager_id', 'employee_number', 'first_name',
                    'last_name', 'display_name', 'work_email', 'work_mobile', 'job_title',
                    'department', 'joining_date', 'status', 'manager_notes',
                ]) + ['company_id' => $actor->company_id],
            );

            $this->syncAssignments($actor, $employee, $data);
            $this->audit->record('workforce.employee.created', $employee, 'Employee created');

            return $employee->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateEmployee(User $actor, WorkforceEmployee $employee, array $data): WorkforceEmployee
    {
        return DB::transaction(function () use ($actor, $employee, $data): WorkforceEmployee {
            $this->assertEmployee($actor, $employee);
            $this->assertBranch($actor, $data['primary_branch_id'] ?? null);
            $this->assertManager($actor, $data['reporting_manager_id'] ?? null, $employee->id);

            $employee->update(Arr::only($data, [
                'primary_branch_id', 'reporting_manager_id', 'employee_number', 'first_name',
                'last_name', 'display_name', 'work_email', 'work_mobile', 'job_title',
                'department', 'joining_date', 'status', 'manager_notes',
            ]));
            $this->syncAssignments($actor, $employee, $data);
            $this->audit->record('workforce.employee.updated', $employee, 'Employee updated');

            return $employee->refresh();
        });
    }

    public function archiveEmployee(User $actor, WorkforceEmployee $employee): void
    {
        DB::transaction(function () use ($actor, $employee): void {
            $this->assertEmployee($actor, $employee);
            $employee->update(['status' => 'archived']);
            if ($employee->user) {
                $this->changeAccountState($actor, $employee->user, 'disabled');
            }
            $this->audit->record('workforce.employee.archived', $employee, 'Employee archived');
        });
    }

    /** @param array<string, mixed> $data */
    public function createUser(User $actor, WorkforceEmployee $employee, array $data): User
    {
        return DB::transaction(function () use ($actor, $employee, $data): User {
            $this->assertEmployee($actor, $employee);
            $this->assertBranch($actor, $data['branch_id'] ?? $employee->primary_branch_id);
            if ($employee->user()->exists()) {
                throw ValidationException::withMessages(['employee_id' => 'This employee already has a linked account.']);
            }

            $role = UserRole::from($data['role']);
            $workforceRole = $this->roleFor($actor, $data['workforce_role_id'] ?? null, $role);
            $user = User::create([
                'company_id' => $actor->company_id,
                'branch_id' => $data['branch_id'] ?? $employee->primary_branch_id,
                'workforce_employee_id' => $employee->id,
                'workforce_role_id' => $workforceRole?->id,
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'mobile' => $data['mobile'] ?? null,
                'role' => $role,
                'account_status' => 'active',
                'is_active' => true,
                'password' => Hash::make($data['password']),
            ]);

            $this->syncUserOutlets($actor, $user, $employee);
            $this->audit->record('workforce.user.created', $user, 'Employee user account created');

            return $user;
        });
    }

    /** @param array<string, mixed> $data */
    public function invite(User $actor, WorkforceEmployee $employee, array $data): WorkforceInvitation
    {
        $token = Str::random(64);

        return DB::transaction(function () use ($actor, $employee, $data, $token): WorkforceInvitation {
            $this->assertEmployee($actor, $employee);
            $this->assertBranch($actor, $data['branch_id'] ?? $employee->primary_branch_id);
            $email = mb_strtolower((string) $data['email']);
            $existing = $employee->user()->first();

            if ($existing && $existing->account_status === 'active') {
                throw ValidationException::withMessages(['email' => 'This employee already has an active account.']);
            }
            if (! $existing && User::query()->where('email', $email)->exists()) {
                throw ValidationException::withMessages(['email' => 'This email cannot be used for a new account.']);
            }

            $role = UserRole::from($data['role']);
            $workforceRole = $this->roleFor($actor, $data['workforce_role_id'] ?? null, $role);
            $user = $existing ?: new User(['company_id' => $actor->company_id, 'workforce_employee_id' => $employee->id]);
            $user->fill([
                'branch_id' => $data['branch_id'] ?? $employee->primary_branch_id,
                'workforce_role_id' => $workforceRole?->id,
                'name' => $data['name'],
                'email' => $email,
                'mobile' => $data['mobile'] ?? null,
                'role' => $role,
                'account_status' => 'pending_invitation',
                'is_active' => false,
                'suspended_at' => null,
                // A generated hash leaves no usable password before acceptance.
                'password' => Str::password(64),
            ]);
            $user->save();
            $this->syncUserOutlets($actor, $user, $employee);

            WorkforceInvitation::query()
                ->where('employee_id', $employee->id)
                ->whereNull('accepted_at')
                ->whereNull('cancelled_at')
                ->update(['cancelled_at' => now()]);

            $invitation = WorkforceInvitation::create([
                'company_id' => $actor->company_id,
                'employee_id' => $employee->id,
                'user_id' => $user->id,
                'email' => $email,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addHours(72),
                'created_by' => $actor->id,
            ]);
            $employee->update(['status' => 'invited']);

            $this->queueInvitationEmail($actor, $employee, $invitation, $token);
            $this->audit->record('workforce.invitation.sent', $invitation, 'Workforce invitation sent');

            return $invitation;
        });
    }

    public function cancelInvitation(User $actor, WorkforceInvitation $invitation): void
    {
        $this->assertEmployee($actor, $invitation->employee);
        if (! $invitation->isUsable()) {
            throw ValidationException::withMessages(['invitation' => 'Only an active invitation can be cancelled.']);
        }
        $invitation->update(['cancelled_at' => now()]);
        $invitation->user?->update(['account_status' => 'disabled', 'is_active' => false]);
        $this->audit->record('workforce.invitation.cancelled', $invitation, 'Workforce invitation cancelled');
    }

    public function acceptInvitation(string $token, string $password): WorkforceInvitation
    {
        return DB::transaction(function () use ($token, $password): WorkforceInvitation {
            $invitation = WorkforceInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->firstOrFail();

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages(['invitation' => 'This activation link is no longer valid.']);
            }

            $user = $invitation->user;
            if (! $user || $user->company_id !== $invitation->company_id) {
                abort(404);
            }

            $user->update([
                'password' => $password,
                'account_status' => 'active',
                'is_active' => true,
                'suspended_at' => null,
            ]);
            $invitation->update(['accepted_at' => now()]);
            if (in_array($invitation->employee->status, ['draft', 'invited'], true)) {
                $invitation->employee->update(['status' => 'active']);
            }
            $this->audit->record('workforce.invitation.accepted', $invitation, 'Workforce invitation accepted', ['company_id' => $invitation->company_id]);

            return $invitation->refresh();
        });
    }

    public function changeAccountState(User $actor, User $target, string $state): void
    {
        DB::transaction(function () use ($actor, $target, $state): void {
            $this->assertUser($actor, $target);
            $this->preventLastAdministratorLockout($target, $state);
            $target->update([
                'account_status' => $state,
                'is_active' => $state === 'active',
                'suspended_at' => $state === 'suspended' ? now() : null,
                'remember_token' => $state === 'active' ? $target->remember_token : Str::random(60),
            ]);
            $this->audit->record('workforce.user.state_changed', $target, 'User account state changed', ['state' => $state]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createRole(User $actor, array $data): WorkforceRole
    {
        return DB::transaction(function () use ($actor, $data): WorkforceRole {
            $keys = array_values(array_unique($data['permissions'] ?? []));
            $known = array_keys(config('permissions.capabilities', []));
            if (array_diff($keys, $known)) {
                throw ValidationException::withMessages(['permissions' => 'One or more permissions are unavailable.']);
            }
            if (($data['base_role'] ?? '') === UserRole::Administrator->value) {
                throw ValidationException::withMessages(['base_role' => 'Custom roles cannot replace the protected Company Administrator role.']);
            }
            $role = WorkforceRole::create([
                'company_id' => $actor->company_id,
                'name' => $data['name'],
                'base_role' => $data['base_role'],
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);
            $role->permissions()->createMany(array_map(fn (string $key): array => ['permission_key' => $key], $keys));
            $this->audit->record('workforce.role.created', $role, 'Custom workforce role created');

            return $role;
        });
    }

    public function assignRole(User $actor, User $target, ?WorkforceRole $role): void
    {
        $this->assertUser($actor, $target);
        if ($target->id === $actor->id) {
            throw ValidationException::withMessages(['user' => 'You cannot change your own role assignment.']);
        }
        if ($role && ($role->company_id !== $actor->company_id || ! $role->is_active)) {
            throw ValidationException::withMessages(['role' => 'That role is not available.']);
        }
        $target->update(['workforce_role_id' => $role?->id, 'role' => $role?->base_role ?? $target->role]);
        $this->audit->record('workforce.role.assigned', $target, 'User role assignment changed', ['role_id' => $role?->id]);
    }

    /** @param array<string, mixed> $data */
    private function syncAssignments(User $actor, WorkforceEmployee $employee, array $data): void
    {
        $branchIds = array_values(array_unique(array_filter(array_map('intval', $data['outlet_ids'] ?? []))));
        if ($employee->primary_branch_id) {
            $branchIds[] = $employee->primary_branch_id;
        }
        $branchIds = array_values(array_unique($branchIds));
        $this->assertResources($actor, $branchIds, $data['warehouse_ids'] ?? [], $data['register_ids'] ?? []);

        WorkforceEmployeeOutletAssignment::query()->where('employee_id', $employee->id)->delete();
        foreach ($branchIds as $branchId) {
            WorkforceEmployeeOutletAssignment::create(['company_id' => $actor->company_id, 'employee_id' => $employee->id, 'branch_id' => $branchId, 'is_active' => true]);
        }

        WorkforceEmployeeWarehouseAssignment::query()->where('employee_id', $employee->id)->delete();
        foreach (array_unique(array_map('intval', $data['warehouse_ids'] ?? [])) as $warehouseId) {
            WorkforceEmployeeWarehouseAssignment::create(['company_id' => $actor->company_id, 'employee_id' => $employee->id, 'warehouse_id' => $warehouseId, 'is_active' => true]);
        }

        WorkforceEmployeeRegisterAssignment::query()->where('employee_id', $employee->id)->delete();
        foreach (array_unique(array_map('intval', $data['register_ids'] ?? [])) as $registerId) {
            WorkforceEmployeeRegisterAssignment::create(['company_id' => $actor->company_id, 'employee_id' => $employee->id, 'register_id' => $registerId, 'is_active' => true]);
        }
    }

    private function syncUserOutlets(User $actor, User $user, WorkforceEmployee $employee): void
    {
        $outletIds = $employee->outletAssignments()->where('is_active', true)->pluck('branch_id')->all();
        if ($outletIds === [] && $user->branch_id) {
            $outletIds = [$user->branch_id];
        }
        BranchUserAssignment::query()->where('company_id', $actor->company_id)->where('user_id', $user->id)->delete();
        foreach ($outletIds as $index => $branchId) {
            BranchUserAssignment::create([
                'company_id' => $actor->company_id,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'is_active' => true,
                'is_default' => $branchId === $user->branch_id || ($index === 0 && ! $user->branch_id),
                'assigned_by' => $actor->id,
            ]);
        }
    }

    private function queueInvitationEmail(User $actor, WorkforceEmployee $employee, WorkforceInvitation $invitation, string $token): void
    {
        $url = route('workforce.invitation.show', ['token' => $token]);
        $this->email->queue(
            companyId: $actor->company_id,
            recipient: $invitation->email,
            subject: 'Activate your RetailPOS account',
            templateKey: 'workforce_invitation',
            payload: [
                'heading' => 'Activate your RetailPOS account',
                'greeting' => 'Hello '.$employee->display_name.',',
                'message' => 'Use this secure link to set your password. It expires in 72 hours.',
                'details' => ['Employee code' => $employee->employee_number],
                'action_url' => $url,
                'action_label' => 'Set up account',
            ],
            related: $invitation,
            createdBy: $actor,
            idempotencyKey: 'workforce-invitation:'.$invitation->id,
            recipientName: $employee->display_name,
        );
    }

    private function roleFor(User $actor, mixed $roleId, UserRole $baseRole): ?WorkforceRole
    {
        if (! $roleId) {
            return null;
        }
        $role = WorkforceRole::query()->where('company_id', $actor->company_id)->where('is_active', true)->find($roleId);
        if (! $role || $role->base_role !== $baseRole->value) {
            throw ValidationException::withMessages(['workforce_role_id' => 'Choose an active custom role matching the account base role.']);
        }

        return $role;
    }

    /** @param array<int, mixed> $branchIds @param array<int, mixed> $warehouseIds @param array<int, mixed> $registerIds */
    private function assertResources(User $actor, array $branchIds, array $warehouseIds, array $registerIds): void
    {
        if (count($branchIds) !== Branch::query()->where('company_id', $actor->company_id)->where('is_active', true)->whereIn('id', $branchIds)->count()) {
            throw ValidationException::withMessages(['outlet_ids' => 'Choose active outlets in this company.']);
        }
        $warehouseIds = array_values(array_unique(array_filter(array_map('intval', $warehouseIds))));
        if ($warehouseIds !== [] && count($warehouseIds) !== Warehouse::query()->where('company_id', $actor->company_id)->where('is_active', true)->whereIn('id', $warehouseIds)->count()) {
            throw ValidationException::withMessages(['warehouse_ids' => 'Choose active warehouses in this company.']);
        }
        $registerIds = array_values(array_unique(array_filter(array_map('intval', $registerIds))));
        if ($registerIds !== [] && count($registerIds) !== PosRegister::query()->where('company_id', $actor->company_id)->where('is_active', true)->whereIn('id', $registerIds)->count()) {
            throw ValidationException::withMessages(['register_ids' => 'Choose active registers in this company.']);
        }
    }

    private function preventLastAdministratorLockout(User $target, string $state): void
    {
        $role = $target->role instanceof UserRole ? $target->role : UserRole::tryFrom((string) $target->role);
        if ($role !== UserRole::Administrator || $state === 'active') {
            return;
        }
        $remaining = User::query()
            ->where('company_id', $target->company_id)
            ->where('is_active', true)
            ->where('account_status', 'active')
            ->where('role', UserRole::Administrator->value)
            ->whereKeyNot($target->id)
            ->exists();
        if (! $remaining) {
            throw ValidationException::withMessages(['user' => 'This is the last active Company Administrator. Assign another administrator before changing this account.']);
        }
    }

    private function assertEmployee(User $actor, WorkforceEmployee $employee): void
    {
        if ($employee->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    private function assertUser(User $actor, User $user): void
    {
        if ($user->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    private function assertBranch(User $actor, mixed $branchId): void
    {
        if ($branchId && ! Branch::query()->where('company_id', $actor->company_id)->where('is_active', true)->whereKey($branchId)->exists()) {
            throw ValidationException::withMessages(['primary_branch_id' => 'Choose an active outlet in this company.']);
        }
    }

    private function assertManager(User $actor, mixed $managerId, ?int $employeeId = null): void
    {
        if ($managerId && ! WorkforceEmployee::query()->where('company_id', $actor->company_id)->whereKey($managerId)->when($employeeId, fn ($query) => $query->whereKeyNot($employeeId))->exists()) {
            throw ValidationException::withMessages(['reporting_manager_id' => 'Choose a valid reporting manager.']);
        }
    }
}
