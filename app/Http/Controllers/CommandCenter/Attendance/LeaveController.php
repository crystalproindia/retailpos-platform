<?php

namespace App\Http\Controllers\CommandCenter\Attendance;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkforceEmployee;
use App\Services\Attendance\AttendanceAccessService;
use App\Services\Attendance\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private readonly LeaveService $leaves, private readonly AttendanceAccessService $access) {}
    public function self(Request $request): View { $employee = $this->access->employeeFor($request->user()); $types = LeaveType::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->get(); $requests = $employee->leaveRequests()->with('leaveType')->latest()->paginate(20); $balances = $employee->leaveBalances()->with('leaveType')->where('period', now()->format('Y'))->get(); return view('command-center.attendance.leave.self', compact('employee', 'types', 'requests', 'balances')); }
    public function store(Request $request): RedirectResponse { $data = $request->validate(['leave_type_id' => ['required', 'integer'], 'starts_on' => ['required', 'date'], 'ends_on' => ['required', 'date'], 'day_portion' => ['required', 'in:full_day,first_half,second_half'], 'reason' => ['nullable', 'string', 'max:2000']]); $this->leaves->request($request->user(), $data); return back()->with('success', 'Leave request submitted.'); }
    public function withdraw(Request $request, LeaveRequest $leave): RedirectResponse { $this->leaves->withdraw($request->user(), $leave); return back()->with('success', 'Leave request withdrawn.'); }
    public function approvals(Request $request): View { $ids = app(\App\Services\Outlets\OutletAccessService::class)->accessibleOutlets($request->user())->pluck('id'); $requests = LeaveRequest::query()->with(['employee', 'leaveType', 'outlet'])->where('company_id', $request->user()->company_id)->whereIn('outlet_id', $ids)->latest()->paginate(30); $types = LeaveType::query()->where('company_id', $request->user()->company_id)->withCount('balances')->get(); return view('command-center.attendance.leave.approvals', compact('requests', 'types')); }
    public function review(Request $request, LeaveRequest $leave): RedirectResponse { $data = $request->validate(['decision' => ['required', 'in:approved,rejected'], 'review_note' => ['nullable', 'string', 'max:1000']]); $this->leaves->review($request->user(), $leave, $data['decision'] === 'approved', $data['review_note'] ?? null); return back()->with('success', 'Leave request reviewed.'); }
    public function storeType(Request $request): RedirectResponse { $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'code' => ['required', 'string', 'max:40'], 'annual_entitlement' => ['required', 'numeric', 'min:0'], 'is_paid' => ['nullable', 'boolean'], 'negative_balance_allowed' => ['nullable', 'boolean'], 'approval_required' => ['nullable', 'boolean'], 'description' => ['nullable', 'string', 'max:1000']]); $this->leaves->createType($request->user(), $data + ['is_paid' => $request->boolean('is_paid'), 'negative_balance_allowed' => $request->boolean('negative_balance_allowed'), 'approval_required' => $request->boolean('approval_required'), 'is_active' => true]); return back()->with('success', 'Leave policy created.'); }
    public function adjustBalance(Request $request, WorkforceEmployee $employee): RedirectResponse { $data = $request->validate(['leave_type_id' => ['required', 'integer'], 'period' => ['required', 'date_format:Y'], 'amount' => ['required', 'numeric'], 'reason' => ['required', 'string', 'max:1000']]); $type = LeaveType::query()->where('company_id', $request->user()->company_id)->findOrFail($data['leave_type_id']); $this->leaves->adjustBalance($request->user(), $employee, $type, $data['period'], (float) $data['amount'], $data['reason']); return back()->with('success', 'Leave balance adjusted.'); }
}
