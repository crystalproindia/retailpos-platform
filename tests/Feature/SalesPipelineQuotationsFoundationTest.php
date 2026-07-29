<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\Crm\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesPipelineQuotationsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_an_opportunity_and_its_weighted_value_is_available(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);

        $this->actingAs($manager)->post("/sales/leads/{$lead->id}/opportunities", [
            'title' => 'Retail rollout', 'stage' => 'qualified', 'expected_value' => 120000,
            'currency' => 'INR', 'probability_percentage' => 35,
        ])->assertRedirect('/sales/opportunities');

        $this->assertDatabaseHas('crm_opportunities', ['company_id' => $manager->company_id, 'lead_id' => $lead->id, 'title' => 'Retail rollout']);
        $this->assertDatabaseHas('crm_opportunity_stage_histories', ['to_stage' => 'qualified']);
        $this->actingAs($manager)->get('/sales/opportunities')->assertOk()->assertSee('42,000.00');
    }

    public function test_follow_up_completion_and_cancellation_are_tenant_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);

        $this->actingAs($manager)->post('/crm/activities', [
            'crm_lead_id' => $lead->id, 'type' => 'follow_up', 'subject' => 'Call back', 'priority' => 'high',
            'scheduled_at' => now()->subHour()->toDateTimeString(),
        ])->assertRedirect();
        $this->actingAs($manager)->get('/crm/follow-ups?overdue=1')->assertOk()->assertSee('Call back');
        $activityId = (int) $lead->activities()->firstOrFail()->id;
        $this->actingAs($manager)->post("/crm/activities/{$activityId}/complete", ['outcome' => 'Reached customer'])->assertRedirect();
        $this->assertDatabaseHas('crm_activities', ['id' => $activityId, 'follow_up_status' => 'completed', 'completed_by' => $manager->id]);

        $this->actingAs($manager)->post('/crm/activities', [
            'crm_lead_id' => $lead->id, 'type' => 'call', 'subject' => 'Reschedule', 'priority' => 'normal',
        ])->assertRedirect();
        $cancelId = (int) $lead->activities()->latest('id')->firstOrFail()->id;
        $this->actingAs($manager)->post("/crm/activities/{$cancelId}/cancel", ['outcome' => 'No longer needed'])->assertRedirect();
        $this->assertDatabaseHas('crm_activities', ['id' => $cancelId, 'follow_up_status' => 'cancelled', 'cancelled_by' => $manager->id]);
    }

    public function test_public_quotation_token_is_hashed_and_customer_can_accept_once(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $quotation = CrmQuotation::create([
            'company_id' => $manager->company_id, 'lead_id' => $lead->id, 'quotation_number' => 'RPOS-'.now()->format('Y').'-00001',
            'title' => 'RetailPOS proposal', 'currency' => 'INR', 'grand_total' => 120000, 'status' => QuotationStatus::Sent,
            'valid_until' => now()->addWeek(), 'created_by' => $manager->id,
        ]);
        $link = app(QuotationService::class)->issuePublicLink($quotation, $manager);
        $token = basename(parse_url($link->url, PHP_URL_PATH));

        $this->assertNull($quotation->refresh()->public_token);
        $this->assertNull($quotation->public_url);
        $this->assertSame(hash('sha256', $token), $quotation->public_token_hash);
        $this->get('/q/'.$token)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->post('/q/'.$token.'/decision', ['decision' => 'accepted', 'name' => 'Asha Mehta', 'confirm' => 1])->assertRedirect('/q/'.$token);
        $this->assertSame(QuotationStatus::Accepted, $quotation->refresh()->status);
        $this->post('/q/'.$token.'/decision', ['decision' => 'rejected', 'name' => 'Asha Mehta', 'rejection_reason' => 'Changed plans', 'confirm' => 1])->assertSessionHasErrors('quotation');
    }

    public function test_accepted_quotation_creates_an_immutable_draft_revision(): void
    {
        $manager = $this->user(UserRole::Manager);
        $lead = $this->lead($manager);
        $quotation = CrmQuotation::create([
            'company_id' => $manager->company_id, 'lead_id' => $lead->id, 'quotation_number' => 'RPOS-'.now()->format('Y').'-00002',
            'title' => 'Original proposal', 'currency' => 'INR', 'status' => QuotationStatus::Accepted,
            'accepted_at' => now(), 'created_by' => $manager->id,
        ]);
        $quotation->items()->create(['name' => 'Implementation', 'quantity' => 1, 'unit_price' => 1000, 'line_total' => 1000, 'sort_order' => 1]);

        $this->actingAs($manager)->post("/crm/quotations/{$quotation->id}/revision")->assertRedirect();

        $revision = CrmQuotation::query()->where('parent_quotation_id', $quotation->id)->firstOrFail();
        $this->assertSame(QuotationStatus::Draft, $revision->status);
        $this->assertSame(2, $revision->version_number);
        $this->assertSame('Implementation', $revision->items()->firstOrFail()->name);
        $this->assertSame(QuotationStatus::Accepted, $quotation->refresh()->status);
    }

    private function lead(User $user): CrmLead
    {
        $source = CrmLeadSource::create(['company_id' => $user->company_id, 'name' => 'Website', 'slug' => 'website', 'is_active' => true]);
        $status = CrmLeadStatus::create(['company_id' => $user->company_id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New, 'is_active' => true]);

        return CrmLead::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'source_id' => $source->id, 'status_id' => $status->id, 'assigned_user_id' => $user->id, 'created_by' => $user->id, 'title' => 'Retail rollout', 'priority' => LeadPriority::Medium]);
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}
