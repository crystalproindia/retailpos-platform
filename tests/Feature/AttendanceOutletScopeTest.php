<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_outlet_manager_cannot_create_company_rules_or_view_another_outlet_roster(): void
    {
        [$manager, $ownOutlet, $otherOutlet] = $this->managerWithTwoOutlets();

        $this->actingAs($manager)->post(route('attendance.holidays.store'), [
            'name' => 'Company Day',
            'holiday_date' => '2026-08-15',
            'holiday_type' => 'paid',
        ])->assertForbidden();

        $this->actingAs($manager)
            ->get(route('attendance.roster', ['outlet_id' => $otherOutlet->id]))
            ->assertNotFound();
    }

    public function test_outlet_manager_sees_only_global_and_authorized_outlet_calendar_rules(): void
    {
        [$manager, $ownOutlet, $otherOutlet] = $this->managerWithTwoOutlets();
        Holiday::create(['company_id' => $manager->company_id, 'name' => 'Global Holiday', 'holiday_date' => '2026-08-15', 'holiday_type' => 'paid']);
        Holiday::create(['company_id' => $manager->company_id, 'outlet_id' => $ownOutlet->id, 'name' => 'Own Outlet Holiday', 'holiday_date' => '2026-08-16', 'holiday_type' => 'paid']);
        Holiday::create(['company_id' => $manager->company_id, 'outlet_id' => $otherOutlet->id, 'name' => 'Other Outlet Holiday', 'holiday_date' => '2026-08-17', 'holiday_type' => 'paid']);

        $this->actingAs($manager)->get(route('attendance.calendar-settings'))
            ->assertOk()
            ->assertSee('Global Holiday')
            ->assertSee('Own Outlet Holiday')
            ->assertDontSee('Other Outlet Holiday');
    }

    public function test_employee_cannot_request_a_correction_to_an_unassigned_outlet(): void
    {
        [$manager, $ownOutlet, $otherOutlet] = $this->managerWithTwoOutlets();
        $employee = WorkforceEmployee::create(['company_id' => $manager->company_id, 'primary_branch_id' => $ownOutlet->id, 'employee_number' => 'ATT-SCOPE-1', 'first_name' => 'Scope', 'display_name' => 'Scope Employee', 'status' => 'active']);
        $manager->forceFill(['workforce_employee_id' => $employee->id])->save();
        $record = AttendanceRecord::create(['company_id' => $manager->company_id, 'employee_id' => $employee->id, 'outlet_id' => $ownOutlet->id, 'attendance_date' => '2026-08-02', 'checked_in_at' => '2026-08-02 09:00:00', 'checked_out_at' => '2026-08-02 17:00:00', 'attendance_status' => 'present', 'attendance_state' => 'completed']);

        $this->actingAs($manager)->post(route('attendance.corrections.store', $record), [
            'outlet_id' => $otherOutlet->id,
            'reason' => 'Incorrect outlet selection.',
        ])->assertSessionHasErrors('outlet_id');
    }

    public function test_shift_template_creation_is_outlet_scoped_and_audited(): void
    {
        [$manager, $ownOutlet, $otherOutlet] = $this->managerWithTwoOutlets();

        $this->actingAs($manager)
            ->post(route('attendance.shifts.store'), [
                'name' => 'Morning shift',
                'code' => 'MORN',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'standard_work_minutes' => 480,
                'overtime_after_minutes' => 540,
                'outlet_id' => $otherOutlet->id,
            ])
            ->assertNotFound();

        $this->actingAs($manager)
            ->post(route('attendance.shifts.store'), [
                'name' => 'Morning shift',
                'code' => 'MORN',
                'start_time' => '09:00',
                'end_time' => '18:00',
                'standard_work_minutes' => 480,
                'overtime_after_minutes' => 540,
                'outlet_id' => $ownOutlet->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shift_templates', [
            'company_id' => $manager->company_id,
            'applicable_outlet_id' => $ownOutlet->id,
            'code' => 'MORN',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'attendance.shift_template.created',
        ]);
    }

    /** @return array{User, Branch, Branch} */
    private function managerWithTwoOutlets(): array
    {
        $company = Company::factory()->create();
        $ownOutlet = Branch::factory()->for($company)->create(['is_active' => true]);
        $otherOutlet = Branch::factory()->for($company)->create(['is_active' => true]);
        $manager = User::factory()->for($company)->create(['branch_id' => $ownOutlet->id, 'role' => UserRole::Manager, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $ownOutlet->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]);

        return [$manager, $ownOutlet, $otherOutlet];
    }
}
