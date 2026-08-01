<?php

namespace App\Http\Controllers\CommandCenter\Attendance;

use App\Http\Controllers\Controller;
use App\Models\ShiftAssignment;
use App\Models\ShiftTemplate;
use App\Models\WorkforceEmployee;
use App\Services\Attendance\AttendanceAccessService;
use App\Services\Attendance\RosterService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RosterController extends Controller
{
    public function __construct(private readonly RosterService $rosters, private readonly AttendanceAccessService $access) {}
    public function index(Request $request): View { $outlets = app(\App\Services\Outlets\OutletAccessService::class)->accessibleOutlets($request->user()); $outlet = $outlets->firstWhere('id', (int) $request->input('outlet_id')) ?: $outlets->first(); $week = CarbonImmutable::parse($request->input('week', now()), $outlet?->timezone ?: config('app.timezone'))->startOfWeek(); $employees = WorkforceEmployee::query()->where('company_id', $request->user()->company_id)->when($outlet, fn ($q) => $q->where('primary_branch_id', $outlet->id))->where('status', 'active')->with(['shiftAssignments' => fn ($q) => $q->with('shift')->whereBetween('work_date', [$week->toDateString(), $week->addDays(6)->toDateString()])])->orderBy('display_name')->get(); $shifts = ShiftTemplate::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->get(); return view('command-center.attendance.roster', compact('outlets', 'outlet', 'week', 'employees', 'shifts')); }
    public function storeShift(Request $request): RedirectResponse { $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:40'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i', 'different:start_time'], 'standard_work_minutes' => ['required', 'integer', 'min:1', 'max:1440'], 'overtime_after_minutes' => ['required', 'integer', 'min:1', 'max:1440']]); ShiftTemplate::create($data + ['company_id' => $request->user()->company_id, 'crosses_midnight' => $request->boolean('crosses_midnight'), 'unpaid_break_minutes' => (int) $request->input('unpaid_break_minutes', 0), 'grace_before_minutes' => (int) $request->input('grace_before_minutes', 0), 'grace_after_minutes' => (int) $request->input('grace_after_minutes', 0), 'minimum_work_minutes' => (int) $request->input('minimum_work_minutes', 0), 'is_active' => true, 'created_by' => $request->user()->id]); return back()->with('success', 'Shift template created.'); }
    public function assign(Request $request): RedirectResponse { $data = $request->validate(['employee_id' => ['required', 'integer'], 'shift_template_id' => ['required', 'integer'], 'work_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:1000']]); $this->rosters->assign($request->user(), $data); return back()->with('success', 'Shift assignment saved.'); }
    public function publish(Request $request): RedirectResponse { $data = $request->validate(['outlet_id' => ['required', 'integer'], 'week_starts_on' => ['required', 'date']]); $this->rosters->publish($request->user(), $data['outlet_id'], $data['week_starts_on']); return back()->with('success', 'Roster published.'); }
}
