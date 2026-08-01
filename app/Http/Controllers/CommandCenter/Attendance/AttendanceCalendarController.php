<?php

namespace App\Http\Controllers\CommandCenter\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Holiday;
use App\Models\WeeklyOff;
use App\Services\AuditLogger;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCalendarController extends Controller
{
    public function __construct(private readonly OutletAccessService $outlets, private readonly AuditLogger $audit) {}
    public function index(Request $request): View { $outlets = $this->outlets->accessibleOutlets($request->user()); $holidays = Holiday::query()->where('company_id', $request->user()->company_id)->with('outlet')->orderBy('holiday_date')->paginate(20); $weeklyOffs = WeeklyOff::query()->where('company_id', $request->user()->company_id)->latest()->get(); return view('command-center.attendance.calendar-settings', compact('outlets', 'holidays', 'weeklyOffs')); }
    public function storeHoliday(Request $request): RedirectResponse { $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'holiday_date' => ['required', 'date'], 'outlet_id' => ['nullable', 'integer'], 'holiday_type' => ['required', 'in:paid,unpaid,observance'], 'notes' => ['nullable', 'string', 'max:1000']]); $this->assertOutlet($request, $data['outlet_id'] ?? null); $holiday = Holiday::create($data + ['company_id' => $request->user()->company_id, 'is_active' => true, 'created_by' => $request->user()->id]); $this->audit->record('attendance.holiday.created', $holiday, 'Holiday calendar updated'); return back()->with('success', 'Holiday added.'); }
    public function storeWeeklyOff(Request $request): RedirectResponse { $data = $request->validate(['weekday' => ['required', 'integer', 'between:1,7'], 'outlet_id' => ['nullable', 'integer'], 'notes' => ['nullable', 'string', 'max:500']]); $this->assertOutlet($request, $data['outlet_id'] ?? null); $weeklyOff = WeeklyOff::create($data + ['company_id' => $request->user()->company_id, 'is_active' => true, 'created_by' => $request->user()->id]); $this->audit->record('attendance.weekly_off.created', $weeklyOff, 'Weekly off rule added'); return back()->with('success', 'Weekly off rule added'); }
    private function assertOutlet(Request $request, ?int $outletId): void { if (! $outletId) return; $outlet = Branch::query()->where('company_id', $request->user()->company_id)->findOrFail($outletId); if (! $this->outlets->canAccess($request->user(), $outlet)) abort(404); }
}
