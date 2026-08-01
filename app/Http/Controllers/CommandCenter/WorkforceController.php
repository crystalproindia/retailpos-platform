<?php

namespace App\Http\Controllers\CommandCenter;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosRegister;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Models\WorkforceInvitation;
use App\Models\WorkforceManagerReview;
use App\Models\WorkforceRecognition;
use App\Models\WorkforceRole;
use App\Repositories\Tasks\TaskRepository;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use App\Services\Workforce\WorkforcePerformanceService;
use App\Services\Workforce\WorkforceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class WorkforceController extends Controller
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    public function dashboard(Request $request, TaskRepository $tasks): View
    {
        $employees = $this->employeeQuery($request);
        $recent = (clone $employees)->latest()->limit(6)->get();
        $outletBreakdown = (clone $employees)
            ->selectRaw('primary_branch_id, count(*) as employees')
            ->with('primaryBranch:id,name')
            ->groupBy('primary_branch_id')
            ->orderByDesc('employees')
            ->limit(6)
            ->get();

        return view('command-center.workforce.dashboard', [
            'metrics' => [
                'total' => (clone $employees)->count(),
                'active' => (clone $employees)->where('status', 'active')->count(),
                'inactive' => (clone $employees)->whereIn('status', ['inactive', 'suspended', 'archived'])->count(),
                'without_login' => (clone $employees)->doesntHave('user')->count(),
                'pending_invitations' => WorkforceInvitation::query()
                    ->where('company_id', $request->user()->company_id)
                    ->whereNull('accepted_at')->whereNull('cancelled_at')->where('expires_at', '>', now())
                    ->whereIn('employee_id', (clone $employees)->select('id'))
                    ->count(),
            ],
            'recent' => $recent,
            'outletBreakdown' => $outletBreakdown,
            'taskMetrics' => $request->user()->can('tasks.view_team') ? $tasks->teamMetrics($request->user()) : null,
        ]);
    }

    public function employees(Request $request): View
    {
        $query = $this->employeeQuery($request)->with(['primaryBranch', 'user']);
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function (Builder $employees) use ($search): void {
                $employees->where('display_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('work_email', 'like', "%{$search}%")
                    ->orWhere('work_mobile', 'like', "%{$search}%");
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('outlet_id')) {
            $query->where('primary_branch_id', $request->integer('outlet_id'));
        }

        return view('command-center.workforce.employees.index', [
            'employees' => $query->latest()->paginate(20)->withQueryString(),
            'branches' => $this->branchesFor($request),
        ]);
    }

    public function createEmployee(Request $request): View
    {
        return view('command-center.workforce.employees.create', $this->employeeFormData($request));
    }

    public function storeEmployee(Request $request, WorkforceService $service): RedirectResponse
    {
        $employee = $service->createEmployee($request->user(), $this->validatedEmployee($request));

        return redirect()->route('workforce.employees.show', $employee)->with('status', 'Employee created without login access.');
    }

    public function showEmployee(Request $request, WorkforcePerformanceService $performance, int $employee): View
    {
        $employee = $this->employeeQuery($request)
            ->with([
                'primaryBranch', 'manager', 'user.workforceRole',
                'outletAssignments.branch', 'warehouseAssignments.warehouse', 'registerAssignments.register',
                'reviews.reviewer', 'recognitions.grantor', 'invitations',
            ])
            ->findOrFail($employee);

        return view('command-center.workforce.employees.show', [
            'employee' => $employee,
            'roles' => WorkforceRole::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
            'systemRoles' => UserRole::cases(),
            'metrics' => $performance->forEmployee($request->user(), $employee),
        ]);
    }

    public function editEmployee(Request $request, int $employee): View
    {
        $employee = $this->employeeQuery($request)->with(['outletAssignments', 'warehouseAssignments', 'registerAssignments'])->findOrFail($employee);

        return view('command-center.workforce.employees.edit', $this->employeeFormData($request) + ['employee' => $employee]);
    }

    public function updateEmployee(Request $request, WorkforceService $service, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);
        $service->updateEmployee($request->user(), $employee, $this->validatedEmployee($request, $employee));

        return redirect()->route('workforce.employees.show', $employee)->with('status', 'Employee profile and assignments updated.');
    }

    public function archiveEmployee(Request $request, WorkforceService $service, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->with('user')->findOrFail($employee);
        $service->archiveEmployee($request->user(), $employee);

        return redirect()->route('workforce.employees.index')->with('status', 'Employee archived. Historical records remain available.');
    }

    public function createUser(Request $request, int $employee): View
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);

        return view('command-center.workforce.users.create', [
            'employee' => $employee,
            'branches' => $this->branchesFor($request),
            'roles' => UserRole::cases(),
            'customRoles' => WorkforceRole::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request, WorkforceService $service, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);
        $data = $this->validatedAccount($request, true);
        $service->createUser($request->user(), $employee, $data);

        return redirect()->route('workforce.employees.show', $employee)->with('status', 'User account created. The password is stored only as a secure hash.');
    }

    public function invite(Request $request, WorkforceService $service, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);
        $service->invite($request->user(), $employee, $this->validatedAccount($request, false));

        return back()->with('status', 'A secure activation invitation was created. Its delivery is recorded by the existing email service.');
    }

    public function cancelInvitation(Request $request, WorkforceService $service, int $invitation): RedirectResponse
    {
        $invitation = WorkforceInvitation::query()->where('company_id', $request->user()->company_id)->with('employee')->findOrFail($invitation);
        $service->cancelInvitation($request->user(), $invitation);

        return back()->with('status', 'Invitation cancelled.');
    }

    public function accounts(Request $request): View
    {
        $query = User::query()->with(['employee', 'workforceRole', 'branch'])->where('company_id', $request->user()->company_id);
        if ($request->filled('state')) {
            $query->where('account_status', $request->string('state')->toString());
        }
        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(fn (Builder $users) => $users->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return view('command-center.workforce.users.index', ['users' => $query->latest()->paginate(20)->withQueryString()]);
    }

    public function state(Request $request, WorkforceService $service, int $user): RedirectResponse
    {
        $target = User::query()->where('company_id', $request->user()->company_id)->findOrFail($user);
        $data = $request->validate(['state' => ['required', 'in:active,suspended,disabled']]);
        $service->changeAccountState($request->user(), $target, $data['state']);

        return back()->with('status', 'Account state updated. Any active remembered login is invalidated.');
    }

    public function roles(Request $request): View
    {
        return view('command-center.workforce.roles.index', [
            'roles' => WorkforceRole::query()->with(['permissions'])->withCount('users')->where('company_id', $request->user()->company_id)->orderBy('name')->get(),
            'permissions' => collect(config('permissions.capabilities', []))
                ->map(fn (array $roles): array => collect($roles)
                    ->map(fn (string $role): string => (string) str($role)->headline())
                    ->all())
                ->all(),
            'baseRoles' => [UserRole::Manager, UserRole::Sales, UserRole::Staff],
        ]);
    }

    public function storeRole(Request $request, WorkforceService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:workforce_roles,name,NULL,id,company_id,'.$request->user()->company_id],
            'base_role' => ['required', 'in:manager,sales,staff'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ]);
        $service->createRole($request->user(), $data);

        return back()->with('status', 'Custom role created with its explicit permission matrix.');
    }

    public function assignRole(Request $request, WorkforceService $service, int $user): RedirectResponse
    {
        $target = User::query()->where('company_id', $request->user()->company_id)->findOrFail($user);
        $data = $request->validate(['workforce_role_id' => ['nullable', 'integer']]);
        $role = filled($data['workforce_role_id'] ?? null)
            ? WorkforceRole::query()->where('company_id', $request->user()->company_id)->findOrFail($data['workforce_role_id'])
            : null;
        $service->assignRole($request->user(), $target, $role);

        return back()->with('status', 'Role assignment updated.');
    }

    public function storeReview(Request $request, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);
        $this->assertCanReviewEmployee($request->user(), $employee);
        $data = $request->validate([
            'period_starts_at' => ['required', 'date'], 'period_ends_at' => ['required', 'date', 'after_or_equal:period_starts_at'],
            'cycle' => ['required', 'in:monthly,quarterly,half_yearly,annual,custom'], 'status' => ['required', 'in:draft,submitted'],
            'customer_service' => ['nullable', 'integer', 'between:1,5'], 'product_knowledge' => ['nullable', 'integer', 'between:1,5'],
            'teamwork' => ['nullable', 'integer', 'between:1,5'], 'reliability' => ['nullable', 'integer', 'between:1,5'],
            'communication' => ['nullable', 'integer', 'between:1,5'], 'initiative' => ['nullable', 'integer', 'between:1,5'],
            'comments' => ['required', 'string', 'max:4000'],
        ]);
        $review = WorkforceManagerReview::create($data + [
            'company_id' => $request->user()->company_id, 'employee_id' => $employee->id, 'reviewer_user_id' => $request->user()->id,
            'submitted_at' => $data['status'] === 'submitted' ? now() : null,
        ]);
        app(AuditLogger::class)->record('workforce.review.created', $review, 'Manager review created');

        return back()->with('status', 'Manager review saved. Operational data and manager judgement remain separate.');
    }

    public function storeRecognition(Request $request, int $employee): RedirectResponse
    {
        $employee = $this->employeeQuery($request)->findOrFail($employee);
        $this->assertCanReviewEmployee($request->user(), $employee);
        $data = $request->validate([
            'type' => ['required', 'in:employee_of_month,customer_service,sales_achievement,inventory_accuracy,team_contribution,manager_appreciation,custom'],
            'title' => ['required', 'string', 'max:160'], 'message' => ['nullable', 'string', 'max:2000'], 'recognized_on' => ['required', 'date'],
        ]);
        $recognition = WorkforceRecognition::create($data + ['company_id' => $request->user()->company_id, 'employee_id' => $employee->id, 'granted_by' => $request->user()->id]);
        app(AuditLogger::class)->record('workforce.recognition.granted', $recognition, 'Employee recognition granted');

        return back()->with('status', 'Recognition recorded.');
    }

    public function export(Request $request)
    {
        $employees = $this->employeeQuery($request)->with('primaryBranch')->orderBy('employee_number')->get();
        $rows = $employees->map(fn (WorkforceEmployee $employee) => [
            $this->csv($employee->employee_number), $this->csv($employee->display_name), $this->csv($employee->work_email),
            $this->csv($employee->job_title), $this->csv($employee->primaryBranch?->name), $this->csv($employee->status),
        ]);

        return Response::streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['Employee code', 'Name', 'Work email', 'Job title', 'Primary outlet', 'Status']);
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, 'employee-directory-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function self(Request $request, WorkforcePerformanceService $performance, TaskRepository $tasks): View
    {
        if (! $request->user()->employee) {
            return view('command-center.workforce.employees.self-unlinked', [
                'personalTaskMetrics' => $tasks->personalMetrics($request->user()),
                'workTaskMetrics' => $tasks->workMetrics($request->user()),
            ]);
        }

        $employee = $request->user()->employee->load(['primaryBranch', 'outletAssignments.branch', 'reviews', 'recognitions']);

        return view('command-center.workforce.employees.self', [
            'employee' => $employee,
            'metrics' => $performance->forEmployee($request->user(), $employee),
            'personalTaskMetrics' => $tasks->personalMetrics($request->user()),
            'workTaskMetrics' => $tasks->workMetrics($request->user()),
        ]);
    }

    public function showInvitation(string $token): View
    {
        $invitation = WorkforceInvitation::query()
            ->with('employee')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless($invitation->isUsable(), 404);

        return view('auth.workforce-activation', ['token' => $token, 'invitation' => $invitation]);
    }

    public function acceptInvitation(Request $request, WorkforceService $service, string $token): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:12', 'confirmed']]);
        $service->acceptInvitation($token, $data['password']);

        return redirect()->route('login')->with('status', 'Your account is active. You can now sign in.');
    }

    private function employeeQuery(Request $request): Builder
    {
        $query = WorkforceEmployee::query()->where('company_id', $request->user()->company_id);
        if (! $this->outlets->hasCompanyWideAccess($request->user())) {
            $outletIds = $this->outlets->accessibleOutlets($request->user())->pluck('id');
            $query->where(function (Builder $employees) use ($outletIds): void {
                $employees->whereIn('primary_branch_id', $outletIds)
                    ->orWhereHas('outletAssignments', fn (Builder $assignments) => $assignments->where('is_active', true)->whereIn('branch_id', $outletIds));
            });
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function employeeFormData(Request $request): array
    {
        return [
            'branches' => $this->branchesFor($request),
            'warehouses' => Warehouse::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
            'registers' => PosRegister::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
            'managers' => $this->employeeQuery($request)->whereIn('status', ['active', 'on_leave'])->orderBy('display_name')->get(),
        ];
    }

    /** @return Collection<int, Branch> */
    private function branchesFor(Request $request)
    {
        return $this->outlets->accessibleOutlets($request->user());
    }

    /** @return array<string, mixed> */
    private function validatedEmployee(Request $request, ?WorkforceEmployee $employee = null): array
    {
        return $request->validate([
            'employee_number' => ['required', 'string', 'max:80', 'unique:workforce_employees,employee_number,'.($employee?->id ?? 'NULL').',id,company_id,'.$request->user()->company_id],
            'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['nullable', 'string', 'max:100'], 'display_name' => ['required', 'string', 'max:160'],
            'work_email' => ['nullable', 'email', 'max:255'], 'work_mobile' => ['nullable', 'string', 'max:32'], 'job_title' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'], 'joining_date' => ['nullable', 'date'], 'primary_branch_id' => ['nullable', 'integer'],
            'reporting_manager_id' => ['nullable', 'integer'], 'status' => ['required', 'in:draft,invited,active,on_leave,suspended,inactive,archived'],
            'manager_notes' => ['nullable', 'string', 'max:4000'], 'outlet_ids' => ['array'], 'outlet_ids.*' => ['integer'],
            'warehouse_ids' => ['array'], 'warehouse_ids.*' => ['integer'], 'register_ids' => ['array'], 'register_ids.*' => ['integer'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedAccount(Request $request, bool $direct): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'mobile' => ['nullable', 'string', 'max:32'],
            'branch_id' => ['nullable', 'integer'], 'role' => ['required', 'in:administrator,manager,sales,staff'], 'workforce_role_id' => ['nullable', 'integer'],
        ];
        if ($direct) {
            $rules['email'][] = 'unique:users,email';
            $rules += ['password' => ['required', 'string', 'min:12', 'confirmed']];
        }

        return $request->validate($rules);
    }

    private function csv(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }

    private function assertCanReviewEmployee(User $actor, WorkforceEmployee $employee): void
    {
        if ($this->outlets->hasCompanyWideAccess($actor)) {
            return;
        }

        $managerId = WorkforceEmployee::query()
            ->where('company_id', $actor->company_id)
            ->whereHas('user', fn (Builder $user) => $user->whereKey($actor->id))
            ->value('id');
        abort_unless($managerId && $employee->reporting_manager_id === (int) $managerId, 403);
    }
}
