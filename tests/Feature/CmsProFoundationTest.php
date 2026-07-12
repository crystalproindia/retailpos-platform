<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsClientLogo;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsProFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_branding_theme_and_footer_builder(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->put('/cms/branding', [
            'brand_name' => 'RetailPOS Pro',
            'brand_tagline' => 'Connected retail operations',
            'primary_brand_color' => '#0f766e',
            'secondary_brand_color' => '#1e293b',
            'accent_brand_color' => '#f59e0b',
            'button_style' => 'rounded',
            'default_cta_text' => 'Book a demo',
            'default_cta_link' => 'https://example.test/contact',
        ])->assertRedirect();

        $this->actingAs($manager)->put('/cms/theme', [
            'primary_color' => '#0f766e',
            'website_theme_mode' => 'clean_light',
            'button_radius_style' => 'rounded',
            'card_radius_style' => 'soft',
        ])->assertRedirect();

        $this->actingAs($manager)->put('/cms/footer', [
            'company_name' => 'RetailPOS Pro',
            'india_contact' => 'Bengaluru, India',
            'singapore_contact' => 'Singapore',
            'malaysia_contact' => 'Malaysia',
            'bahrain_contact' => 'Bahrain',
        ])->assertRedirect();

        $this->assertDatabaseHas('cms_settings', ['company_id' => $manager->company_id, 'key' => 'brand_name', 'value' => 'RetailPOS Pro']);
        $this->assertDatabaseHas('cms_theme_settings', ['company_id' => $manager->company_id, 'website_theme_mode' => 'clean_light']);
        $this->assertDatabaseHas('cms_footer_profiles', ['company_id' => $manager->company_id, 'india_contact' => 'Bengaluru, India']);
        $this->assertDatabaseHas('domain_event_logs', ['company_id' => $manager->company_id, 'event_key' => 'cms.branding.updated']);
        $this->assertDatabaseHas('domain_event_logs', ['company_id' => $manager->company_id, 'event_key' => 'cms.theme.updated']);
    }

    public function test_staff_is_denied_and_client_logos_are_tenant_scoped_with_soft_delete_restore(): void
    {
        $staff = $this->user(UserRole::Staff);
        $this->actingAs($staff)->get('/cms/client-logos')->assertForbidden();

        $manager = $this->user(UserRole::Manager);
        $this->actingAs($manager)->post('/cms/client-logos', [
            'name' => 'Demo Apparel Group', 'display_style' => 'color', 'is_featured' => '1',
            'show_on_homepage' => '1', 'show_on_case_studies' => '1', 'is_active' => '1', 'sort_order' => 1,
        ])->assertRedirect();

        $logo = CmsClientLogo::query()->where('company_id', $manager->company_id)->firstOrFail();
        $otherManager = $this->user(UserRole::Manager);
        $this->actingAs($otherManager)->delete("/cms/client-logos/{$logo->id}")->assertNotFound();

        $this->actingAs($manager)->delete("/cms/client-logos/{$logo->id}")->assertRedirect();
        $this->assertSoftDeleted('cms_client_logos', ['id' => $logo->id]);
        $this->actingAs($manager)->post("/cms/client-logos/{$logo->id}/restore")->assertRedirect();
        $this->assertDatabaseHas('cms_client_logos', ['id' => $logo->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('domain_event_logs', ['company_id' => $manager->company_id, 'event_key' => 'cms.client_logo.created']);
    }

    public function test_case_study_lifecycle_and_content_library_records_are_managed(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)->post('/cms/case-studies', [
            'title' => 'Demo Retail Rollout', 'slug' => 'demo-retail-rollout', 'client_name' => 'Demo Retail Group', 'status' => 'draft',
            'short_summary' => 'Illustrative case study content.', 'metrics' => ['stores' => '8'],
            'sections' => [['section_type' => 'challenge', 'title' => 'Challenge', 'content' => 'Illustrative challenge', 'sort_order' => 1]],
        ])->assertRedirect();

        $study = CmsCaseStudy::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertCount(1, $study->sections);
        $this->actingAs($manager)->post("/cms/case-studies/{$study->id}/publish")->assertRedirect();
        $this->assertSame('published', $study->refresh()->status);
        $this->actingAs($manager)->post("/cms/case-studies/{$study->id}/unpublish")->assertRedirect();
        $this->assertSame('draft', $study->refresh()->status);

        $this->actingAs($manager)->post('/cms/testimonials', [
            'client_name' => 'Demo Operations Lead', 'testimonial_text' => 'Illustrative testimonial content.', 'rating' => 5, 'is_active' => '1', 'sort_order' => 1,
        ])->assertRedirect();
        $this->actingAs($manager)->post('/cms/trust-metrics', [
            'label' => 'Businesses Served', 'value' => '500+', 'is_active' => '1', 'show_on_homepage' => '1', 'sort_order' => 1,
        ])->assertRedirect();
        $this->actingAs($manager)->post('/cms/faqs', [
            'question' => 'Can I manage pages?', 'answer' => 'Yes, this is illustrative FAQ content.', 'is_active' => '1', 'sort_order' => 1,
        ])->assertRedirect();
        $this->actingAs($manager)->post('/cms/ctas', [
            'title' => 'Ready to begin?', 'button_text' => 'Book a demo', 'button_link' => '/contact', 'is_active' => '1', 'sort_order' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('cms_testimonials', ['company_id' => $manager->company_id, 'client_name' => 'Demo Operations Lead']);
        $this->assertDatabaseHas('cms_trust_metrics', ['company_id' => $manager->company_id, 'label' => 'Businesses Served']);
        $this->assertDatabaseHas('cms_faqs', ['company_id' => $manager->company_id, 'question' => 'Can I manage pages?']);
        $this->assertDatabaseHas('cms_cta_blocks', ['company_id' => $manager->company_id, 'title' => 'Ready to begin?']);
        $this->assertDatabaseHas('domain_event_logs', ['company_id' => $manager->company_id, 'event_key' => 'cms.case_study.published']);
        $this->assertDatabaseHas('domain_event_logs', ['company_id' => $manager->company_id, 'event_key' => 'cms.case_study.unpublished']);
    }

    public function test_seeder_creates_cms_pro_demo_content(): void
    {
        $this->seed();

        $this->assertDatabaseHas('cms_theme_settings', ['website_theme_mode' => 'clean_light']);
        $this->assertDatabaseHas('cms_client_logos', ['name' => 'Demo Apparel Group']);
        $this->assertDatabaseHas('cms_case_studies', ['slug' => 'demo-multi-store-retail-rollout', 'status' => 'published']);
        $this->assertDatabaseHas('cms_trust_metrics', ['label' => 'Businesses Served', 'value' => '500+']);
        $this->assertDatabaseHas('cms_trust_metrics', ['label' => 'Years Experience', 'value' => '15+']);
        $this->assertDatabaseHas('cms_trust_metrics', ['label' => 'Successful Software Projects', 'value' => '100+']);
        $this->assertDatabaseHas('cms_trust_metrics', ['label' => 'Support', 'value' => '24/7']);
    }

    private function user(UserRole $role): User
    {
        return User::factory()->for(Company::factory())->create(['role' => $role]);
    }
}
