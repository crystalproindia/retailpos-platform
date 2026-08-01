<?php

namespace App\Http\Controllers\CommandCenter\Tasks;

use App\Enums\Tasks\TaskPriority;
use App\Enums\Tasks\TaskSourceType;
use App\Enums\Tasks\TaskStatus;
use App\Enums\Tasks\TaskType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\TransitionTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Tasks\Task;
use App\Models\User;
use App\Repositories\Tasks\TaskRepository;
use App\Services\Outlets\OutletAccessService;
use App\Services\Tasks\TaskAccessService;
use App\Services\Tasks\TaskRelatedRecordRegistry;
use App\Services\Tasks\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function index(Request $request, TaskRepository $tasks, TaskRelatedRecordRegistry $records, OutletAccessService $outlets, TaskAccessService $access): View
    {
        $filters = $this->filters($request, $records, $outlets);
        $user = $request->user();
        $prefill = $this->prefill($request, $records);
        $taskPage = $tasks->paginateForUser($user, $filters);

        return view('command-center.tasks.index', [
            'tasks' => $taskPage,
            'manageableTaskIds' => $taskPage->getCollection()
                ->filter(fn (Task $task) => $access->canManage($user, $task))
                ->modelKeys(),
            'filters' => $request->query(),
            'view' => $filters['view'] ?? 'all',
            'personalMetrics' => $tasks->personalMetrics($user),
            'workMetrics' => $tasks->workMetrics($user),
            'teamMetrics' => $user->can('tasks.view_team') ? $tasks->teamMetrics($user) : null,
            'priorities' => TaskPriority::cases(),
            'statuses' => TaskStatus::cases(),
            'sourceTypes' => TaskSourceType::cases(),
            'types' => TaskType::cases(),
            'relatedTypes' => $records->options(),
            'relatedRegistry' => $records,
            'outlets' => $outlets->accessibleOutlets($user),
            'users' => $this->users($user, $outlets),
            'prefill' => $prefill,
        ]);
    }

    public function show(Request $request, TaskRepository $tasks, TaskAccessService $access, TaskRelatedRecordRegistry $records, OutletAccessService $outlets, int $task): View
    {
        $user = $request->user();
        $task = $tasks->findForUser($user, $task);
        $access->assertCanView($user, $task);
        $task->load(['auditLogs.user']);

        return view('command-center.tasks.show', [
            'task' => $task,
            'canManage' => $access->canManage($user, $task),
            'canReassign' => $user->can('tasks.reassign'),
            'canReopen' => $user->can('tasks.reopen'),
            'relatedLabel' => $task->related && $records->isVisibleTo($user, $task->related) ? $records->label($task->related) : null,
            'relatedUrl' => $task->related && $records->isVisibleTo($user, $task->related) ? $records->routeFor($task->related) : null,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'outlets' => $outlets->accessibleOutlets($user),
            'users' => $this->users($user, $outlets),
        ]);
    }

    public function calendar(Request $request, TaskRepository $tasks): View
    {
        try {
            $start = Carbon::parse($request->query('start', now()->startOfWeek()->toDateString()))->startOfDay();
        } catch (\Throwable) {
            abort(422, 'Choose a valid calendar start date.');
        }
        $end = $start->copy()->addDays(6)->endOfDay();
        $calendarTasks = $tasks->queryForUser($request->user())
            ->whereBetween('due_at', [$start, $end])
            ->orderBy('due_at')
            ->limit(250)
            ->get()
            ->groupBy(fn (Task $task) => $task->due_at?->toDateString());

        return view('command-center.tasks.calendar', compact('start', 'end', 'calendarTasks'));
    }

    public function store(StoreTaskRequest $request, TaskService $service): RedirectResponse
    {
        $task = $service->create($request->user(), $request->validated());

        return redirect()->route('tasks.show', $task)->with('status', 'Task created.');
    }

    public function update(UpdateTaskRequest $request, TaskRepository $tasks, TaskService $service, int $task): RedirectResponse
    {
        $task = $service->update($tasks->findForUser($request->user(), $task), $request->user(), $request->validated());

        return redirect()->route('tasks.show', $task)->with('status', 'Task updated.');
    }

    public function transition(TransitionTaskRequest $request, TaskRepository $tasks, TaskService $service, int $task): RedirectResponse
    {
        $task = $service->transition($tasks->findForUser($request->user(), $task), $request->user(), $request->validated());

        return redirect()->route('tasks.show', $task)->with('status', 'Task status updated.');
    }

    public function archive(Request $request, TaskRepository $tasks, TaskService $service, int $task): RedirectResponse
    {
        abort_unless($request->user()->can('tasks.archive'), 403);
        $service->archive($tasks->findForUser($request->user(), $task), $request->user());

        return redirect()->route('tasks.index')->with('status', 'Task archived.');
    }

    public function export(Request $request, TaskRepository $tasks, TaskRelatedRecordRegistry $records, OutletAccessService $outlets)
    {
        abort_unless($request->user()->can('tasks.export'), 403);
        $rows = $tasks->exportForUser($request->user(), $this->filters($request, $records, $outlets));

        return response()->streamDownload(function () use ($rows, $records): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Title', 'Status', 'Priority', 'Due at', 'Assigned to', 'Outlet', 'Related record', 'Source']);
            foreach ($rows as $task) {
                fputcsv($output, array_map(fn (?string $value) => $this->csv($value), [
                    $task->title,
                    $task->status->label(),
                    $task->priority->label(),
                    $task->due_at?->toDateTimeString(),
                    $task->assignee?->name,
                    $task->outlet?->name,
                    $task->related ? $records->label($task->related) : null,
                    $task->source_type->label(),
                ]));
            }
            fclose($output);
        }, 'work-tasks-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request, TaskRelatedRecordRegistry $records, OutletAccessService $outlets): array
    {
        $filters = $request->only(['view', 'search', 'task_type', 'status', 'priority', 'assigned_user_id', 'outlet_id', 'related_type', 'source_type', 'created_by_user_id', 'due_from', 'due_to', 'completed_from', 'completed_to']);
        $routeView = $request->route('view');
        if (is_string($routeView) && $routeView !== '') {
            $filters['view'] = $routeView;
        }
        if (! empty($filters['related_type']) && $records->supports($filters['related_type'])) {
            $filters['related_type'] = $records->definitions()[$filters['related_type']]['model'];
        } else {
            unset($filters['related_type']);
        }

        if (! empty($filters['outlet_id'])) {
            $outlet = Branch::query()->where('company_id', $request->user()->company_id)->find((int) $filters['outlet_id']);
            abort_unless($outlet && $outlets->canAccess($request->user(), $outlet), 403);
        }

        return $filters;
    }

    /** @return array{type: string, id: int, label: string, outlet_id: ?int}|null */
    private function prefill(Request $request, TaskRelatedRecordRegistry $records): ?array
    {
        $type = $request->query('create_related_type');
        $id = $request->integer('create_related_id');
        if (! $type && ! $id) {
            return null;
        }

        abort_unless(is_string($type) && $id > 0 && $records->supports($type), 404);
        $record = $records->findForUser($request->user(), $type, $id);
        abort_unless($record, 404);

        return [
            'type' => $type,
            'id' => (int) $record->getKey(),
            'label' => $records->label($record),
            'outlet_id' => $records->outletId($record),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function users(User $user, OutletAccessService $outlets)
    {
        if (! $user->can('tasks.assign')) {
            return collect([$user]);
        }

        $query = User::query()->where('company_id', $user->company_id)->where('is_active', true);
        if (! $outlets->hasCompanyWideAccess($user)) {
            $assigneeIds = BranchUserAssignment::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->whereIn('branch_id', $outlets->accessibleOutlets($user)->pluck('id'))
                ->pluck('user_id');
            $query->whereIn('id', $assigneeIds);
        }

        return $query->orderBy('name')->get(['id', 'name', 'workforce_employee_id']);
    }

    private function csv(?string $value): string
    {
        $value ??= '';

        return Str::startsWith($value, ['=', '+', '-', '@']) ? "'{$value}" : $value;
    }
}
