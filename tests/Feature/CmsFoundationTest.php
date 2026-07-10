<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsHomepageSection;
use App\Models\Cms\CmsPage;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_access_cms(): void
    {
        $staff = $this->user(UserRole::Staff);

        $this->actingAs($staff)
            ->get('/cms')
            ->assertForbidden();
    }

    public function test_manager_can_create_cms_page_with_seo_and_revision(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->post('/cms/pages', [
                'title' => 'Retail POS Software',
                'slug' => 'retail-pos-software',
                'subtitle' => 'Run every store with clarity',
                'hero_content' => 'Enterprise hero content',
                'body_content' => 'Long-form page body',
                'status' => CmsPage::STATUS_DRAFT,
                'meta_title' => 'Retail POS Software Meta',
                'meta_description' => 'Retail POS software meta description.',
                'meta_keywords' => 'retail,pos',
                'canonical_url' => 'https://retailpos.biz/retail-pos-software',
                'twitter_card' => 'summary_large_image',
            ])
            ->assertRedirect();

        $page = CmsPage::query()->where('slug', 'retail-pos-software')->firstOrFail();

        $this->assertSame($manager->company_id, $page->company_id);
        $this->assertSame(CmsPage::STATUS_DRAFT, $page->status);
        $this->assertSame('Retail POS Software Meta', $page->seo->meta_title);
        $this->assertCount(1, $page->revisions);
    }

    public function test_cms_page_publish_and_unpublish_actions_are_audited(): void
    {
        $manager = $this->user(UserRole::Manager);
        $page = CmsPage::create([
            'company_id' => $manager->company_id,
            'author_user_id' => $manager->id,
            'slug' => 'about',
            'title' => 'About',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->actingAs($manager)
            ->post("/cms/pages/{$page->id}/publish")
            ->assertRedirect();

        $this->assertSame(CmsPage::STATUS_PUBLISHED, $page->refresh()->status);
        $this->assertNotNull($page->published_at);

        $this->actingAs($manager)
            ->post("/cms/pages/{$page->id}/unpublish")
            ->assertRedirect();

        $this->assertSame(CmsPage::STATUS_DRAFT, $page->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.page.published']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.page.unpublished']);
    }

    public function test_cms_settings_and_footer_can_be_updated(): void
    {
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)
            ->put('/cms/settings', [
                'website_name' => 'RetailPOS.biz',
                'tagline' => 'Enterprise retail command center',
                'default_meta' => 'Default website meta',
                'email' => 'hello@retailpos.biz',
                'phone' => '+91 90000 00000',
                'whatsapp' => '+91 90000 00000',
                'address' => 'Bengaluru',
                'business_hours' => '10 AM - 7 PM',
                'google_map' => 'https://maps.example.com',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_settings', [
            'company_id' => $administrator->company_id,
            'key' => 'website_name',
            'value' => 'RetailPOS.biz',
        ]);

        $this->actingAs($administrator)
            ->put('/cms/settings/footer', [
                'company_name' => 'RetailPOS',
                'address' => 'MG Road',
                'phone' => '+91 90000 00000',
                'email' => 'hello@retailpos.biz',
                'whatsapp' => '+91 90000 00000',
                'business_hours' => '10 AM - 7 PM',
                'google_map_url' => 'https://maps.example.com',
                'copyright_text' => 'Copyright RetailPOS',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_footer_profiles', [
            'company_id' => $administrator->company_id,
            'company_name' => 'RetailPOS',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.settings.updated']);
    }

    public function test_seo_settings_and_redirects_can_be_managed(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->put('/cms/seo', [
                'default_meta_title' => 'RetailPOS SEO',
                'default_meta_description' => 'SEO default description.',
                'default_meta_keywords' => 'retail,pos',
                'default_canonical_url' => 'https://retailpos.biz',
                'schema_markup' => '{"@type":"Organization"}',
                'robots_txt' => "User-agent: *\nAllow: /",
                'sitemap_enabled' => '1',
                'search_console_verification' => 'google-site-verification',
                'google_analytics_id' => 'G-123',
                'google_tag_manager_id' => 'GTM-123',
                'facebook_pixel_id' => 'FB-123',
                'linkedin_insight_tag' => 'LI-123',
                'microsoft_clarity_id' => 'CLARITY-123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_seo_settings', [
            'company_id' => $manager->company_id,
            'default_meta_title' => 'RetailPOS SEO',
        ]);

        $this->actingAs($manager)
            ->post('/cms/seo/redirects', [
                'source_url' => '/old',
                'target_url' => '/new',
                'status_code' => 301,
                'is_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_redirects', [
            'company_id' => $manager->company_id,
            'source_url' => '/old',
            'target_url' => '/new',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.seo.updated']);
    }

    public function test_menus_and_menu_items_can_be_managed(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->post('/cms/menus', [
                'name' => 'Header',
                'location' => 'header',
                'is_enabled' => '1',
            ])
            ->assertRedirect();

        $menuId = (int) DB::table('cms_menus')->where('name', 'Header')->value('id');

        $this->actingAs($manager)
            ->post("/cms/menus/{$menuId}/items", [
                'label' => 'Pricing',
                'url' => '/pricing',
                'icon' => 'tag',
                'opens_new_tab' => '0',
                'is_enabled' => '1',
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_menus', [
            'company_id' => $manager->company_id,
            'name' => 'Header',
            'location' => 'header',
        ]);
        $this->assertDatabaseHas('cms_menu_items', [
            'menu_id' => $menuId,
            'label' => 'Pricing',
            'url' => '/pricing',
        ]);
    }

    public function test_homepage_builder_creates_defaults_and_updates_sections(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->get('/cms/homepage')
            ->assertOk()
            ->assertSee('Homepage Builder');

        $this->assertDatabaseHas('cms_homepage_sections', [
            'company_id' => $manager->company_id,
            'key' => 'hero',
        ]);

        $this->actingAs($manager)
            ->put('/cms/homepage/hero', [
                'heading' => 'RetailPOS Hero',
                'subheading' => 'Managed from CMS',
                'content' => 'Hero content body',
                'cta_label' => 'Book demo',
                'cta_url' => '/contact',
                'is_enabled' => '1',
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $this->assertSame(
            'RetailPOS Hero',
            CmsHomepageSection::query()
                ->where('company_id', $manager->company_id)
                ->where('key', 'hero')
                ->value('heading'),
        );
    }

    public function test_database_seeder_creates_cms_foundation_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('cms_homepage_sections', [
            'key' => 'hero',
            'name' => 'Hero',
        ]);
        $this->assertDatabaseHas('cms_settings', [
            'key' => 'website_name',
            'value' => 'RetailPOS',
        ]);
        $this->assertDatabaseHas('cms_seo_settings', [
            'default_meta_title' => 'RetailPOS - Enterprise Retail Platform',
        ]);
        $this->assertDatabaseHas('cms_menus', [
            'location' => 'header',
            'name' => 'Primary Header',
        ]);
    }

    private function user(UserRole $role): User
    {
        $company = Company::factory()->create();

        return User::factory()->for($company)->create([
            'role' => $role,
        ]);
    }
}
