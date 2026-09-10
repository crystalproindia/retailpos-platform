<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceReturn;
use App\Models\Crm\CrmLeadStageHistory;
use App\Models\User;
use App\Services\Crm\CrmPipelineAnalyticsService;
use App\Services\Crm\LeadStageTransitionService;
use App\Repositories\Crm\LeadRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmLeadStageHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_transition_creates_an_immutable_history_snapshot(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $lost = $this->leadStatus($manager, 'Lost', 'lost', LeadStageType::Lost);

        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", [
            'target_stage' => 'lost',
            'lost_reason' => 'Budget deferred',
            'lost_note' => 'Customer postponed the project until the next financial year.',
            'stage_transition_key' => '4e39a4a0-fd0e-4783-b43d-2e3bfb4a4a40',
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_lead_stage_histories', [
            'company_id' => $manager->company_id,
            'branch_id' => $manager->branch_id,
            'lead_id' => $lead->id,
            'from_status_id' => $lead->status_id,
            'to_status_id' => $lost->id,
            'from_stage' => 'new_lead',
            'to_stage' => 'lost',
            'lost_reason_snapshot' => 'Budget deferred',
            'actor_id' => $manager->id,
            'actor_name_snapshot' => $manager->name,
        ]);
        $this->assertNotNull($lead->refresh()->lost_at);
    }

    public function test_retry_key_does_not_duplicate_a_stage_transition_or_activity(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $proposal = $this->leadStatus($manager, 'Proposal Sent', 'proposal-sent', LeadStageType::Proposal);
        $key = 'a77f01ea-8dd7-445a-a638-3e65d57c3e2d';
        $transitions = app(LeadStageTransitionService::class);

        $transitions->transition($lead, $proposal->id, $manager, 'crm.pipeline.transitioned', true, ['idempotency_key' => $key]);
        $transitions->transition($lead->refresh(), $proposal->id, $manager, 'crm.pipeline.transitioned', true, ['idempotency_key' => $key]);

        $this->assertDatabaseCount('crm_lead_stage_histories', 1);
        $this->assertSame(1, $lead->activities()->where('subject', 'Pipeline moved from New Lead to Proposal Sent')->count());
    }

    public function test_transition_endpoints_are_http_idempotent_and_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $proposal = $this->leadStatus($manager, 'Proposal', 'proposal', LeadStageType::Proposal);
        $key = '5c5c5c5c-1111-4111-8111-111111111111';

        foreach ([
            ["/crm/pipeline/{$lead->id}", ['status_id' => $proposal->id, 'stage_transition_key' => $key], 'patch'],
            ["/crm/pipeline/{$lead->id}", ['status_id' => $proposal->id, 'stage_transition_key' => $key], 'patch'],
        ] as [$url, $payload, $method]) {
            $this->actingAs($manager)->{$method}($url, $payload)->assertRedirect();
        }
        $this->assertSame(1, CrmLeadStageHistory::where('lead_id', $lead->id)->count());
        $this->assertSame(1, $lead->activities()->where('subject', 'Pipeline moved from New Lead to Proposal Sent')->count());

        $won = $this->leadStatus($manager, 'Won', 'won', LeadStageType::Won);
        $this->actingAs($manager)->patch("/crm/pipeline/{$lead->id}", ['status_id' => $won->id, 'stage_transition_key' => $key])
            ->assertSessionHasErrors('stage_transition_key');
        $this->assertSame(1, CrmLeadStageHistory::where('lead_id', $lead->id)->count());

        $other = $this->user(UserRole::Manager);
        $outside = $this->lead($other);
        $this->actingAs($manager)->post("/crm/pipeline/cards/{$outside->id}/move", ['target_stage' => 'won', 'stage_transition_key' => '6c5c5c5c-1111-4111-8111-111111111111'])->assertNotFound();
        $this->actingAs($this->user(UserRole::Staff))->post("/crm/pipeline/cards/{$lead->id}/move", ['target_stage' => 'won', 'stage_transition_key' => '7c5c5c5c-1111-4111-8111-111111111111'])->assertForbidden();
    }

    public function test_pipeline_card_move_endpoint_is_http_idempotent_and_rejects_conflicting_key_reuse(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $key = '8c5c5c5c-1111-4111-8111-111111111111';
        $payload = ['target_stage' => 'contacted', 'stage_transition_key' => $key];

        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", $payload)->assertRedirect();
        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", $payload)->assertRedirect();
        $this->assertSame('contacted', $lead->fresh()->status->stage_type->value);
        $this->assertSame(1, CrmLeadStageHistory::where('lead_id', $lead->id)->count());
        $this->assertSame(1, $lead->activities()->count());

        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", ['target_stage' => 'won', 'stage_transition_key' => $key])
            ->assertSessionHasErrors('stage_transition_key');
        $this->assertSame('contacted', $lead->fresh()->status->stage_type->value);
        $this->assertSame(1, CrmLeadStageHistory::where('lead_id', $lead->id)->count());
        $this->assertSame(1, $lead->activities()->count());
    }

    public function test_actual_sales_are_periodized_from_invoice_and_finalized_credit_note_events(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $invoice = $this->invoice($manager, $lead, null, 10000, 'paid');
        $invoice->update(['issue_date' => '2026-01-31', 'credited_total' => '10000.00']);
        CrmInvoiceReturn::create([
            'company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'invoice_id' => $invoice->id,
            'credit_note_number' => 'CN-'.uniqid(), 'financial_year' => '2026-2027', 'issue_date' => '2026-02-01',
            'status' => CrmInvoiceReturn::STATUS_FINALIZED, 'currency' => 'INR', 'credit_total' => '2500.55',
            'reason_code' => 'returned', 'idempotency_key' => 'return-'.uniqid(), 'company_name_snapshot' => 'Test Co',
            'created_by' => $manager->id, 'finalized_by' => $manager->id, 'finalized_at' => '2026-02-01 00:00:00',
        ]);
        $service = app(CrmPipelineAnalyticsService::class);
        $january = $service->forecast($manager, ['from' => '2026-01-31', 'to' => '2026-01-31']);
        $february = $service->forecast($manager, ['from' => '2026-02-01', 'to' => '2026-02-01']);

        $this->assertSame(10000.0, $january['gross_recognized_sales_value']);
        $this->assertSame(0.0, $january['returns_credits_value']);
        $this->assertSame(10000.0, $january['actual_sales_value']);
        $this->assertSame(0.0, $february['gross_recognized_sales_value']);
        $this->assertSame(2500.55, $february['returns_credits_value']);
        $this->assertSame(-2500.55, $february['actual_sales_value']);
    }

    public function test_salesperson_transaction_period_sales_ignore_lead_cohort_and_match_forecast_owner_ai_and_csv(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['created_at' => '2025-12-15 12:00:00', 'updated_at' => '2025-12-15 12:00:00']);
        $invoice = $this->invoice($manager, $lead, null, 100000, 'paid');
        $invoice->update(['issue_date' => '2026-01-15']);
        CrmInvoiceReturn::create([
            'company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'invoice_id' => $invoice->id,
            'credit_note_number' => 'CN-'.uniqid(), 'financial_year' => '2025-2026', 'issue_date' => '2026-01-20',
            'status' => CrmInvoiceReturn::STATUS_FINALIZED, 'currency' => 'INR', 'credit_total' => '25000.00',
            'reason_code' => 'returned', 'idempotency_key' => 'return-'.uniqid(), 'company_name_snapshot' => 'Test Co',
            'created_by' => $manager->id, 'finalized_by' => $manager->id, 'finalized_at' => '2026-01-20 00:00:00',
        ]);
        $filters = ['from' => '2026-01-01', 'to' => '2026-01-31', 'branch_id' => $manager->branch_id];
        $analytics = app(CrmPipelineAnalyticsService::class);
        $forecast = $analytics->forecast($manager, $filters);
        $performance = $analytics->salespersonPerformance($manager, $filters)->firstWhere('salesperson_id', $manager->id);

        $this->assertSame(75000.0, $forecast['actual_sales_value']);
        $this->assertSame(0, $analytics->summary($manager, $filters)['total_leads']);
        $this->assertSame(75000.0, $performance['actual_sales_value']);
        $this->assertSame(1, $performance['invoices']);
        $this->actingAs($manager)->get('/reports?outlet_id='.$manager->branch_id.'&date_from=2026-01-01&date_to=2026-01-31')->assertOk()->assertSee('₹75,000.00');
        $ai = app(\App\Services\Ai\BusinessIntelligenceContextService::class)->forIntent($manager, 'crm_followup', ['label' => 'January', 'date_from' => '2026-01-01', 'date_to' => '2026-01-31'], $manager->branch_id);
        $this->assertSame(75000.0, collect($ai['facts'])->firstWhere('label', 'Actual sales')['value']);
        $csv = $this->actingAs($manager)->get('/crm/analytics/export/salesperson-performance?from=2026-01-01&to=2026-01-31')->assertOk()->streamedContent();
        $this->assertStringContainsString('75000', $csv);
    }

    public function test_reopening_a_lost_lead_preserves_history_and_clears_terminal_state(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $lost = $this->leadStatus($manager, 'Lost', 'lost', LeadStageType::Lost);
        $contacted = $this->leadStatus($manager, 'Contacted', 'contacted', LeadStageType::Contacted);
        $transitions = app(LeadStageTransitionService::class);

        $transitions->transition($lead, $lost->id, $manager, 'crm.pipeline.transitioned', true, ['lost_reason' => 'No decision']);
        $transitions->transition($lead->refresh(), $contacted->id, $manager, 'crm.pipeline.transitioned', true);

        $this->assertNull($lead->refresh()->lost_at);
        $this->assertNull($lead->refresh()->lost_reason);
        $this->assertDatabaseHas('crm_lead_stage_histories', ['lead_id' => $lead->id, 'event_type' => 'marked_lost']);
        $this->assertDatabaseHas('crm_lead_stage_histories', ['lead_id' => $lead->id, 'event_type' => 'reopened']);
    }

    public function test_generic_lead_edit_routes_status_changes_through_the_history_service(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $contacted = $this->leadStatus($manager, 'Contacted', 'contacted', LeadStageType::Contacted);

        $this->actingAs($manager)->put("/crm/leads/{$lead->id}", $this->leadPayload($lead, ['status_id' => $contacted->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('crm_lead_stage_histories', [
            'lead_id' => $lead->id,
            'from_status_id' => $lead->status_id,
            'to_status_id' => $contacted->id,
            'event_type' => 'stage_transition',
        ]);
    }

    public function test_analytics_excludes_won_and_lost_opportunities_from_forecast_and_respects_tenant_scope(): void
    {
        $manager = $this->user(UserRole::Manager);
        $other = $this->user(UserRole::Manager);
        $openLead = $this->lead($manager, ['expected_value' => 10000]);
        $wonLead = $this->lead($manager, ['title' => 'Won lead']);
        $otherLead = $this->lead($other, ['title' => 'Other tenant lead']);

        CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $openLead->id, 'title' => 'Open', 'stage' => 'negotiation', 'expected_value' => 10000, 'probability_percentage' => 60, 'currency' => 'INR']);
        CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $wonLead->id, 'title' => 'Won', 'stage' => 'won', 'expected_value' => 30000, 'probability_percentage' => 100, 'currency' => 'INR']);
        CrmOpportunity::create(['company_id' => $other->company_id, 'lead_id' => $otherLead->id, 'title' => 'Other', 'stage' => 'negotiation', 'expected_value' => 99000, 'probability_percentage' => 90, 'currency' => 'INR']);

        $summary = app(CrmPipelineAnalyticsService::class)->summary($manager);

        $this->assertSame('6000', $summary['forecast_weighted_value']);
        $this->assertSame(2, $summary['total_leads']);
        $this->assertSame(0, $summary['lost_count']);
    }

    public function test_forecast_moves_won_revenue_from_open_pipeline_to_recognized_actual_sales_once(): void
    {
        $manager = $this->user(UserRole::Manager);
        $leads = collect([100000 => 20, 200000 => 50, 300000 => 80])->map(function (int $probability, int $value) use ($manager): CrmLead {
            $lead = $this->lead($manager, ['expected_value' => $value]);
            CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'title' => "Opportunity {$value}", 'stage' => 'negotiation', 'expected_value' => $value, 'probability_percentage' => $probability, 'currency' => 'INR', 'assigned_user_id' => $manager->id]);
            return $lead;
        });
        $service = app(CrmPipelineAnalyticsService::class);
        $before = $service->forecast($manager);
        $this->assertSame(600000.0, $before['open_pipeline_value']);
        $this->assertSame(360000.0, $before['weighted_forecast']);

        $wonLead = $leads->get(300000);
        $opportunity = $wonLead->opportunities()->first();
        $opportunity->update(['stage' => 'won']);
        $this->invoice($manager, $wonLead, $opportunity, 300000, 'issued');
        $after = $service->forecast($manager);
        $this->assertSame(300000.0, $after['actual_sales_value']);
        $this->assertSame(300000.0, $after['open_pipeline_value']);
        $this->assertSame(120000.0, $after['weighted_forecast']);
        $this->assertSame(300000.0, $after['by_stage']->sum('open_value'));
        $this->assertSame(300000.0, $after['by_salesperson']->sum('open_value'));
        $this->assertSame(300000.0, $after['by_outlet']->sum('open_value'));
    }

    public function test_actual_sales_excludes_draft_cancelled_void_and_other_tenant_invoices(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        foreach (['draft' => 10000, 'cancelled' => 20000, 'void' => 30000, 'paid' => 40000] as $status => $value) $this->invoice($manager, $lead, null, $value, $status);
        $other = $this->user(UserRole::Manager);
        $this->invoice($other, $this->lead($other), null, 90000, 'paid');

        $this->assertSame(40000.0, app(CrmPipelineAnalyticsService::class)->forecast($manager)['actual_sales_value']);
    }

    public function test_salesperson_metrics_use_authorized_leads_and_recognized_invoice_value(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['next_follow_up_at' => now()->subDay()]);
        $won = $this->leadStatus($manager, 'Won', 'won', LeadStageType::Won);
        app(LeadStageTransitionService::class)->transition($lead, $won->id, $manager);
        $this->invoice($manager, $lead, null, 100000, 'paid');
        $metrics = app(CrmPipelineAnalyticsService::class)->salespersonPerformance($manager)->first();

        $this->assertSame($manager->id, $metrics['salesperson_id']);
        $this->assertSame(1, $metrics['leads_assigned']);
        $this->assertSame(1, $metrics['won_leads']);
        $this->assertSame(1, $metrics['invoices']);
        $this->assertSame(100000.0, $metrics['actual_sales_value']);
        $this->assertSame(100.0, $metrics['conversion_rate']);
    }

    public function test_direct_performance_and_forecast_urls_cannot_be_widened_by_tenant_or_outlet_parameters(): void
    {
        $manager = $this->user(UserRole::Manager);
        $otherOutlet = Branch::factory()->for($manager->company)->create();
        $outsideOutletLead = $this->lead($manager, ['branch_id' => $otherOutlet->id, 'title' => 'Outside outlet lead']);
        $otherTenant = $this->user(UserRole::Manager);
        $otherTenantLead = $this->lead($otherTenant, ['title' => 'Other tenant lead']);
        CrmOpportunity::create(['company_id' => $otherTenant->company_id, 'lead_id' => $otherTenantLead->id, 'title' => 'Other tenant opportunity', 'stage' => 'negotiation', 'expected_value' => 50000, 'probability_percentage' => 50, 'currency' => 'INR']);

        $this->actingAs($manager)->get('/crm/pipeline-analytics?branch_id='.$otherOutlet->id)->assertOk()->assertDontSee('Outside outlet lead')->assertDontSee('Other tenant opportunity');
        $this->actingAs($manager)->get('/crm/salesperson-performance?branch_id='.$otherOutlet->id.'&assigned_user_id='.$otherTenant->id)->assertOk()->assertDontSee('Other tenant lead');
        $this->assertEquals(0.0, app(CrmPipelineAnalyticsService::class)->forecast($manager, ['branch_id' => $otherOutlet->id])['open_pipeline_value']);
    }

    public function test_sales_role_cannot_expand_analytics_to_another_salespersons_leads(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Sales]);
        $managerLead = $this->lead($manager, ['title' => 'Manager only lead']);
        CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $managerLead->id, 'title' => 'Manager only opportunity', 'stage' => 'negotiation', 'expected_value' => 70000, 'probability_percentage' => 50, 'currency' => 'INR', 'assigned_user_id' => $manager->id]);

        $this->assertEquals(0.0, app(CrmPipelineAnalyticsService::class)->forecast($sales, ['assigned_user_id' => $manager->id])['open_pipeline_value']);
        $this->actingAs($sales)->get('/crm/salesperson-performance?assigned_user_id='.$manager->id)->assertOk()->assertDontSee('Manager only lead');
    }

    public function test_analytics_exports_use_the_shared_scoped_metrics_and_escape_formula_cells(): void
    {
        $manager = $this->user(UserRole::Manager);
        $manager->update(['name' => '=Formula Name']);
        $lead = $this->lead($manager, ['title' => 'Visible lead']);
        CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'title' => 'Visible opportunity', 'stage' => 'negotiation', 'expected_value' => 10000, 'probability_percentage' => 50, 'currency' => 'INR', 'assigned_user_id' => $manager->id]);
        $lost = $this->leadStatus($manager, 'Lost', 'lost', LeadStageType::Lost);
        CrmLeadStageHistory::create([
            'company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'lead_id' => $lead->id,
            'to_status_id' => $lost->id, 'to_stage' => 'lost', 'event_type' => 'marked_lost',
            'lost_reason_snapshot' => '=Do not execute', 'actor_id' => $manager->id, 'occurred_at' => now(),
        ]);
        $outsideOutlet = Branch::factory()->for($manager->company)->create();
        $hidden = $this->lead($manager, ['branch_id' => $outsideOutlet->id, 'title' => 'Hidden outlet lead']);
        CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $hidden->id, 'title' => 'Hidden', 'stage' => 'negotiation', 'expected_value' => 99999, 'probability_percentage' => 100, 'currency' => 'INR']);
        $outsideTenant = $this->user(UserRole::Manager);
        $outsideLead = $this->lead($outsideTenant, ['title' => 'Other tenant lead']);
        CrmOpportunity::create(['company_id' => $outsideTenant->company_id, 'lead_id' => $outsideLead->id, 'title' => 'Other tenant', 'stage' => 'negotiation', 'expected_value' => 99999, 'probability_percentage' => 100, 'currency' => 'INR']);

        foreach (['pipeline', 'funnel', 'lost-reasons', 'salesperson-performance'] as $export) {
            $response = $this->actingAs($manager)->get("/crm/analytics/export/{$export}?branch_id={$outsideOutlet->id}&assigned_user_id={$outsideTenant->id}&source_id=999999&from=2020-01-01&to=2030-01-01");
            $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $csv = $response->streamedContent();
            $this->assertStringNotContainsString('Hidden outlet lead', $csv);
            $this->assertStringNotContainsString('Other tenant lead', $csv);
        }

        $lostCsv = $this->actingAs($manager)->get('/crm/analytics/export/lost-reasons')->assertOk()->streamedContent();
        $salespersonCsv = $this->actingAs($manager)->get('/crm/analytics/export/salesperson-performance')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Do not execute", $lostCsv);
        $this->assertStringContainsString("'=Formula Name", $salespersonCsv);
        $this->actingAs($this->user(UserRole::Sales))->get('/crm/analytics/export/pipeline')->assertForbidden();
        $this->actingAs($manager)->get('/crm/analytics/export/not-an-export')->assertNotFound();
    }

    public function test_owner_command_center_uses_the_same_crm_actual_open_and_weighted_metrics(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['next_follow_up_at' => now()->subDay()]);
        $opportunity = CrmOpportunity::create(['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'title' => 'Owner metric opportunity', 'stage' => 'negotiation', 'expected_value' => 10000, 'probability_percentage' => 50, 'currency' => 'INR', 'assigned_user_id' => $manager->id]);
        $this->invoice($manager, $lead, null, 1200, 'issued');

        $forecast = app(CrmPipelineAnalyticsService::class)->forecast($manager, ['branch_id' => $manager->branch_id]);
        $this->assertSame(1200.0, $forecast['actual_sales_value']);
        $this->assertSame(10000.0, $forecast['open_pipeline_value']);
        $this->assertSame(5000.0, $forecast['weighted_forecast']);

        $this->actingAs($manager)->get('/reports?outlet_id='.$manager->branch_id.'&date_from='.today()->toDateString().'&date_to='.today()->toDateString())
            ->assertOk()->assertSee('CRM signals')->assertSee('Actual Sales')->assertSee('₹1,200.00')->assertSee('Open Pipeline')->assertSee('₹10,000.00')->assertSee('Weighted Forecast')->assertSee('₹5,000.00');
    }

    public function test_lead_queries_reject_a_lead_from_an_unassigned_outlet(): void
    {
        $manager = $this->user(UserRole::Manager);
        $otherOutlet = Branch::factory()->for($manager->company)->create();
        $outsideScope = $this->lead($manager, ['branch_id' => $otherOutlet->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        app(LeadRepository::class)->findForUser($manager, $outsideScope->id);
    }

    public function test_branchless_leads_require_explicit_relationship_for_outlet_restricted_users(): void
    {
        $manager = $this->user(UserRole::Manager);
        $otherUser = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Manager]);
        $arbitraryLegacyLead = $this->lead($manager, ['branch_id' => null, 'assigned_user_id' => $otherUser->id, 'created_by' => $otherUser->id]);
        $ownedLegacyLead = $this->lead($manager, ['branch_id' => null, 'assigned_user_id' => $manager->id, 'created_by' => $manager->id]);

        $this->assertFalse(app(LeadRepository::class)->queryForUser($manager)->whereKey($arbitraryLegacyLead->id)->exists());
        $this->assertTrue(app(LeadRepository::class)->queryForUser($manager)->whereKey($ownedLegacyLead->id)->exists());
    }

    public function test_administrator_can_read_company_branchless_leads_without_cross_tenant_leakage(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sameCompanyLegacyLead = $this->lead($administrator, ['branch_id' => null]);
        $otherTenant = $this->user(UserRole::Administrator);
        $otherTenantLead = $this->lead($otherTenant, ['branch_id' => null]);

        $query = app(LeadRepository::class)->queryForUser($administrator);

        $this->assertTrue((clone $query)->whereKey($sameCompanyLegacyLead->id)->exists());
        $this->assertFalse((clone $query)->whereKey($otherTenantLead->id)->exists());
    }

    public function test_stalled_leads_are_derived_from_overdue_follow_ups_and_exclude_terminal_leads(): void
    {
        $manager = $this->user(UserRole::Manager);
        $stalled = $this->lead($manager, ['next_follow_up_at' => now()->subDay()]);
        $won = $this->lead($manager, ['title' => 'Won', 'next_follow_up_at' => now()->subDay()]);
        $wonStatus = $this->leadStatus($manager, 'Won', 'won', LeadStageType::Won);
        app(LeadStageTransitionService::class)->transition($won, $wonStatus->id, $manager);

        $results = app(CrmPipelineAnalyticsService::class)->stalledLeads($manager);

        $this->assertTrue($results->contains(fn (array $row) => $row['lead']->id === $stalled->id && $row['reason'] === 'Follow-up is overdue'));
        $this->assertFalse($results->contains(fn (array $row) => $row['lead']->id === $won->id));
    }

    public function test_reopened_lead_uses_the_new_sales_cycle_as_its_stalled_baseline(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['created_at' => now()->subMonth(), 'updated_at' => now()->subMonth()]);
        $lost = $this->leadStatus($manager, 'Lost', 'lost', LeadStageType::Lost);
        $contacted = $this->leadStatus($manager, 'Contacted', 'contacted', LeadStageType::Contacted);
        $service = app(LeadStageTransitionService::class);
        $service->transition($lead, $lost->id, $manager);
        $service->transition($lead->refresh(), $contacted->id, $manager);

        $this->assertFalse(app(CrmPipelineAnalyticsService::class)->stalledLeads($manager)->contains(fn (array $row) => $row['lead']->id === $lead->id));
    }

    private function lead(User $user, array $overrides = []): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website-contact'], ['name' => 'Website Contact', 'is_active' => true]);
        $status = $this->leadStatus($user, 'New', 'new', LeadStageType::New);

        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Pipeline lead',
            'currency' => 'INR',
            'priority' => LeadPriority::Medium,
        ], $overrides));
    }

    private function leadStatus(User $user, string $name, string $slug, LeadStageType $stage): CrmLeadStatus
    {
        return CrmLeadStatus::firstOrCreate(
            ['company_id' => $user->company_id, 'slug' => $slug],
            ['name' => $name, 'stage_type' => $stage, 'is_won' => $stage === LeadStageType::Won, 'is_lost' => $stage === LeadStageType::Lost, 'is_active' => true, 'sort_order' => CrmLeadStatus::query()->where('company_id', $user->company_id)->count() + 1],
        );
    }

    private function invoice(User $user, CrmLead $lead, ?CrmOpportunity $opportunity, int $total, string $status): CrmInvoice
    {
        return CrmInvoice::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'lead_id' => $lead->id, 'opportunity_id' => $opportunity?->id, 'invoice_number' => 'INV-'.uniqid(), 'currency' => 'INR', 'grand_total' => $total, 'balance_due' => $total, 'status' => $status, 'issue_date' => today(), 'created_by' => $user->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function leadPayload(CrmLead $lead, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $lead->branch_id,
            'source_id' => $lead->source_id,
            'status_id' => $lead->status_id,
            'assigned_user_id' => $lead->assigned_user_id,
            'title' => $lead->title,
            'currency' => 'INR',
            'priority' => 'medium',
        ], $overrides);
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
