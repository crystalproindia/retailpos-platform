<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\StoreSetupWizard;
use App\Models\Setting;
use App\Models\User;

class Free365OnboardingService
{
    /** @return array{dismissed: bool, completed: int, total: int, items: array<int, array{key:string,label:string,description:string,complete:bool,route:?string}>}|null */
    public function checklist(User $user): ?array
    {
        $company = $user->company()->first();
        if (! $company || ! $this->isPublicFree365($company)) return null;

        $items = [
            ['key' => 'account', 'label' => 'Account created', 'description' => 'Your RetailPOS account is active.', 'complete' => true, 'route' => null],
            ['key' => 'store_setup', 'label' => 'Complete store setup', 'description' => 'Apply the recommended starter settings for your store.', 'complete' => StoreSetupWizard::query()->where('company_id', $company->id)->where('status', 'completed')->exists(), 'route' => 'onboarding.store-setup.show'],
            ['key' => 'store_name', 'label' => 'Update your store name', 'description' => 'Replace the temporary store name with your own.', 'complete' => ! $company->hasPlaceholderName(), 'route' => 'settings.company-profile.edit'],
            ['key' => 'company_details', 'label' => 'Complete company details', 'description' => 'Add your address and GST details before formal GST billing.', 'complete' => filled($company->address) && filled($company->tax_id), 'route' => 'settings.company-profile.edit'],
            ['key' => 'product', 'label' => 'Add your first product', 'description' => 'Build your catalog so your counter is ready.', 'complete' => $company->products()->exists(), 'route' => 'inventory.products.create'],
            ['key' => 'customer', 'label' => 'Add a customer', 'description' => 'Keep customer details for better billing.', 'complete' => $company->customers()->exists(), 'route' => 'customers.create'],
            ['key' => 'invoice', 'label' => 'Create your first invoice', 'description' => 'Start billing once your store details are ready.', 'complete' => CrmInvoice::query()->where('company_id', $company->id)->exists(), 'route' => 'sales.invoices.create'],
            ['key' => 'sales', 'label' => 'Review today’s sales', 'description' => 'See your store’s operating snapshot.', 'complete' => $company->posSales()->exists(), 'route' => 'dashboard'],
        ];
        $dismissed = (bool) data_get(Setting::query()->where('company_id', $company->id)->where('group', 'free365_onboarding')->where('key', 'checklist')->value('value'), 'dismissed', false);

        return ['dismissed' => $dismissed, 'completed' => count(array_filter($items, fn (array $item) => $item['complete'])), 'total' => count($items), 'items' => $items];
    }

    public function dismiss(User $user): void
    {
        Setting::query()->updateOrCreate(
            ['company_id' => $user->company_id, 'group' => 'free365_onboarding', 'key' => 'checklist'],
            ['value' => ['dismissed' => true, 'dismissed_at' => now()->toIso8601String(), 'dismissed_by' => $user->id]],
        );
    }

    private function isPublicFree365(Company $company): bool
    {
        return $company->saasSubscriptions()
            ->whereIn('status', ['active', 'trialing', 'grace_period'])
            ->whereHas('plan', fn ($query) => $query->where('code', config('saas.free365_plan_code')))
            ->exists();
    }
}
