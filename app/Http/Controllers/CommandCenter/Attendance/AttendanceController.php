<?php

namespace App\Http\Controllers\CommandCenter\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\OvertimeReview;
use App\Models\User;
use App\Models\WorkforceEmployee;
use App\Services\Attendance\AttendanceAccessService;
use App\Services\Attendance\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceAccessService $access, private readonly AttendanceService $attendance) {}

    public function self(Request $request): View
    {
        $employee = $request->user()->employee;
        if (! $employee || $employee->status !== 'active' || $employee->company_id !== $request->user()->company_id) return view('command-center.attendance.self-unlinked');
        $timezone = $employee->primaryBranch?->timezone ?: $request->user()->company?->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $record = AttendanceRecord::query()->with(['breaks', 'shiftAssignment.shift', 'outlet'])->where('company_id', $request->user()->company_id)->where('employee_id', $employee->id)->whereDate('attendance_date', $today)->first();
        $upcoming = $employee->shiftAssignments()->with(['shift', 'outlet'])->whereDate('work_date', '>=', $today)->orderBy('work_date')->limit(7)->get();
        $balances = $employee->leaveBalances()->with('leaveType')->where('period', CarbonImmutable::now($timezone)->format('Y'))->get();
        $recent = $employee->attendanceRecords()->latest('attendance_date')->limit(7)->get();
        return view('command-center.attendance.self', compact('employee', 'record', 'upcoming', 'balances', 'recent', 'timezone'));
    }

    public function history(Request $request): View
    {
        $employee = $this->access->employeeFor($request->user());
        $records = $employee->attendanceRecords()->with('outlet')->latest('attendance_date')->paginate(20);
        return view('command-center.attendance.history', compact('employee', 'records'));
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $this->attendance->checkIn($request->user(), null, $request->validate(['method' => ['nullable', 'in:web,pin_terminal'], 'notes' => ['nullable', 'string', 'max:1000']]));
        return back()->with('success', 'You are checked in.');
    }

    public function checkOut(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $this->attendance->checkOut($request->user(), $attendance, $request->validate(['method' => ['nullable', 'in:web,pin_terminal'], 'override_active_break' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:1000']]));
        return back()->with('success', 'Your attendance summary is ready.');
    }

    public function startBreak(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $data = $request->validate(['break_type' => ['required', 'in:meal,short_break,official_duty,other'], 'notes' => ['nullable', 'string', 'max:1000']]);
        $this->attendance->startBreak($request->user(), $attendance, $data['break_type'], $data['notes'] ?? null);
        return back()->with('success', 'Break started.');
    }

    public function endBreak(Request $request, AttendanceBreak $break): RedirectResponse
    {
        $this->attendance->endBreak($request->user(), $break, $request->validate(['notes' => ['nullable', 'string', 'max:1000']])['notes'] ?? null);
        return back()->with('success', 'Break ended.');
    }

    public function requestCorrection(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $data = $request->validate(['checked_in_at' => ['nullable', 'date'], 'checked_out_at' => ['nullable', 'date'], 'outlet_id' => ['nullable', 'integer'], 'attendance_status' => ['nullable', 'in:present,partial_day,absent,missing_check_out'], 'notes' => ['nullable', 'string', 'max:1000'], 'reason' => ['required', 'string', 'max:1000']]);
        $requested = collect($data)->except('reason')->filter(fn ($value) => $value !== null)->all();
        $this->attendance->requestCorrection($request->user(), $attendance, $requested, $data['reason']);
        return back()->with('success', 'Correction request submitted for review.');
    }

    public function dashboard(Request $request): View
    {
        $today = now()->toDateString();
        $records = $this->access->attendanceQuery($request->user())->with(['employee.primaryBranch', 'outlet'])->whereDate('attendance_date', $today);
        $metrics = ['present' => (clone $records)->whereIn('attendance_status', ['present', 'partial_day'])->count(), 'late' => (clone $records)->where('late_minutes', '>', 0)->count(), 'on_break' => (clone $records)->whereHas('breaks', fn ($query) => $query->whereNull('ended_at'))->count(), 'missing_check_out' => (clone $records)->where('attendance_status', 'missing_check_out')->count(), 'on_leave' => 0];
        $exceptions = (clone $records)->whereIn('attendance_status', ['missing_check_out', 'pending_correction'])->latest()->limit(8)->get();
        $pendingLeave = \App\Models\LeaveRequest::query()->where('company_id', $request->user()->company_id)->where('status', 'pending')->whereIn('outlet_id', $this->accessibleOutletIds($request->user()))->count();
        $pendingCorrections = AttendanceCorrection::query()->where('company_id', $request->user()->company_id)->where('status', 'pending')->whereHas('employee', fn ($query) => $query->whereIn('primary_branch_id', $this->accessibleOutletIds($request->user())))->count();
        return view('command-center.attendance.dashboard', compact('metrics', 'exceptions', 'pendingLeave', 'pendingCorrections'));
    }

    public function index(Request $request): View
    {
        $query = $this->access->attendanceQuery($request->user())->with(['employee', 'outlet']);
        if ($request->filled('date')) $query->whereDate('attendance_date', $request->string('date'));
        if ($request->filled('status')) $query->where('attendance_status', $request->string('status'));
        $records = $query->latest('attendance_date')->paginate(30)->withQueryString();
        return view('command-center.attendance.index', compact('records'));
    }

    public function managerCheckIn(Request $request, WorkforceEmployee $employee): RedirectResponse
    {
        $data = $request->validate(['checked_in_at' => ['nullable', 'date'], 'notes' => ['required', 'string', 'max:1000']]);
        $this->attendance->checkIn($request->user(), $employee, $data + ['is_manual' => true, 'method' => 'manager_entry']);
        return back()->with('success', 'Manual check-in recorded.');
    }

    public function managerCheckOut(Request $request, AttendanceRecord $attendance): RedirectResponse
    {
        $data = $request->validate(['checked_out_at' => ['nullable', 'date'], 'notes' => ['required', 'string', 'max:1000'], 'override_active_break' => ['nullable', 'boolean']]);
        $this->attendance->checkOut($request->user(), $attendance, $data + ['method' => 'manager_entry']);
        return back()->with('success', 'Manual check-out recorded.');
    }

    public function reviewCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]);
        $this->attendance->reviewCorrection($request->user(), $correction, $data['decision'] === 'approved', $data['review_note'] ?? null);
        return back()->with('success', 'Correction reviewed.');
    }

    public function reviewOvertime(Request $request, OvertimeReview $review): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected'], 'approved_minutes' => ['nullable', 'integer', 'min:0'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $this->attendance->reviewOvertime($request->user(), $review, $data['status'], (int) ($data['approved_minutes'] ?? 0), $data['reason'] ?? null);
        return back()->with('success', 'Overtime review saved.');
    }

    public function summary(Request $request): View
    {
        $query = $this->access->attendanceQuery($request->user());
        $from = $request->date('from', now()->startOfMonth())->toDateString(); $to = $request->date('to', now())->toDateString();
        $rows = $query->with('employee')->whereBetween('attendance_date', [$from, $to])->get()->groupBy('employee_id')->map(fn ($records) => ['employee' => $records->first()->employee, 'scheduled_days' => $records->count(), 'present_days' => $records->whereIn('attendance_status', ['present', 'partial_day'])->count(), 'worked_minutes' => $records->sum('worked_minutes'), 'overtime_minutes' => $records->sum('overtime_minutes'), 'late_minutes' => $records->sum('late_minutes'), 'missing' => $records->where('attendance_status', 'missing_check_out')->count()]);
        return view('command-center.attendance.summary', compact('rows', 'from', 'to'));
    }

    public function export(Request $request)
    {
        $records = $this->access->attendanceQuery($request->user())->with(['employee', 'outlet'])->latest('attendance_date')->limit(5000)->get();
        app(\App\Services\AuditLogger::class)->record('attendance.exported', $request->user(), 'Attendance register exported');
        return Response::streamDownload(function () use ($records): void { $out = fopen('php://output', 'w'); fputcsv($out, ['Date', 'Employee', 'Outlet', 'Status', 'Worked minutes', 'Late minutes', 'Overtime candidate']); foreach ($records as $record) fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value, [$record->attendance_date->toDateString(), $record->employee?->display_name, $record->outlet?->name, $record->attendance_status, $record->worked_minutes, $record->late_minutes, $record->overtime_minutes])); fclose($out); }, 'attendance-register.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<int, int> */
    private function accessibleOutletIds(User $user): array { return app(\App\Services\Outlets\OutletAccessService::class)->accessibleOutlets($user)->pluck('id')->all(); }
}
