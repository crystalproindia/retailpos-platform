<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\OnboardingStatus;
use App\Enums\Crm\ProformaStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmOnboardingTask;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Services\Crm\CrmOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_start_customer_onboarding_with_the_default_checklist_and_audit_trail(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager, 'Orchid Retail Group');

        $this->actingAs($manager)
            ->post("/crm/customers/{$customer->id}/onboarding", [
                'priority' => 'high',
                'implementation_owner_id' => $manager->id,
                'target_go_live_date' => now()->addWeeks(2)->toDateString(),
            ])
            ->assertRedirect();

        $onboarding = CrmCustomerOnboarding::query()->with('tasks')->firstOrFail();

        $this->assertSame('ONB-'.now()->format('Y').'-000001', $onboarding->onboarding_number);
        $this->assertSame(OnboardingStatus::NotStarted, $onboarding->status);
        $this->assertSame(26, $onboarding->tasks->count());
        $this->assertSame($manager->id, $onboarding->implementation_owner_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.onboarding.started', 'auditable_id' => $onboarding->id]);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.onboarding.started')->exists());

        $this->actingAs($manager)
            ->get("/crm/onboarding/{$onboarding->id}")
            ->assertOk()
            ->assertSee('Implementation checklist')
            ->assertSee('Activity timeline')
            ->assertSee('Orchid Retail Group');
    }

    public function test_active_onboarding_cannot_be_started_twice_but_a_completed_one_can_be_restarted(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);

        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertSessionHasErrors('customer');

        CrmCustomerOnboarding::query()->firstOrFail()->update(['status' => OnboardingStatus::Live]);

        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $this->assertDatabaseCount('crm_customer_onboardings', 2);
    }

    public function test_task_updates_recalculate_progress_and_custom_tasks_are_company_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $onboarding = CrmCustomerOnboarding::query()->with('tasks')->firstOrFail();
        $task = $onboarding->tasks->firstOrFail();

        $this->actingAs($manager)
            ->post("/crm/onboarding/{$onboarding->id}/tasks/{$task->id}", ['status' => 'completed'])
            ->assertRedirect();

        $this->assertTrue($task->refresh()->completed_at !== null);
        $this->assertGreaterThan(0, $onboarding->refresh()->progress_percent);

        $this->actingAs($manager)
            ->post("/crm/onboarding/{$onboarding->id}/tasks", [
                'task_key' => 'site-readiness',
                'title' => 'Confirm site readiness',
                'category' => 'custom',
                'assigned_to' => $manager->id,
                'is_required' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('crm_onboarding_tasks', ['onboarding_id' => $onboarding->id, 'task_key' => 'site-readiness']);

        $outside = $this->user(UserRole::Manager);
        $this->actingAs($manager)
            ->post("/crm/onboarding/{$onboarding->id}/tasks", [
                'task_key' => 'outside-assignment',
                'title' => 'Invalid external assignment',
                'category' => 'custom',
                'assigned_to' => $outside->id,
            ])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_notes_and_external_document_statuses_are_recorded_on_the_onboarding(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $onboarding = CrmCustomerOnboarding::query()->firstOrFail();

        $this->actingAs($manager)
            ->post("/crm/onboarding/{$onboarding->id}/notes", ['note' => 'Customer confirmed branch details.', 'visibility' => 'customer_safe'])
            ->assertRedirect();
        $this->actingAs($manager)
            ->post("/crm/onboarding/{$onboarding->id}/documents", ['document_type' => 'gst_certificate', 'title' => 'GST certificate', 'external_url' => 'https://files.example.test/gst.pdf', 'status' => 'requested'])
            ->assertRedirect();

        $document = $onboarding->documents()->firstOrFail();
        $this->actingAs($manager)
            ->put("/crm/onboarding/{$onboarding->id}/documents/{$document->id}", ['document_type' => 'gst_certificate', 'title' => 'GST certificate', 'external_url' => 'https://files.example.test/gst.pdf', 'status' => 'verified'])
            ->assertRedirect();

        $this->assertDatabaseHas('crm_onboarding_notes', ['onboarding_id' => $onboarding->id, 'visibility' => 'customer_safe']);
        $this->assertDatabaseHas('crm_onboarding_documents', ['id' => $document->id, 'status' => 'verified']);
    }

    public function test_onboarding_can_be_started_from_a_customer_linked_proforma(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $proforma = CrmProformaInvoice::create([
            'company_id' => $manager->company_id,
            'customer_id' => $customer->id,
            'proforma_number' => 'RPI-'.now()->format('Y').'-000001',
            'title' => 'Implementation advance',
            'currency' => 'INR',
            'invoice_date' => today(),
            'grand_total' => 1000,
            'balance_amount' => 1000,
            'status' => ProformaStatus::Sent,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post("/crm/proforma-invoices/{$proforma->id}/onboarding")->assertRedirect();

        $this->assertDatabaseHas('crm_customer_onboardings', ['customer_id' => $customer->id, 'proforma_invoice_id' => $proforma->id]);
    }

    public function test_all_required_tasks_complete_to_go_live_ready_and_marking_live_sets_the_date_and_notification(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $onboarding = CrmCustomerOnboarding::query()->with('tasks')->firstOrFail();
        $service = app(CrmOnboardingService::class);

        foreach ($onboarding->tasks->where('is_required', true) as $task) {
            $service->updateTask($onboarding, $task, $manager, ['status' => 'completed']);
        }

        $this->assertSame(OnboardingStatus::GoLiveReady, $onboarding->refresh()->status);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.onboarding.go_live_ready')->exists());

        $this->actingAs($manager)->post("/crm/onboarding/{$onboarding->id}/status", ['status' => 'live'])->assertRedirect();

        $this->assertSame(OnboardingStatus::Live, $onboarding->refresh()->status);
        $this->assertSame(today()->toDateString(), $onboarding->actual_go_live_date?->toDateString());
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.onboarding.live')->exists());
    }

    public function test_reminder_command_records_overdue_task_document_and_target_go_live_events(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding")->assertRedirect();
        $onboarding = CrmCustomerOnboarding::query()->with('tasks')->firstOrFail();
        $onboarding->update(['target_go_live_date' => now()->subDay()->toDateString()]);
        $onboarding->tasks->firstOrFail()->update(['due_date' => now()->subDay()->toDateString()]);
        $onboarding->documents()->create(['document_type' => 'product_master', 'title' => 'Product master', 'status' => 'requested', 'uploaded_by' => $manager->id]);

        $this->artisan('retailpos:onboarding-reminders')->assertSuccessful();

        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.onboarding.task_overdue', 'aggregate_id' => $onboarding->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.onboarding.document_pending', 'aggregate_id' => $onboarding->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.onboarding.target_go_live_missed', 'aggregate_id' => $onboarding->id]);
    }

    public function test_onboarding_list_filters_and_dashboards_are_company_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager, 'Northstar Retail');
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/onboarding", ['target_go_live_date' => now()->addWeek()->toDateString()])->assertRedirect();
        $onboarding = CrmCustomerOnboarding::query()->firstOrFail();

        $other = $this->user(UserRole::Manager);
        $outsideCustomer = $this->customer($other, 'Outside Retail');
        $this->actingAs($other)->post("/crm/customers/{$outsideCustomer->id}/onboarding")->assertRedirect();

        $this->actingAs($manager)
            ->get('/crm/onboarding?search=Northstar&status=not_started')
            ->assertOk()
            ->assertSee('Northstar Retail')
            ->assertDontSee('Outside Retail');

        $this->actingAs($manager)
            ->get('/crm')
            ->assertOk()
            ->assertSee('Customer Onboarding')
            ->assertSee('Northstar Retail')
            ->assertSee('/crm/onboarding/'.$onboarding->id, false);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Customer Onboarding')
            ->assertSee('Northstar Retail');
    }

    public function test_staff_cannot_access_or_start_customer_onboarding(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $customer = $this->customer($manager);

        $this->actingAs($staff)->get('/crm/onboarding')->assertForbidden();
        $this->actingAs($staff)->post("/crm/customers/{$customer->id}/onboarding")->assertForbidden();
    }

    public function test_sales_user_cannot_reassign_an_onboarding_owned_by_them(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $customer = $this->customer($manager);
        $onboarding = CrmCustomerOnboarding::create([
            'company_id' => $manager->company_id,
            'customer_id' => $customer->id,
            'onboarding_number' => 'ONB-'.now()->format('Y').'-000001',
            'title' => 'Implementation: '.$customer->company_name,
            'status' => OnboardingStatus::NotStarted,
            'priority' => 'normal',
            'assigned_to' => $sales->id,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($sales)
            ->put("/crm/onboarding/{$onboarding->id}", [
                'title' => $onboarding->title,
                'status' => OnboardingStatus::NotStarted->value,
                'priority' => 'normal',
                'assigned_to' => $manager->id,
            ])
            ->assertForbidden();
    }

    private function customer(User $user, string $companyName = 'Demo Retail Group'): CrmCustomer
    {
        return CrmCustomer::create([
            'company_id' => $user->company_id,
            'customer_code' => 'RPC-'.str_pad((string) (CrmCustomer::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'company_name' => $companyName,
            'display_name' => 'Asha Mehta',
            'email' => str($companyName)->slug().'@example.test',
            'phone' => '+91 90000 11111',
            'number_of_stores' => 2,
            'status' => CrmCustomerStatus::Onboarding,
            'created_by' => $user->id,
        ]);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
