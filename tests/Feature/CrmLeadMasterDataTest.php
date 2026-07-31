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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmLeadMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_roles_can_manage_tenant_scoped_statuses_and_sources(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->post('/crm/settings/lead-statuses', $this->statusPayload('Discovery', true))
            ->assertRedirect(route('crm.settings.statuses.index'));
        $this->actingAs($manager)->post('/crm/settings/lead-sources', $this->sourcePayload('Partner Referral', true))
            ->assertRedirect(route('crm.settings.sources.index'));

        $status = CrmLeadStatus::query()->where('company_id', $manager->company_id)->where('name', 'Discovery')->firstOrFail();
        $source = CrmLeadSource::query()->where('company_id', $manager->company_id)->where('name', 'Partner Referral')->firstOrFail();

        $this->assertTrue($status->is_default);
        $this->assertTrue($source->is_default);
        $this->assertSame(1, CrmLeadStatus::query()->where('company_id', $manager->company_id)->where('is_default', true)->count());
        $this->assertSame(1, CrmLeadSource::query()->where('company_id', $manager->company_id)->where('is_default', true)->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead_status.created', 'company_id' => $manager->company_id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead_source.created', 'company_id' => $manager->company_id]);
    }

    public function test_sales_cannot_manage_master_data_or_access_another_tenant_record(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $other = $this->user(UserRole::Manager);
        $foreign = $this->leadStatus($other, 'Foreign');

        $this->actingAs($sales)->get('/crm/settings/lead-statuses')->assertForbidden();
        $this->actingAs($manager)->get('/crm/settings/lead-statuses/'.$foreign->id.'/edit')->assertNotFound();
    }

    public function test_statuses_and_sources_can_be_reordered_deactivated_and_protected_when_in_use(): void
    {
        $manager = $this->user(UserRole::Manager);
        $new = $this->leadStatus($manager, 'New', true);
        $followUp = $this->leadStatus($manager, 'Follow up');
        $source = $this->source($manager, 'Website');

        $this->actingAs($manager)->patch('/crm/settings/lead-statuses/reorder', ['ids' => [$followUp->id, $new->id]])->assertRedirect();
        $this->assertSame(1, $followUp->refresh()->sort_order);
        $this->actingAs($manager)->post('/crm/settings/lead-statuses/'.$new->id.'/toggle')->assertRedirect();
        $this->assertFalse($new->refresh()->is_default);
        $this->assertTrue($followUp->refresh()->is_default);

        CrmLead::create(['company_id' => $manager->company_id, 'branch_id' => $manager->branch_id, 'status_id' => $followUp->id, 'source_id' => $source->id, 'assigned_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Existing lead', 'priority' => LeadPriority::Medium]);
        $this->actingAs($manager)->delete('/crm/settings/lead-statuses/'.$followUp->id)->assertSessionHasErrors('status');
        $this->actingAs($manager)->delete('/crm/settings/lead-sources/'.$source->id)->assertSessionHasErrors('source');
    }

    public function test_lead_form_uses_active_default_records_and_rejects_inactive_selection(): void
    {
        $manager = $this->user(UserRole::Manager);
        $default = $this->leadStatus($manager, 'New', true);
        $inactive = $this->leadStatus($manager, 'Retired');
        $inactive->update(['is_active' => false]);
        $source = $this->source($manager, 'Manual entry', true);

        $this->actingAs($manager)->get('/crm/leads/create')->assertOk()
            ->assertSee('value="'.$default->id.'" selected', false)
            ->assertSee('value="'.$source->id.'" selected', false)
            ->assertDontSee('Retired');
        $this->actingAs($manager)->post('/crm/leads', ['title' => 'Rejected inactive status', 'status_id' => $inactive->id, 'currency' => 'INR', 'priority' => 'medium'])
            ->assertSessionHasErrors('status_id');
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    private function leadStatus(User $user, string $name, bool $default = false): CrmLeadStatus
    {
        return CrmLeadStatus::create(['company_id' => $user->company_id, 'name' => $name, 'slug' => str($name)->slug(), 'stage_type' => LeadStageType::New, 'tone' => 'neutral', 'probability' => 10, 'is_active' => true, 'is_default' => $default, 'sort_order' => CrmLeadStatus::query()->where('company_id', $user->company_id)->count() + 1]);
    }

    private function source(User $user, string $name, bool $default = false): CrmLeadSource
    {
        return CrmLeadSource::create(['company_id' => $user->company_id, 'name' => $name, 'slug' => str($name)->slug(), 'tone' => 'neutral', 'is_active' => true, 'is_default' => $default, 'sort_order' => CrmLeadSource::query()->where('company_id', $user->company_id)->count() + 1]);
    }

    /** @return array<string, mixed> */
    private function statusPayload(string $name, bool $default): array
    {
        return ['name' => $name, 'stage_type' => LeadStageType::New->value, 'tone' => 'neutral', 'probability' => 15, 'is_default' => $default];
    }

    /** @return array<string, mixed> */
    private function sourcePayload(string $name, bool $default): array
    {
        return ['name' => $name, 'description' => 'A customer referral source.', 'tone' => 'neutral', 'is_default' => $default];
    }
}
