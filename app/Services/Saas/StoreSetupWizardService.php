<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Models\InvoiceTemplateSetting;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Setting;
use App\Models\SaasTenantOnboarding;
use App\Models\StoreSetupWizard;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Compliance\GstinValidator;
use App\Services\Crm\InvoiceTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StoreSetupWizardService
{
    public function __construct(private readonly StoreSetupRecommendationService $recommendations, private readonly InvoiceTemplateService $templates, private readonly GstinValidator $gstin, private readonly AuditLogger $audit) {}

    public function enabled(): bool { return (bool) config('store_setup.enabled'); }

    public function canManage(User $user): bool { return $user->can('store.setup.manage'); }

    public function shouldRedirect(User $user): bool
    {
        if (! $this->enabled() || ! $this->canManage($user)) return false;
        $wizard = StoreSetupWizard::query()->where('company_id', $user->company_id)->first();
        if ($wizard) return $wizard->status === 'draft' && ! $wizard->skipped_at;
        return SaasTenantOnboarding::query()->where('company_id', $user->company_id)->where('status', 'completed')->exists();
    }

    public function wizard(User $user): StoreSetupWizard
    {
        $company = $user->company()->firstOrFail();
        $wizard = StoreSetupWizard::query()->firstOrCreate(['company_id' => $company->id], [
            'status' => 'draft', 'current_step' => 0, 'industry_key' => $company->industry ?: 'general_retail', 'answers' => ['industry' => $company->industry ?: 'general_retail'],
            'idempotency_key' => (string) Str::uuid(), 'created_by' => $user->id, 'updated_by' => $user->id, 'started_at' => now(),
        ]);
        $wizard->update(['last_resumed_at' => now(), 'updated_by' => $user->id]);
        return $wizard->refresh();
    }

    public function start(User $user, StoreSetupWizard $wizard): StoreSetupWizard
    {
        $this->assertOwnership($user, $wizard);
        $wizard->update(['status' => 'draft', 'skipped_at' => null, 'current_step' => max(1, $wizard->current_step), 'updated_by' => $user->id, 'last_resumed_at' => now()]);
        $this->audit->record('saas.store_setup.started', $wizard, 'Store setup started.', ['company_id' => $user->company_id]);
        return $wizard->refresh();
    }

    /** @param array<string,mixed> $input */
    public function save(User $user, StoreSetupWizard $wizard, int $step, array $input): StoreSetupWizard
    {
        $this->assertOwnership($user, $wizard);
        if ($wizard->status === 'completed') throw ValidationException::withMessages(['setup' => 'Store setup is already complete.']);
        $answers = $wizard->answers ?? [];
        $answers = array_replace_recursive($answers, $this->validateStep($wizard, $step, $input));
        $recommendations = $this->recommendations->make($user->company, $answers);
        $wizard->update(['status' => 'draft', 'skipped_at' => null, 'current_step' => min(6, max($wizard->current_step, $step + 1)), 'answers' => $answers, 'recommendations' => $recommendations, 'recommendation_version' => config('store_setup.version'), 'updated_by' => $user->id, 'last_resumed_at' => now()]);
        $this->audit->record('saas.store_setup.step_saved', $wizard, 'Store setup step saved.', ['company_id' => $user->company_id, 'step' => $step]);
        return $wizard->refresh();
    }

    public function skip(User $user, StoreSetupWizard $wizard): void
    {
        $this->assertOwnership($user, $wizard);
        $wizard->update(['status' => 'skipped', 'skipped_at' => now(), 'updated_by' => $user->id]);
        $this->audit->record('saas.store_setup.skipped', $wizard, 'Store setup skipped.', ['company_id' => $user->company_id]);
    }

    /** @param array<string,mixed> $choices */
    public function apply(User $user, StoreSetupWizard $wizard, array $choices): StoreSetupWizard
    {
        $this->assertOwnership($user, $wizard);
        return DB::transaction(function () use ($user, $wizard, $choices): StoreSetupWizard {
            $wizard = StoreSetupWizard::query()->where('company_id', $user->company_id)->lockForUpdate()->findOrFail($wizard->id);
            if ($wizard->status === 'completed') return $wizard;
            $company = Company::query()->lockForUpdate()->findOrFail($user->company_id);
            $plan = $this->recommendations->make($company, $wizard->answers ?? []);
            $selectedCategories = collect($choices['categories'] ?? [])->filter()->values()->all();
            $allowedCategories = collect($plan['categories'])->pluck('name')->all();
            if (array_diff($selectedCategories, $allowedCategories)) throw ValidationException::withMessages(['categories' => 'Choose only categories in your current setup plan.']);
            $created = $this->createCategories($company, $selectedCategories);
            if (filter_var($choices['apply_tax'] ?? false, FILTER_VALIDATE_BOOL)) $this->applyTax($company, $wizard->answers ?? []);
            if (filter_var($choices['apply_template'] ?? false, FILTER_VALIDATE_BOOL)) $this->applyTemplate($company, $user, $plan['invoice_template']['key']);
            if (filter_var($choices['apply_barcode'] ?? false, FILTER_VALIDATE_BOOL)) $this->applyBarcodeDefaults($company, $user, $wizard->answers ?? []);
            $wizard->update(['status' => 'completed', 'current_step' => 7, 'recommendations' => $plan, 'recommendation_version' => config('store_setup.version'), 'applied_version' => config('store_setup.version'), 'completed_at' => now(), 'completed_by' => $user->id, 'updated_by' => $user->id]);
            $this->audit->record('saas.store_setup.completed', $wizard, 'Store setup completed.', ['company_id' => $company->id, 'categories_created' => count($created), 'duration_seconds' => now()->diffInSeconds($wizard->started_at ?? $wizard->created_at)]);
            return $wizard->refresh();
        });
    }

    /** @return array<string,mixed> */
    private function validateStep(StoreSetupWizard $wizard, int $step, array $input): array
    {
        return match ($step) {
            1 => $this->stepOne($wizard, $input),
            2 => ['product_volume' => $this->oneOf($input['product_volume'] ?? null, ['under_50', '50_250', '251_1000', '1001_5000', 'over_5000', 'unsure'], 'product_volume')],
            3 => ['tax' => $this->taxAnswers($input)],
            4 => ['scanner' => $this->scannerAnswers($input)],
            5 => ['printer' => $this->printerAnswers($input)],
            6 => ['import' => ['choice' => $this->oneOf($input['choice'] ?? null, ['csv_template', 'manual', 'skip'], 'choice')]],
            default => throw ValidationException::withMessages(['step' => 'That setup step is not available.']),
        };
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function stepOne(StoreSetupWizard $wizard, array $input): array
    {
        $industry = $wizard->industry_key;
        $allowed = array_keys(config('store_setup.subtypes.'.$industry, []));
        $subtypes = array_values(array_unique(array_filter((array) ($input['subtypes'] ?? []), 'is_string')));
        if (! $subtypes || array_diff($subtypes, [...$allowed, 'other'])) throw ValidationException::withMessages(['subtypes' => 'Choose one or more supported business types.']);
        return ['industry' => $industry, 'subtypes' => $subtypes];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function taxAnswers(array $input): array
    {
        $registered = filter_var($input['registered'] ?? false, FILTER_VALIDATE_BOOL);
        $rates = array_values(array_unique(array_filter((array) ($input['rates'] ?? []), fn ($rate) => in_array((string) $rate, ['0', '5', '12', '18', '28'], true))));
        $gstin = isset($input['gstin']) ? strtoupper(trim((string) $input['gstin'])) : null;
        if ($registered && (! $gstin || ! $this->gstin->isStructurallyValid($gstin))) throw ValidationException::withMessages(['gstin' => 'Enter a valid GSTIN format. This does not verify registration with the government.']);
        return ['registered' => $registered, 'gstin' => $registered ? $gstin : null, 'state_code' => $this->nullableState($input['state_code'] ?? null), 'state_name' => $this->nullableString($input['state_name'] ?? null, 80), 'pricing_mode' => $this->oneOf($input['pricing_mode'] ?? 'inclusive', ['inclusive', 'exclusive'], 'pricing_mode'), 'rates' => $rates, 'interstate_sales' => filter_var($input['interstate_sales'] ?? false, FILTER_VALIDATE_BOOL), 'hsn_tracking' => filter_var($input['hsn_tracking'] ?? false, FILTER_VALIDATE_BOOL)];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function scannerAnswers(array $input): array
    {
        return ['choice' => $this->oneOf($input['choice'] ?? null, ['already_have', 'plan_to_buy', 'manual_search', 'unsure'], 'choice'), 'keyboard_input' => filter_var($input['keyboard_input'] ?? false, FILTER_VALIDATE_BOOL), 'existing_barcodes' => filter_var($input['existing_barcodes'] ?? false, FILTER_VALIDATE_BOOL), 'generate_missing' => filter_var($input['generate_missing'] ?? false, FILTER_VALIDATE_BOOL), 'format' => $this->oneOf($input['format'] ?? 'code128', ['code128', 'ean13', 'unsure'], 'format')];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function printerAnswers(array $input): array
    {
        return ['type' => $this->oneOf($input['type'] ?? null, ['thermal', 'a4', 'both', 'digital', 'unsure'], 'type'), 'thermal_width' => $this->oneOf($input['thermal_width'] ?? '80', ['58', '80'], 'thermal_width')];
    }

    /** @return array<int,string> */
    private function createCategories(Company $company, array $names): array
    {
        $created = [];
        foreach ($names as $offset => $name) {
            $existing = InventoryCategory::withTrashed()->where('company_id', $company->id)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
            if ($existing) { if ($existing->trashed()) $existing->restore(); continue; }
            $slug = Str::slug($name);
            $suffix = 1; $candidate = $slug;
            while (InventoryCategory::withTrashed()->where('company_id', $company->id)->where('slug', $candidate)->exists()) $candidate = $slug.'-'.++$suffix;
            InventoryCategory::create(['company_id' => $company->id, 'name' => $name, 'slug' => $candidate, 'sort_order' => $offset + 1, 'is_active' => true]);
            $created[] = $name;
        }
        return $created;
    }

    /** @param array<string,mixed> $answers */
    private function applyTax(Company $company, array $answers): void
    {
        $tax = (array) ($answers['tax'] ?? []);
        if (! ($tax['registered'] ?? false)) return;
        $setting = GstSetting::firstOrCreate(['company_id' => $company->id], ['legal_name' => $company->legal_name ?: $company->name]);
        if (! $setting->gstin) $setting->update(['gstin' => $tax['gstin'], 'registration_type' => 'regular', 'state_code' => $tax['state_code'], 'state_name' => $tax['state_name'], 'default_place_of_supply_state_code' => $tax['state_code']]);
        if (! $company->tax_id) $company->update(['tax_id' => $tax['gstin']]);
        foreach ((array) ($tax['rates'] ?? []) as $index => $rate) {
            InventoryTaxRate::query()->firstOrCreate(['company_id' => $company->id, 'rate' => $rate, 'tax_type' => 'gst'], ['name' => 'GST '.$rate.'%', 'country' => 'India', 'is_default' => $index === 0, 'is_active' => true]);
        }
    }

    private function applyTemplate(Company $company, User $user, string $templateKey): void
    {
        $setting = $this->templates->setting($company);
        if ($setting->updated_by) return;
        $this->templates->update($company, $user, ['template_key' => $templateKey, 'brand_color' => '#0F766E', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $this->templates->defaultOptions()]);
    }

    /** @param array<string,mixed> $answers */
    private function applyBarcodeDefaults(Company $company, User $user, array $answers): void
    {
        $scanner = (array) ($answers['scanner'] ?? []);
        if (! in_array($scanner['choice'] ?? null, ['already_have', 'plan_to_buy'], true)) return;
        Setting::query()->firstOrCreate(['company_id' => $company->id, 'group' => 'inventory', 'key' => 'barcode_price_source'], ['value' => ['value' => 'selling_price']]);
        if (! BarcodeLabelTemplate::query()->where('company_id', $company->id)->exists()) {
            BarcodeLabelTemplate::create(['company_id' => $company->id, 'name' => 'Store Setup Barcode Label', 'industry_type' => $company->industry, 'paper_size' => 'custom', 'label_width_mm' => 50, 'label_height_mm' => 25, 'columns' => 1, 'rows' => 1, 'barcode_type' => $scanner['format'] === 'ean13' ? 'ean13' : 'code128', 'barcode_width_mm' => 40, 'barcode_height_mm' => 12, 'font_size' => 9, 'show_product_name' => true, 'show_sku' => true, 'show_barcode_text' => true, 'show_price' => true, 'is_default' => true, 'is_active' => true]);
        }
    }

    private function assertOwnership(User $user, StoreSetupWizard $wizard): void { abort_unless($this->canManage($user) && $wizard->company_id === $user->company_id, 403); }
    private function oneOf(mixed $value, array $allowed, string $field): string { if (! is_string($value) || ! in_array($value, $allowed, true)) throw ValidationException::withMessages([$field => 'Choose a valid option.']); return $value; }
    private function nullableState(mixed $value): ?string { if ($value === null || $value === '') return null; if (! is_string($value) || ! preg_match('/^[0-9]{2}$/', $value)) throw ValidationException::withMessages(['state_code' => 'Enter a two-digit GST state code.']); return $value; }
    private function nullableString(mixed $value, int $max): ?string { if ($value === null || trim((string) $value) === '') return null; if (! is_string($value) || mb_strlen(trim($value)) > $max) throw ValidationException::withMessages(['value' => 'Enter a shorter value.']); return trim($value); }
}
