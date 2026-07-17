<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\SupportTicketPriority;
use App\Enums\Crm\SupportTicketStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerOnboarding;
use App\Models\Crm\CrmSupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrmSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_a_customer_ticket_with_sla_audit_and_notification(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager, 'Orchid Retail');

        $this->actingAs($manager)->post('/crm/support/tickets', $this->payload($customer->id))->assertRedirect();

        $ticket = CrmSupportTicket::firstOrFail();
        $this->assertSame('TKT-'.now()->format('Y').'-000001', $ticket->ticket_number);
        $this->assertSame(SupportTicketStatus::New, $ticket->status);
        $this->assertNotNull($ticket->first_response_due_at);
        $this->assertNotNull($ticket->due_at);
        $this->assertDatabaseHas('crm_support_ticket_status_histories', ['ticket_id' => $ticket->id, 'new_status' => 'new']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.support.created', 'auditable_id' => $ticket->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.support_ticket_created', 'aggregate_id' => $ticket->id]);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.support_ticket_created')->exists());
    }

    public function test_roles_and_tenant_scope_protect_support_tickets(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $ticket = $this->ticket($manager);
        $other = $this->user(UserRole::Manager);
        $outside = $this->ticket($other);

        $this->actingAs($staff)->get('/crm/support/tickets')->assertForbidden();
        $this->actingAs($staff)->post('/crm/support/tickets', $this->payload())->assertForbidden();
        $this->actingAs($manager)->get("/crm/support/tickets/{$ticket->id}")->assertOk();
        $this->actingAs($manager)->get("/crm/support/tickets/{$outside->id}")->assertNotFound();
    }

    public function test_ticket_list_search_and_filters_show_the_expected_tickets(): void
    {
        $manager = $this->user(UserRole::Manager);
        $urgent = $this->ticket($manager, ['subject' => 'Printer offline', 'priority' => SupportTicketPriority::Urgent, 'status' => SupportTicketStatus::Open]);
        $this->ticket($manager, ['subject' => 'Training question', 'priority' => SupportTicketPriority::Low, 'status' => SupportTicketStatus::Resolved]);

        $this->actingAs($manager)->get('/crm/support/tickets?search=Printer&priority=urgent&unresolved=1')->assertOk()->assertSee($urgent->ticket_number)->assertSee('Printer offline')->assertDontSee('Training question');
    }

    public function test_valid_status_transitions_record_history_and_require_a_resolution_summary(): void
    {
        $manager = $this->user(UserRole::Manager);
        $ticket = $this->ticket($manager);

        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'resolved', 'resolution_summary' => 'Too early'])->assertSessionHasErrors('status');
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'open'])->assertRedirect();
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'resolved'])->assertSessionHasErrors('resolution_summary');
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'resolved', 'resolution_summary' => 'Restarted the print service and confirmed a test receipt.'])->assertRedirect();
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'closed'])->assertRedirect();
        $this->actingAs($manager)->put("/crm/support/tickets/{$ticket->id}", ['status' => 'reopened'])->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::Reopened, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertNotNull($ticket->reopened_at);
        $this->assertDatabaseCount('crm_support_ticket_status_histories', 5);
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.support_ticket_resolved')->exists());
    }

    public function test_internal_notes_customer_safe_replies_and_external_attachment_links_are_recorded(): void
    {
        $manager = $this->user(UserRole::Manager);
        $ticket = $this->ticket($manager);

        $this->actingAs($manager)->post("/crm/support/tickets/{$ticket->id}/messages", ['message' => 'Checking terminal logs.', 'visibility' => 'internal'])->assertRedirect();
        $this->actingAs($manager)->post("/crm/support/tickets/{$ticket->id}/messages", ['message' => 'We are investigating and will update you shortly.', 'visibility' => 'customer_safe'])->assertRedirect();
        $this->actingAs($manager)->post("/crm/support/tickets/{$ticket->id}/attachments", ['title' => 'Terminal screenshot', 'external_url' => 'https://files.example.test/terminal.png'])->assertRedirect();
        $this->actingAs($manager)->post("/crm/support/tickets/{$ticket->id}/attachments", ['title' => 'Invalid link', 'external_url' => 'javascript:alert(1)'])->assertSessionHasErrors('external_url');

        $this->assertDatabaseHas('crm_support_ticket_messages', ['ticket_id' => $ticket->id, 'visibility' => 'internal']);
        $this->assertDatabaseHas('crm_support_ticket_messages', ['ticket_id' => $ticket->id, 'visibility' => 'customer_safe']);
        $this->assertDatabaseHas('crm_support_ticket_attachments', ['ticket_id' => $ticket->id, 'external_url' => 'https://files.example.test/terminal.png']);
    }

    public function test_sla_reminders_are_idempotent_and_dashboard_and_context_views_show_ticket_data(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager, 'Northstar Retail');
        $onboarding = CrmCustomerOnboarding::create(['company_id' => $manager->company_id, 'customer_id' => $customer->id, 'onboarding_number' => 'ONB-'.now()->format('Y').'-000001', 'title' => 'Northstar implementation', 'status' => 'in_progress', 'priority' => 'normal', 'created_by' => $manager->id]);
        $ticket = $this->ticket($manager, ['customer_id' => $customer->id, 'onboarding_id' => $onboarding->id, 'status' => SupportTicketStatus::WaitingForInternalTeam, 'due_at' => now()->subHour(), 'first_response_due_at' => now()->subHour()]);
        DB::table('crm_support_tickets')->where('id', $ticket->id)->update(['updated_at' => now()->subDays(2)]);

        $this->artisan('retailpos:support-ticket-reminders')->assertSuccessful();
        $this->artisan('retailpos:support-ticket-reminders')->assertSuccessful();

        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.support_ticket_overdue', 'aggregate_id' => $ticket->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'crm.support_ticket_waiting_internal', 'aggregate_id' => $ticket->id]);
        $this->assertSame(3, DB::table('domain_event_logs')->where('aggregate_id', $ticket->id)->whereIn('event_key', ['crm.support_ticket_overdue', 'crm.support_ticket_waiting_internal'])->count());

        $this->actingAs($manager)->get('/crm')->assertOk()->assertSee('Customer Support')->assertSee($ticket->ticket_number)->assertSee('/crm/support/tickets/'.$ticket->id, false);
        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertSee('Customer Support')->assertSee($ticket->ticket_number);
        $this->actingAs($manager)->get("/crm/customers/{$customer->id}")->assertOk()->assertSee('Support Tickets')->assertSee($ticket->ticket_number);
        $this->actingAs($manager)->get("/crm/onboarding/{$onboarding->id}")->assertOk()->assertSee('Support')->assertSee($ticket->ticket_number);
    }

    /** @param array<string, mixed> $overrides */
    private function ticket(User $user, array $overrides = []): CrmSupportTicket
    {
        return CrmSupportTicket::create(array_merge([
            'company_id' => $user->company_id,
            'ticket_number' => 'TKT-'.now()->format('Y').'-'.str_pad((string) (CrmSupportTicket::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'subject' => 'Need help with receipt printer',
            'description' => 'The receipt printer does not respond after login.',
            'category' => 'hardware',
            'priority' => SupportTicketPriority::Normal,
            'status' => SupportTicketStatus::New,
            'source' => 'internal',
            'assigned_to' => $user->id,
            'reported_by_name' => 'Asha Mehta',
            'first_response_due_at' => now()->addDay(),
            'due_at' => now()->addDays(5),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }

    /** @return array<string, mixed> */
    private function payload(?int $customerId = null): array
    {
        return ['customer_id' => $customerId, 'subject' => 'Payment terminal is offline', 'description' => 'The branch payment terminal stopped responding after the morning shift.', 'category' => 'hardware', 'priority' => 'high', 'source' => 'phone', 'reported_by_name' => 'Asha Mehta', 'reported_by_email' => 'asha@example.test', 'reported_by_phone' => '+91 90000 11111'];
    }

    private function customer(User $user, string $companyName = 'Demo Retail'): CrmCustomer
    {
        return CrmCustomer::create(['company_id' => $user->company_id, 'customer_code' => 'RPC-'.str_pad((string) (CrmCustomer::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'company_name' => $companyName, 'display_name' => 'Asha Mehta', 'email' => 'asha@example.test', 'phone' => '+91 90000 11111', 'status' => CrmCustomerStatus::Active, 'created_by' => $user->id]);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
