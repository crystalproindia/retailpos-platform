<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPreviewToken;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsRevisionPreviewImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_changed_page_creates_a_generic_revision_but_unchanged_save_does_not(): void
    {
        $user = $this->user();
        $page = CmsPage::create($this->page($user));
        $payload = ['title' => 'About RetailPOS', 'slug' => 'about', 'page_type' => 'standard', 'status' => 'draft', 'is_active' => true];

        $this->actingAs($user)->put("/website/pages/{$page->id}", $payload)->assertRedirect();
        $this->assertDatabaseCount('cms_revisions', 1);
        $this->actingAs($user)->put("/website/pages/{$page->id}", $payload)->assertRedirect();
        $this->assertDatabaseCount('cms_revisions', 1);
    }

    public function test_preview_tokens_are_hashed_and_only_expose_the_selected_draft(): void
    {
        $user = $this->user();
        $page = CmsPage::create($this->page($user));
        $other = CmsPage::create($this->page($user, ['slug' => 'private-draft', 'title' => 'Private draft']));

        $response = $this->actingAs($user)->post("/website/pages/{$page->id}/preview")->assertRedirect()->assertSessionHas('preview_url');
        $token = CmsPreviewToken::firstOrFail();
        $this->assertNotSame($token->token_hash, $response->getSession()->get('preview_url'));
        $url = parse_url($response->getSession()->get('preview_url'));
        parse_str($url['query'], $query);
        $this->getJson($url['path'].'?token='.$query['token'])->assertOk()->assertJsonPath('data.slug', 'about');
        $this->getJson('/api/public/cms/preview/page/private-draft?token='.$query['token'])->assertNotFound();
        $token->update(['expires_at' => now()->subMinute()]);
        $this->getJson($url['path'].'?token='.$query['token'])->assertNotFound();
    }

    public function test_restore_revision_returns_page_to_draft_and_records_a_new_revision(): void
    {
        $user = $this->user();
        $page = CmsPage::create($this->page($user, ['body_content' => 'First draft']));
        $this->actingAs($user)->put("/website/pages/{$page->id}", ['title' => 'About RetailPOS', 'slug' => 'about', 'page_type' => 'standard', 'body_content' => 'Second draft', 'status' => 'draft', 'is_active' => true]);
        $revision = \App\Models\Cms\CmsRevision::firstOrFail();
        $this->actingAs($user)->post("/website/pages/{$page->id}/revisions/{$revision->id}/restore")->assertRedirect();
        $this->assertSame(CmsPage::STATUS_DRAFT, $page->fresh()->status);
        $this->assertDatabaseHas('cms_revisions', ['action' => 'restored']);
    }

    public function test_manifest_dry_run_and_import_create_drafts_without_duplicates(): void
    {
        $company = Company::factory()->create();
        $file = storage_path('framework/testing-cms-import.json');
        file_put_contents($file, json_encode(['pages' => [['title' => 'Imported Page', 'slug' => 'imported-page', 'page_type' => 'standard', 'body_content' => 'Draft content']]]));
        $this->artisan('cms:import-website-content', ['file' => $file, '--company' => $company->id, '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseMissing('cms_pages', ['company_id' => $company->id, 'slug' => 'imported-page']);
        $this->artisan('cms:import-website-content', ['file' => $file, '--company' => $company->id])->assertSuccessful();
        $this->assertDatabaseHas('cms_pages', ['company_id' => $company->id, 'slug' => 'imported-page', 'status' => 'draft']);
        @unlink($file);
    }

    private function user(): User { return User::factory()->for(Company::factory())->create(['role' => UserRole::Administrator]); }
    private function page(User $user, array $overrides = []): array { return array_merge(['company_id' => $user->company_id, 'author_user_id' => $user->id, 'title' => 'About RetailPOS', 'slug' => 'about', 'page_type' => 'standard', 'status' => 'draft', 'is_active' => true], $overrides); }
}
