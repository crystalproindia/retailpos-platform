<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteCmsLeadIntakeTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_users_can_manage_website_pages_while_staff_cannot_access_them(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($manager)->get('/website/pages')->assertOk()->assertSee('Dynamic Pages');
        $this->actingAs($staff)->get('/website/pages')->assertForbidden();

        $this->actingAs($manager)
            ->post('/website/pages', $this->pagePayload('Website Contact'))
            ->assertRedirect();

        $page = CmsPage::query()->where('company_id', $manager->company_id)->where('slug', 'website-contact')->firstOrFail();
        $this->assertSame('Website Contact', $page->title);

        $this->actingAs($manager)
            ->put("/website/pages/{$page->id}", $this->pagePayload('Contact RetailPOS', ['slug' => 'contact-retailpos', 'status' => CmsPage::STATUS_ARCHIVED]))
            ->assertRedirect();

        $this->assertSame(CmsPage::STATUS_ARCHIVED, $page->refresh()->status);
        $this->assertFalse($page->is_active);
    }

    public function test_page_sections_and_navigation_items_can_be_created_and_updated(): void
    {
        $manager = $this->user(UserRole::Manager);
        $page = CmsPage::create([
            'company_id' => $manager->company_id,
            'author_user_id' => $manager->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => CmsPage::STATUS_DRAFT,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post("/cms/pages/{$page->id}/sections", [
                'section_key' => 'hero',
                'section_type' => 'hero',
                'title' => 'A clear retail day',
                'content' => 'Structured hero content.',
                'settings' => '{"alignment":"left"}',
                'sort_order' => 1,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_page_sections', [
            'page_id' => $page->id,
            'section_key' => 'hero',
            'section_type' => 'hero',
        ]);

        $menu = CmsMenu::create([
            'company_id' => $manager->company_id,
            'name' => 'Primary Header',
            'location' => 'header',
            'is_enabled' => true,
        ]);

        $this->actingAs($manager)
            ->post("/website/navigation/{$menu->id}/items", [
                'label' => 'Pricing',
                'url' => '/pricing',
                'opens_new_tab' => false,
                'is_enabled' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $item = CmsMenuItem::query()->where('menu_id', $menu->id)->firstOrFail();

        $this->actingAs($manager)
            ->put("/website/navigation/{$menu->id}/items/{$item->id}", [
                'label' => 'Plans',
                'url' => '/pricing',
                'opens_new_tab' => true,
                'is_enabled' => false,
                'sort_order' => 2,
            ])
            ->assertRedirect();

        $this->assertSame('Plans', $item->refresh()->label);
        $this->assertTrue($item->opens_new_tab);
        $this->assertFalse($item->is_enabled);
    }

    public function test_website_settings_can_be_updated(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->put('/website/settings', [
                'company_name' => 'RetailPOS Demo',
                'primary_phone' => 'Configured in production',
                'support_email' => 'support@example.test',
                'whatsapp_default_message' => 'How can we help?',
                'google_analytics_id' => 'G-TEST123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_settings', [
            'company_id' => $manager->company_id,
            'key' => 'company_name',
            'value' => 'RetailPOS Demo',
            'group' => 'company',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cms.settings.updated']);
    }

    public function test_public_lead_api_rejects_missing_or_invalid_tokens(): void
    {
        $company = Company::factory()->create();
        config()->set('services.retailpos.public_lead_company_id', $company->id);
        config()->set('services.retailpos.public_lead_token', 'valid-token');

        $this->postJson('/api/public/leads', $this->leadPayload())->assertUnauthorized();
        $this->withHeader('X-RetailPOS-Lead-Token', 'wrong-token')->postJson('/api/public/leads', $this->leadPayload())->assertUnauthorized();
    }

    public function test_public_lead_api_creates_a_sanitized_crm_lead_activity_and_audit_record(): void
    {
        $manager = $this->user(UserRole::Manager);
        $this->configurePublicIntake($manager);

        $response = $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload([
                'name' => 'Asha <strong>Mehta</strong>',
                'company_name' => 'Acme <em>Retail</em>',
                'source' => 'contact',
                'page_url' => 'https://retailpos.biz/contact',
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'retail-search',
                'metadata' => ['form_variant' => '<b>contact-a</b>'],
            ]));

        $response->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Lead received successfully.',
        ]);

        $lead = CrmLead::query()->where('company_id', $manager->company_id)->firstOrFail();
        $this->assertSame('Asha Mehta', $lead->contact_name);
        $this->assertSame('Acme Retail', $lead->business_name);
        $this->assertSame('website-contact', $lead->source->slug);
        $this->assertSame('new', $lead->status->slug);
        $this->assertSame('https://retailpos.biz/contact', $lead->metadata['page_url']);
        $this->assertSame('google', $lead->metadata['utm']['source']);
        $this->assertSame('contact-a', $lead->metadata['submitted_metadata']['form_variant']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Public website lead received']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.lead.public_intake_received', 'auditable_id' => $lead->id]);
    }

    public function test_book_demo_maps_to_demo_source_honeypots_do_not_create_leads_and_dashboard_counts_include_api_intake(): void
    {
        $manager = $this->user(UserRole::Manager);
        $this->configurePublicIntake($manager);

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload(['source' => 'book_demo']))
            ->assertOk();

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload(['name' => 'Bot User', 'website' => 'https://spam.example']))
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Lead received successfully.']);

        $this->assertDatabaseCount('crm_leads', 1);
        $lead = CrmLead::query()->firstOrFail();
        $this->assertSame('book-demo', $lead->source->slug);

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Total Leads', '1', 'New Leads', '1', 'Demo Requests', '1', 'Follow-up Pending', '0']);
    }

    public function test_demo_seed_includes_website_pages_navigation_and_grouped_settings(): void
    {
        $this->seed();

        $this->assertDatabaseHas('cms_pages', ['slug' => 'home', 'title' => 'Home']);
        $this->assertDatabaseHas('cms_pages', ['slug' => 'book-demo', 'title' => 'Book Demo']);
        $this->assertDatabaseHas('cms_menus', ['name' => 'Primary Header', 'location' => 'header']);
        $this->assertDatabaseHas('cms_menus', ['name' => 'Footer Navigation', 'location' => 'footer']);
        $this->assertDatabaseHas('cms_menus', ['name' => 'Mobile Navigation', 'location' => 'mobile']);
        $this->assertDatabaseHas('cms_settings', ['key' => 'company_name', 'group' => 'company']);
    }

    public function test_legacy_cms_branding_header_and_client_logo_routes_redirect_to_safe_website_surfaces(): void
    {
        $manager = $this->user(UserRole::Manager);

        $this->actingAs($manager)
            ->get('/website/settings')
            ->assertOk()
            ->assertSee('Branding')
            ->assertSee('Header')
            ->assertSee('Client Logos');

        $this->actingAs($manager)
            ->get('/cms/branding')
            ->assertRedirect(route('website.settings.index').'#branding');

        $this->actingAs($manager)
            ->get('/cms/header')
            ->assertRedirect(route('website.settings.index').'#header');

        $this->actingAs($manager)
            ->get('/cms/client-logos')
            ->assertRedirect(route('website.settings.index').'#client-logos');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pagePayload(string $title, array $overrides = []): array
    {
        return array_merge([
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'page_type' => 'standard',
            'status' => CmsPage::STATUS_DRAFT,
            'meta_title' => $title.' | RetailPOS',
            'meta_description' => 'A CMS page used by an automated feature test.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Mehta',
            'company_name' => 'Acme Retail',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'city' => 'Bengaluru',
            'country' => 'India',
            'business_type' => 'Retail',
            'requirement' => 'Interested in a RetailPOS demonstration.',
            'source' => 'contact',
        ], $overrides);
    }

    private function configurePublicIntake(User $user): void
    {
        config()->set('services.retailpos.public_lead_token', 'test-lead-token');
        config()->set('services.retailpos.public_lead_company_id', $user->company_id);
        config()->set('services.retailpos.public_lead_assignee_id', $user->id);
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
