<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsSetting;
use App\Models\Company;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CmsContentImportService
{
    public function __construct(private readonly AuditLogger $audit, private readonly CmsRevisionService $revisions) {}

    /** @param array<string, mixed> $manifest @return array<string, int> */
    public function import(Company $company, ?User $actor, array $manifest, bool $dryRun = false, bool $updateExisting = false, bool $publish = false): array
    {
        $this->validateManifest($manifest);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'failed' => 0];
        if ($dryRun) { $result['created'] = count($manifest['pages'] ?? []) + count($manifest['case_studies'] ?? []); return $result; }

        return DB::transaction(function () use ($company, $actor, $manifest, $updateExisting, $publish, $result): array {
            foreach ($manifest['pages'] ?? [] as $entry) {
                $slug = Str::slug((string) ($entry['slug'] ?? $entry['title']));
                $page = CmsPage::query()->where('company_id', $company->id)->where('slug', $slug)->first();
                if ($page && ! $updateExisting) { $result['skipped']++; continue; }
                $payload = Arr::only($entry, ['route_path', 'title', 'h1', 'page_type', 'subtitle', 'hero_content', 'intro_content', 'body_content', 'footer_seo_content', 'schema_json', 'sort_order']);
                $payload += ['slug' => $slug, 'status' => $publish ? CmsPage::STATUS_PUBLISHED : CmsPage::STATUS_DRAFT, 'is_active' => $publish, 'published_at' => $publish ? now() : null];
                if ($page) { $page->update($payload); $result['updated']++; } else { $page = CmsPage::create($payload + ['company_id' => $company->id, 'author_user_id' => $actor?->id]); $result['created']++; }
                foreach ($entry['sections'] ?? [] as $index => $section) $page->sections()->updateOrCreate(['section_key' => $section['section_key'] ?? 'section-'.($index + 1)], Arr::only($section, ['section_key', 'section_type', 'title', 'subtitle', 'content', 'settings', 'is_active']) + ['company_id' => $company->id, 'sort_order' => $section['sort_order'] ?? $index + 1]);
                $this->revisions->record($page, $actor, 'imported', ['page' => $page->fresh()->toArray()], null, 'Imported from website content manifest');
            }
            foreach ($manifest['case_studies'] ?? [] as $entry) {
                $slug = Str::slug((string) ($entry['slug'] ?? $entry['title']));
                $study = CmsCaseStudy::query()->where('company_id', $company->id)->where('slug', $slug)->first();
                if ($study && ! $updateExisting) { $result['skipped']++; continue; }
                $payload = Arr::only($entry, ['title', 'client_name', 'industry', 'location', 'project_type', 'short_summary', 'challenge', 'solution', 'results', 'metrics', 'schema_json', 'sort_order']) + ['slug' => $slug, 'status' => $publish ? 'published' : 'draft', 'published_at' => $publish ? now() : null];
                if ($study) { $study->update($payload); $result['updated']++; } else { $study = CmsCaseStudy::create($payload + ['company_id' => $company->id]); $result['created']++; }
                $this->revisions->record($study, $actor, 'imported', ['case_study' => $study->fresh()->toArray()], null, 'Imported from website content manifest');
            }
            foreach ($manifest['settings'] ?? [] as $key => $value) {
                if (preg_match('/(token|secret|password|api[_-]?key)/i', (string) $key)) { $result['warnings']++; continue; }
                CmsSetting::updateOrCreate(['company_id' => $company->id, 'key' => $key], ['group' => 'imported', 'label' => str($key)->headline(), 'value' => is_scalar($value) ? (string) $value : json_encode($value), 'value_type' => 'text', 'is_public' => true]);
            }
            $this->audit->record('cms.content.imported', null, 'Website content manifest imported', ['company_id' => $company->id, 'result' => $result]);
            return $result;
        });
    }

    /** @param array<string, mixed> $manifest */
    private function validateManifest(array $manifest): void
    {
        if (! isset($manifest['pages']) && ! isset($manifest['case_studies']) && ! isset($manifest['settings'])) throw ValidationException::withMessages(['manifest' => 'The manifest must include pages, case studies, or settings.']);
        $errors = [];
        foreach ($manifest['pages'] ?? [] as $index => $page) {
            if (! is_array($page) || blank($page['title'] ?? null)) { $errors["pages.$index.title"] = 'Each imported page needs a title.'; continue; }
            $slug = Str::slug((string) ($page['slug'] ?? $page['title']));
            $this->validateFiniteFields($errors, "pages.$index", 'page', $slug, $page, ['slug' => 255, 'title' => 255, 'h1' => 255, 'page_type' => 255, 'route_path' => 255]);
            foreach ($page['sections'] ?? [] as $sectionIndex => $section) {
                if (! is_array($section)) { $errors["pages.$index.sections.$sectionIndex"] = "Page \"{$slug}\" has an invalid section."; continue; }
                $this->validateFiniteFields($errors, "pages.$index.sections.$sectionIndex", 'page section', $slug, $section, ['section_key' => 255, 'section_type' => 255, 'title' => 255]);
            }
        }
        foreach ($manifest['case_studies'] ?? [] as $index => $study) {
            if (! is_array($study) || blank($study['title'] ?? null)) { $errors["case_studies.$index.title"] = 'Each imported case study needs a title.'; continue; }
            $slug = Str::slug((string) ($study['slug'] ?? $study['title']));
            $this->validateFiniteFields($errors, "case_studies.$index", 'case study', $slug, $study, ['slug' => 255, 'title' => 255, 'client_name' => 255, 'industry' => 255, 'location' => 255, 'project_type' => 255]);
        }
        if ($errors !== []) throw ValidationException::withMessages($errors);
    }

    /** @param array<string, string> $errors @param array<string, mixed> $data @param array<string, int> $limits */
    private function validateFiniteFields(array &$errors, string $path, string $entity, string $slug, array $data, array $limits): void
    {
        foreach ($limits as $field => $limit) {
            if (! isset($data[$field]) || ! is_string($data[$field])) continue;
            $length = mb_strlen($data[$field]);
            if ($length > $limit) $errors["{$path}.{$field}"] = ucfirst($entity)." \"{$slug}\": {$field} has {$length} characters; maximum allowed is {$limit}.";
        }
    }
}
