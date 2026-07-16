<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\ProformaStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmPremiumPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_view_the_complete_board_and_list_pipeline(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['title' => 'Northstar Retail Opportunity']);

        $this->actingAs($manager)->get('/crm/pipeline')
            ->assertOk()
            ->assertSee('Northstar Retail Opportunity')
            ->assertSee('New Lead')
            ->assertSee('Partially Paid')
            ->assertSee('Move stage');

        $this->actingAs($manager)->get('/crm/pipeline?view=list')
            ->assertOk()
            ->assertSee('Northstar Retail Opportunity')
            ->assertSee('Stage');

        $this->assertDatabaseHas('crm_lead_statuses', [
            'company_id' => $manager->company_id,
            'stage_type' => LeadStageType::ProformaSent->value,
        ]);
    }

    public function test_pipeline_card_move_updates_status_activity_audit_and_selected_notifications(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $lead = $this->lead($manager, ['assigned_user_id' => $sales->id]);

        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", [
            'target_stage' => 'not-a-stage',
        ])->assertSessionHasErrors('target_stage');

        $this->actingAs($manager)->postJson("/crm/pipeline/cards/{$lead->id}/move", [
            'target_stage' => 'proposal_sent',
        ])->assertOk()->assertJsonPath('stage', 'proposal_sent');

        $this->assertSame(LeadStageType::Proposal, $lead->refresh()->status->stage_type);
        $this->assertDatabaseHas('crm_activities', [
            'crm_lead_id' => $lead->id,
            'subject' => 'Pipeline moved from New Lead to Proposal Sent',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'crm.pipeline.transitioned',
            'auditable_id' => $lead->id,
        ]);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.pipeline.stage_changed')->exists());
        $this->assertDatabaseCount('crm_customers', 0);
        $this->assertDatabaseCount('crm_quotations', 0);
        $this->assertDatabaseCount('crm_proforma_invoices', 0);
    }

    public function test_partial_proforma_payment_is_projected_to_the_partial_payment_stage(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['title' => 'Aster Clinics']);
        $proforma = $this->proforma($manager, $lead, [
            'status' => ProformaStatus::PartiallyPaid,
            'paid_amount' => 25000,
            'balance_amount' => 15000,
        ]);

        $this->actingAs($manager)->get('/crm/pipeline')
            ->assertOk()
            ->assertSee('Aster Clinics')
            ->assertSee('Partially Paid')
            ->assertSee($proforma->proforma_number);
    }

    public function test_pipeline_filters_apply_and_staff_is_not_authorized(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $visible = $this->lead($manager, ['title' => 'Searchable Group', 'next_follow_up_at' => now()->subDay()]);
        $this->lead($manager, ['title' => 'Another Group']);

        $this->actingAs($manager)->get('/crm/pipeline?search=Searchable&follow_up=overdue')
            ->assertOk()
            ->assertSee('Searchable Group')
            ->assertDontSee('Another Group');

        $this->actingAs($staff)->get('/crm/pipeline')->assertForbidden();
        $this->actingAs($staff)->postJson("/crm/pipeline/cards/{$visible->id}/move", ['target_stage' => 'won'])->assertForbidden();
    }

    public function test_lost_move_preserves_existing_quotation_and_proforma_records(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager, ['title' => 'Preserved Commercial History']);
        $quotation = CrmQuotation::create([
            'company_id' => $manager->company_id,
            'lead_id' => $lead->id,
            'quotation_number' => 'RPQ-'.now()->format('Y').'-000001',
            'title' => 'Commercial Proposal',
            'currency' => 'INR',
            'grand_total' => 40000,
            'created_by' => $manager->id,
        ]);
        $proforma = $this->proforma($manager, $lead, ['quotation_id' => $quotation->id]);

        $this->actingAs($manager)->post("/crm/pipeline/cards/{$lead->id}/move", ['target_stage' => 'lost'])
            ->assertRedirect();

        $this->assertSame(LeadStageType::Lost, $lead->refresh()->status->stage_type);
        $this->assertNotNull($lead->lost_at);
        $this->assertDatabaseHas('crm_quotations', ['id' => $quotation->id]);
        $this->assertDatabaseHas('crm_proforma_invoices', ['id' => $proforma->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function lead(User $user, array $overrides = []): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(
            ['company_id' => $user->company_id, 'slug' => 'website-contact'],
            ['name' => 'Website Contact', 'is_active' => true],
        );
        $status = CrmLeadStatus::firstOrCreate(
            ['company_id' => $user->company_id, 'slug' => 'new'],
            ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'sort_order' => 1],
        );

        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Pipeline Opportunity',
            'business_name' => 'Retail Group',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'currency' => 'INR',
            'expected_value' => 40000,
            'priority' => LeadPriority::Medium,
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides */
    private function proforma(User $user, CrmLead $lead, array $overrides = []): CrmProformaInvoice
    {
        return CrmProformaInvoice::create(array_merge([
            'company_id' => $user->company_id,
            'lead_id' => $lead->id,
            'proforma_number' => 'RPI-'.now()->format('Y').'-'.str_pad((string) (CrmProformaInvoice::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'title' => 'RetailPOS Command Center',
            'customer_name' => 'Asha Mehta',
            'customer_company' => 'Retail Group',
            'currency' => 'INR',
            'subtotal' => 40000,
            'grand_total' => 40000,
            'paid_amount' => 0,
            'balance_amount' => 40000,
            'invoice_date' => today(),
            'status' => ProformaStatus::Sent,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
