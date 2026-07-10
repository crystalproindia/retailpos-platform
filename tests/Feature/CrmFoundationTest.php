<?php

namespace Tests\Feature;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\PreferredContactMethod;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmTag;
use App\Models\User;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_is_registered_with_child_modules_and_sales_visibility(): void
    {
        $registry = new ModuleRegistry;

        $crm = $registry->find('crm');
        $salesSidebar = $registry->sidebar(UserRole::Sales);
        $staffSidebar = $registry->sidebar(UserRole::Staff);
        $salesCrm = $salesSidebar->firstWhere('id', 'crm');

        $this->assertSame('crm.dashboard', $crm->route);
        $this->assertTrue($salesSidebar->contains('id', 'crm'));
        $this->assertFalse($staffSidebar->contains('id', 'crm'));
        $this->assertContains('leads', collect($salesCrm->children)->pluck('id'));
        $this->assertContains('crm-pipeline', collect($salesCrm->children)->pluck('id'));
    }

    public function test_crm_access_is_limited_by_role(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales);
        $staff = $this->user(UserRole::Staff);

        $this->actingAs($administrator)->get('/crm')->assertOk();
        $this->actingAs($manager)->get('/crm')->assertOk();
        $this->actingAs($sales)->get('/crm')->assertOk();
        $this->actingAs($staff)->get('/crm')->assertForbidden();
    }

    public function test_lead_crud_search_filters_notes_bulk_actions_and_audit_log(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);

        $this->actingAs($manager)
            ->post('/crm/leads', $this->leadPayload($fixtures, [
                'assigned_user_id' => $sales->id,
                'title' => 'Enterprise CRM Discovery',
                'business_name' => 'Demo Retail Group',
            ]))
            ->assertRedirect();

        $lead = CrmLead::query()->where('title', 'Enterprise CRM Discovery')->firstOrFail();

        $this->actingAs($manager)
            ->get('/crm/leads?search=Discovery&status_id='.$fixtures['new']->id)
            ->assertOk()
            ->assertSee('Enterprise CRM Discovery');

        $this->actingAs($manager)
            ->put("/crm/leads/{$lead->id}", $this->leadPayload($fixtures, [
                'assigned_user_id' => $sales->id,
                'title' => 'Updated CRM Discovery',
                'status_id' => $fixtures['qualified']->id,
            ]))
            ->assertRedirect();

        $this->assertSame('Updated CRM Discovery', $lead->refresh()->title);
        $this->assertSame($fixtures['qualified']->id, $lead->status_id);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/notes", ['body' => 'Discovery call completed.'])
            ->assertRedirect();

        $this->assertDatabaseHas('crm_notes', [
            'notable_type' => $lead->getMorphClass(),
            'notable_id' => $lead->id,
            'body' => 'Discovery call completed.',
        ]);

        $this->actingAs($manager)
            ->post('/crm/leads/bulk', [
                'action' => 'status',
                'ids' => [$lead->id],
                'status_id' => $fixtures['proposal']->id,
            ])
            ->assertRedirect();

        $this->assertSame($fixtures['proposal']->id, $lead->refresh()->status_id);

        $this->actingAs($manager)
            ->post('/crm/leads/bulk', [
                'action' => 'assign',
                'ids' => [$lead->id],
                'assigned_user_id' => $manager->id,
            ])
            ->assertRedirect();

        $this->assertSame($manager->id, $lead->refresh()->assigned_user_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.note_added']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.bulk_assigned']);
    }

    public function test_sales_user_only_sees_assigned_or_created_leads_and_tenant_isolation_blocks_other_companies(): void
    {
        $sales = $this->user(UserRole::Sales);
        $fixtures = $this->crmFixtures($sales);
        $assignedLead = $this->lead($sales, $fixtures, ['title' => 'Assigned Lead', 'assigned_user_id' => $sales->id]);
        $otherOwner = $this->user(UserRole::Sales, $sales->company, $sales->branch);
        $hiddenLead = $this->lead($sales, $fixtures, ['title' => 'Hidden Lead', 'assigned_user_id' => $otherOwner->id, 'created_by' => $otherOwner->id]);
        $outsideUser = $this->user(UserRole::Manager);

        $this->actingAs($sales)
            ->get('/crm/leads')
            ->assertOk()
            ->assertSee('Assigned Lead')
            ->assertDontSee('Hidden Lead');

        $this->actingAs($sales)
            ->get("/crm/leads/{$hiddenLead->id}")
            ->assertNotFound();

        $this->actingAs($outsideUser)
            ->get("/crm/leads/{$assignedLead->id}")
            ->assertNotFound();
    }

    public function test_pipeline_grouping_and_status_transition(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $lead = $this->lead($manager, $fixtures, ['title' => 'Pipeline Lead']);

        $this->actingAs($manager)
            ->get('/crm/pipeline')
            ->assertOk()
            ->assertSee('Pipeline Lead')
            ->assertSee('New');

        $this->actingAs($manager)
            ->patch("/crm/pipeline/{$lead->id}", ['status_id' => $fixtures['qualified']->id])
            ->assertRedirect();

        $this->assertSame($fixtures['qualified']->id, $lead->refresh()->status_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.pipeline.transitioned']);
    }

    public function test_activities_can_be_created_and_completed(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $this->actingAs($manager)
            ->post('/crm/activities', [
                'crm_lead_id' => $lead->id,
                'type' => ActivityType::Call->value,
                'subject' => 'Introductory call',
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'priority' => LeadPriority::High->value,
            ])
            ->assertRedirect();

        $activity = CrmActivity::query()->where('subject', 'Introductory call')->firstOrFail();

        $this->actingAs($manager)
            ->post("/crm/activities/{$activity->id}/complete", ['outcome' => 'Interested in demo'])
            ->assertRedirect();

        $this->assertNotNull($activity->refresh()->completed_at);
        $this->assertSame('Interested in demo', $activity->outcome);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.activity.completed']);
    }

    public function test_companies_and_contacts_support_crud_soft_delete_and_restore(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);

        $this->actingAs($manager)
            ->post('/crm/companies', [
                'name' => 'Acme Retail',
                'industry' => 'Fashion',
                'email' => 'hello@acme.test',
                'phone' => '+91 90000 33333',
                'assigned_user_id' => $manager->id,
                'is_active' => '1',
                'tag_ids' => [$fixtures['tag']->id],
            ])
            ->assertRedirect();

        $crmCompany = CrmCompany::query()->where('name', 'Acme Retail')->firstOrFail();

        $this->actingAs($manager)
            ->post('/crm/contacts', [
                'crm_company_id' => $crmCompany->id,
                'first_name' => 'Priya',
                'last_name' => 'Rao',
                'email' => 'priya@acme.test',
                'phone' => '+91 90000 44444',
                'preferred_contact_method' => PreferredContactMethod::Email->value,
                'assigned_user_id' => $manager->id,
                'is_primary' => '1',
                'tag_ids' => [$fixtures['tag']->id],
            ])
            ->assertRedirect();

        $contact = CrmContact::query()->where('email', 'priya@acme.test')->firstOrFail();

        $this->actingAs($manager)->delete("/crm/contacts/{$contact->id}")->assertRedirect();
        $this->assertSoftDeleted('crm_contacts', ['id' => $contact->id]);

        $this->actingAs($manager)->post("/crm/contacts/{$contact->id}/restore")->assertRedirect();
        $this->assertFalse($contact->refresh()->trashed());

        $this->actingAs($manager)->delete("/crm/companies/{$crmCompany->id}")->assertRedirect();
        $this->assertSoftDeleted('crm_companies', ['id' => $crmCompany->id]);

        $this->actingAs($manager)->post("/crm/companies/{$crmCompany->id}/restore")->assertRedirect();
        $this->assertFalse($crmCompany->refresh()->trashed());
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.company.created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.contact.created']);
    }

    public function test_lead_conversion_creates_company_contact_and_preserves_lead_history(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $lead = $this->lead($manager, $fixtures, [
            'title' => 'Conversion Lead',
            'status_id' => $fixtures['qualified']->id,
            'business_name' => 'Conversion Retail',
            'contact_name' => 'Asha Menon',
            'email' => 'asha@conversion.test',
        ]);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/convert")
            ->assertRedirect();

        $lead->refresh();

        $this->assertNotNull($lead->converted_at);
        $this->assertSame($fixtures['won']->id, $lead->status_id);
        $this->assertNotNull($lead->crm_company_id);
        $this->assertNotNull($lead->crm_contact_id);
        $this->assertDatabaseHas('crm_companies', ['name' => 'Conversion Retail']);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'asha@conversion.test']);
        $this->assertDatabaseHas('crm_notes', ['body' => 'Lead converted to CRM company and contact.']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.converted']);
    }

    public function test_validation_failures_and_unauthorized_actions_are_blocked(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $lead = $this->lead($manager, $fixtures, ['assigned_user_id' => $sales->id]);

        $this->actingAs($manager)
            ->from('/crm/leads/create')
            ->post('/crm/leads', [
                'status_id' => $fixtures['new']->id,
                'priority' => LeadPriority::Medium->value,
                'currency' => 'INR',
            ])
            ->assertRedirect('/crm/leads/create')
            ->assertSessionHasErrors('title');

        $this->actingAs($sales)
            ->delete("/crm/leads/{$lead->id}")
            ->assertForbidden();
    }

    public function test_database_seeder_creates_crm_foundation_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'sales@retailpos.test',
            'role' => UserRole::Sales->value,
        ]);
        $this->assertDatabaseHas('crm_lead_sources', ['slug' => 'website-demo']);
        $this->assertDatabaseHas('crm_lead_statuses', ['slug' => 'qualified']);
        $this->assertDatabaseCount('crm_leads', 20);
        $this->assertDatabaseHas('crm_activities', ['type' => ActivityType::Call->value]);
    }

    /**
     * @return array<string, mixed>
     */
    private function crmFixtures(User $user): array
    {
        $source = CrmLeadSource::create([
            'company_id' => $user->company_id,
            'name' => 'Website Demo',
            'slug' => 'website-demo',
            'is_active' => true,
        ]);

        $new = $this->crmStatus($user, 'New', LeadStageType::New, 1);
        $qualified = $this->crmStatus($user, 'Qualified', LeadStageType::Qualified, 2);
        $proposal = $this->crmStatus($user, 'Proposal', LeadStageType::Proposal, 3);
        $won = $this->crmStatus($user, 'Won', LeadStageType::Won, 4, true);
        $tag = CrmTag::create([
            'company_id' => $user->company_id,
            'name' => 'Hot Lead',
            'slug' => 'hot-lead',
            'is_active' => true,
        ]);

        return compact('source', 'new', 'qualified', 'proposal', 'won', 'tag');
    }

    private function crmStatus(User $user, string $name, LeadStageType $stage, int $sortOrder, bool $isWon = false): CrmLeadStatus
    {
        return CrmLeadStatus::create([
            'company_id' => $user->company_id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'stage_type' => $stage->value,
            'probability' => $isWon ? 100 : 25,
            'is_won' => $isWon,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lead(User $user, array $fixtures, array $overrides = []): CrmLead
    {
        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['new']->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'CRM Demo Lead',
            'business_name' => 'Demo Account',
            'contact_name' => 'Demo Contact',
            'email' => 'demo@example.test',
            'phone' => '+91 90000 11111',
            'expected_value' => 125000,
            'currency' => 'INR',
            'priority' => LeadPriority::Medium->value,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(array $fixtures, array $overrides = []): array
    {
        return array_merge([
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['new']->id,
            'title' => 'CRM Demo Lead',
            'business_name' => 'Demo Account',
            'contact_name' => 'Demo Contact',
            'email' => 'demo@example.test',
            'phone' => '+91 90000 11111',
            'expected_value' => 125000,
            'currency' => 'INR',
            'priority' => LeadPriority::Medium->value,
            'lead_score' => 70,
            'next_follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }
}
