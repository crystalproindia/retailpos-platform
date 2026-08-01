# Phase K: Attendance, Shifts, Leave and Workforce Scheduling

## Scope and safety

Phase K adds a tenant-scoped, outlet-authorized time-management foundation. It
does not calculate pay, issue payslips, make disciplinary decisions, integrate
biometric hardware, use Google Calendar/Meet, or collect location, camera,
microphone, or background tracking data.

## Attendance lifecycle and timezone rules

An active employee may create one authoritative `attendance_records` day in
their primary outlet/company timezone. Check-in and check-out are explicit user
or authorized-manager actions; timestamps are persisted as UTC instants while
the attendance date and shift window are resolved in the outlet/company
timezone. This retains correct elapsed time across daylight-saving changes.

The shared `AttendanceCalculator` is the sole source of worked, break, late,
early-departure, and overtime-candidate values. Worked minutes are actual
elapsed minutes less completed non-official-duty breaks. Grace after scheduled
start applies to lateness; grace before scheduled end applies to early
departure. Overtime is evidence only and requires an explicit review before it
is payroll-ready.

## Shifts, rosters and exceptions

`shift_templates` are reusable and may cross midnight. `shift_assignments`
keep one employee/day assignment and preserve history through soft deletion.
Assignments validate active employee, tenant, outlet, template scope, approved
leave and holiday conflicts. The weekly roster has a contained desktop grid and
an accessible assignment form in every unassigned cell; no drag-and-drop is
required.

Holidays and weekly offs are scoped by company, outlet, or employee. The daily
status command only creates holiday/weekly-off evidence; it never silently
marks people absent.

## Breaks, corrections and manual work

Breaks are normalized in `attendance_breaks`, with one active break per
attendance record. A normal checkout is blocked while a break is active. A
manager override requires a reason. Manual manager entries also require a
reason. Corrections preserve original and requested values separately; only an
authorized manager may approve/reject a pending correction, and approval
re-runs the shared calculator.

## Leave and balances

`leave_types` are controlled tenant policies, not a formula engine. Balances
are period-specific and reconcile from opening, accrued, adjusted, pending and
used values. `leave_balance_transactions` preserves each action. A pending
request reserves availability; approval moves it to used; rejection or
withdrawal releases it. Leave approval is human-only, outlet-scoped, and
self-approval is prohibited. Approved leave creates `on_leave` attendance
evidence only where no actual attendance exists.

## Authorization, privacy and audit

Employee routes require the current linked active employee profile. Manager
routes require the existing outlet access service plus the new explicit
attendance/shift/leave capability. Direct tenant or outlet bypasses return 404;
exports use the same authorized query path and neutralize spreadsheet formula
prefixes. Audit events cover check-in/out, break lifecycle, manual entry,
corrections, shifts, holidays, leave, balances, exports and overtime review.
The existing preference-aware in-app notification center receives leave-review
and attendance-correction review events; Phase K does not add email, SMS, or
external messaging. Leave reasons remain limited to the requester and
authorized reviewers.

## Payroll-ready boundary

The attendance summary presents traceable scheduled/present days, worked and
overtime-candidate minutes, late minutes and missing-checkout exceptions. It
never calculates salary, deductions, payslips or automatic penalties. Historical
rows are retained when employees or assignments later change.

## Scheduler and operations

`attendance:mark-missing-checkouts --limit=250` is hourly, bounded and
idempotent. `attendance:generate-daily-status --limit=500` is daily and only
adds holiday/weekly-off status. Both support `--company` and `--dry-run`; no
cron is configured by this code. Hostinger needs the existing one-minute Laravel
scheduler cron for these tasks to run.

## Migration and remediation

`2026_08_02_010000_create_attendance_shift_leave_foundation.php` is additive
and forward-only. It creates Phase K tables and does not alter existing users,
employees, tasks, CRM, invoices, payroll, permissions or historical records.
Use a new forward remediation migration in production instead of rolling back
populated attendance tables.

## Future Phase K.1 boundary

Future field-duty work may add explicit employee-consented duty actions and
short-lived location evidence. Phase K intentionally includes no location
columns, browser permission requests, tracking processes, maps, or monitoring
controls.

## Current limitations

No biometric import, payroll calculation, attachments, recurring rota copy,
automatic shift reminders, salary impact, disciplinary labels, GPS/location,
Google Calendar/Meet, or external messaging is included in V1.

## Verification

Focused attendance/leave/shift/field-duty coverage uses persisted SQLite
records, including notification dispatch. The final review passed 18 focused
tests with 78 assertions and the full suite passed 489 tests with 3,533
assertions. Browser verification covers the attendance, dashboard, roster,
leave, calendar, and manager-review screens at 1440px, 768px, and 390px with
no horizontal overflow or browser-console errors. Historical-harness, Vite,
cache, and route checks are recorded with the release verification for this
branch.
