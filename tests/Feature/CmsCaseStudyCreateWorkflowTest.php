<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsMedia;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsCaseStudyCreateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_can_open_the_case_study_create_screen_with_media_options(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        CmsMedia::create(['company_id' => $administrator->company_id, 'name' => 'Retail rollout cover', 'file_name' => 'retail-rollout-cover.png', 'disk' => 'public', 'path' => 'cms/retail-rollout-cover.png', 'type' => 'image', 'size' => 1024]);

        $this->actingAs($administrator)
            ->get('/cms/case-studies/create')
            ->assertOk()
            ->assertSee('New Case Study')
            ->assertSee('Retail rollout cover')
            ->assertSee('Create case study');
    }

    public function test_unauthorized_staff_member_cannot_open_the_case_study_create_screen(): void
    {
        $staff = $this->user(UserRole::Staff);

        $this->actingAs($staff)->get('/cms/case-studies/create')->assertForbidden();
    }

    public function test_dashboard_new_case_study_button_targets_a_registered_create_route(): void
    {
        $administrator = $this->user(UserRole::Administrator);

        $this->assertSame(url('/cms/case-studies/create'), route('cms.case-studies.create'));
        $this->actingAs($administrator)->get('/cms')->assertOk()->assertSee('/cms/case-studies/create', false);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->for(Company::factory())->create(['role' => $role]);
    }
}
