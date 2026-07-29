<?php

namespace Database\Seeders;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\PreferredContactMethod;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Cms\CmsFooterProfile;
use App\Models\Cms\CmsFooterBlock;
use App\Models\Cms\CmsFaq;
use App\Models\Cms\CmsHomepageSection;
use App\Models\Cms\CmsContentPage;
use App\Models\Cms\CmsContentSection;
use App\Models\Cms\CmsNavigationItem;
use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsCaseStudySection;
use App\Models\Cms\CmsClientLogo;
use App\Models\Cms\CmsCtaBlock;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPageSection;
use App\Models\Cms\CmsTestimonial;
use App\Models\Cms\CmsThemeSetting;
use App\Models\Cms\CmsTrustMetric;
use App\Models\Cms\CmsSeoSetting;
use App\Models\Cms\CmsSetting;
use App\Models\Company;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerActivityLog;
use App\Models\Customers\CustomerAddress;
use App\Models\Customers\CustomerContact;
use App\Models\Customers\CustomerGroup;
use App\Models\Customers\CustomerGroupMember;
use App\Models\Customers\CustomerInsightSnapshot;
use App\Models\Customers\CustomerLoyaltyAccount;
use App\Models\Customers\CustomerLoyaltyTransaction;
use App\Models\Customers\CustomerReturnSummary;
use App\Models\Customers\CustomerSetting;
use App\Models\Customers\CustomerWalletTransaction;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmTag;
use App\Models\DashboardStatistic;
use App\Models\DomainEventLog;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\BarcodePrintBatch;
use App\Models\Inventory\ChannelProductMapping;
use App\Models\Inventory\ChannelStockLevel;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventorySyncLog;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductAttribute;
use App\Models\Inventory\ProductAttributeValue;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\ReorderSuggestion;
use App\Models\Inventory\SalesChannel;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\Purchases\GoodsReceipt;
use App\Models\Purchases\PurchaseApprovalLog;
use App\Models\Purchases\PurchaseOrder;
use App\Models\Purchases\PurchaseRequest;
use App\Models\Purchases\PurchaseReturn;
use App\Models\Purchases\PurchaseSettings;
use App\Models\Purchases\Supplier;
use App\Models\Purchases\SupplierAddress;
use App\Models\Purchases\SupplierContact;
use App\Models\Purchases\SupplierProduct;
use App\Models\Purchases\SupplierScoreSnapshot;
use App\Models\Promotions\PromotionAction;
use App\Models\Promotions\PromotionBrandTarget;
use App\Models\Promotions\PromotionBranchTarget;
use App\Models\Promotions\PromotionCampaign;
use App\Models\Promotions\PromotionCategoryTarget;
use App\Models\Promotions\PromotionChannelTarget;
use App\Models\Promotions\PromotionCoupon;
use App\Models\Promotions\PromotionProductTarget;
use App\Models\Promotions\PromotionRule;
use App\Models\Promotions\PromotionSettings;
use App\Models\Pos\CustomerProductSummary;
use App\Models\Pos\PosPayment;
use App\Models\Pos\PosProductPairSummary;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\QueueJobSnapshot;
use App\Models\Setting;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\SystemHealthCheck;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Notifications\PlatformNotification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['name' => 'Crystal Retail Demo'],
            [
                'legal_name' => 'Crystal Retail Demo Private Limited',
                'tax_id' => 'GSTIN29ABCDE1234F1Z5',
                'email' => 'operations@retailpos.test',
                'phone' => '+91 98765 43210',
                'address' => 'MG Road, Bengaluru, Karnataka',
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'is_active' => true,
            ],
        );

        $branch = Branch::updateOrCreate(
            [
                'company_id' => $company->id,
                'code' => 'BLR-HQ',
            ],
            [
                'name' => 'Bengaluru HQ',
                'email' => 'blr@retailpos.test',
                'phone' => '+91 98765 43211',
                'address' => 'MG Road Flagship Store',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'is_primary' => true,
                'is_active' => true,
            ],
        );

        $admin = User::updateOrCreate(
            ['email' => 'admin@retailpos.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'RetailPOS Administrator',
                'role' => UserRole::Administrator,
                'is_active' => true,
                'is_platform_admin' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $manager = User::updateOrCreate(
            ['email' => 'manager@retailpos.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'RetailPOS Manager',
                'role' => UserRole::Manager,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $sales = User::updateOrCreate(
            ['email' => 'sales@retailpos.test'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'RetailPOS Sales',
                'role' => UserRole::Sales,
                'is_active' => true,
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $growthPlan = SaasPlan::updateOrCreate(
            ['code' => 'retail-growth'],
            [
                'name' => 'Retail Growth',
                'description' => 'Demo plan for the RetailPOS subscription foundation.',
                'status' => 'active',
                'billing_interval' => 'monthly',
                'currency' => 'INR',
                'base_price' => 0,
                'setup_fee' => 0,
                'tax_percentage' => 0,
                'trial_days' => 14,
                'grace_period_days' => 3,
                'sort_order' => 10,
                'is_public' => true,
                'is_recommended' => true,
                'is_custom' => false,
            ],
        );

        foreach (config('saas.features') as $feature) {
            $growthPlan->features()->updateOrCreate(['feature_key' => $feature], ['is_enabled' => true]);
        }

        foreach (config('saas.usage_limits') as $limit) {
            $growthPlan->limits()->updateOrCreate(['limit_key' => $limit], ['limit_value' => null]);
        }

        $growthPlan->load(['features', 'limits']);
        $snapshot = $growthPlan->snapshot();
        $growthPlan->versions()->firstOrCreate(['version' => 1], ['snapshot' => $snapshot, 'created_by' => $admin->id]);
        SaasSubscription::firstOrCreate(
            ['company_id' => $company->id, 'subscription_number' => 'SUB-DEMO-RETAIL-GROWTH'],
            [
                'saas_plan_id' => $growthPlan->id,
                'status' => 'trialing',
                'billing_interval' => $growthPlan->billing_interval,
                'currency' => $growthPlan->currency,
                'price_snapshot' => $growthPlan->base_price,
                'tax_snapshot' => $growthPlan->tax_percentage,
                'setup_fee_snapshot' => $growthPlan->setup_fee,
                'feature_snapshot' => $snapshot['features'],
                'limit_snapshot' => $snapshot['limits'],
                'trial_starts_at' => today(),
                'trial_ends_at' => today()->addDays($growthPlan->trial_days),
                'starts_at' => today(),
                'current_period_starts_at' => today(),
                'current_period_ends_at' => today()->addMonth(),
                'renewal_date' => today()->addMonth(),
                'grace_period_ends_at' => today()->addDays($growthPlan->trial_days + $growthPlan->grace_period_days),
                'billing_method' => 'complimentary',
                'internal_notes' => 'Demo-only SaaS foundation subscription.',
            ],
        );

        $customerGroups = collect([
            ['name' => 'Regular', 'description' => 'Demo customer group for standard retail customers.', 'sort_order' => 1, 'is_default' => true],
            ['name' => 'VIP', 'description' => 'Demo customer group for high-value retail relationships.', 'sort_order' => 2, 'is_default' => false],
            ['name' => 'Wholesale', 'description' => 'Demo customer group for wholesale accounts.', 'sort_order' => 3, 'is_default' => false],
            ['name' => 'Loyalty Member', 'description' => 'Demo customer group for loyalty foundation records.', 'sort_order' => 4, 'is_default' => false],
        ])->mapWithKeys(fn (array $group) => [str($group['name'])->slug()->toString() => CustomerGroup::updateOrCreate(['company_id' => $company->id, 'slug' => str($group['name'])->slug()->toString()], $group + ['company_id' => $company->id, 'slug' => str($group['name'])->slug()->toString(), 'is_active' => true, 'loyalty_multiplier' => 1])]);

        $customerSettings = CustomerSetting::updateOrCreate(['company_id' => $company->id], ['customer_number_prefix' => 'CUS', 'next_customer_number' => 1008, 'default_customer_group_id' => $customerGroups['regular']->id, 'birthday_reminder_days_before' => 7, 'inactive_customer_days' => 90, 'lost_customer_days' => 180, 'frequent_return_threshold_count' => 3, 'frequent_return_threshold_days' => 90, 'loyalty_enabled' => true, 'wallet_enabled' => true, 'loyalty_points_per_amount' => 100, 'loyalty_amount_per_point' => 1, 'allow_negative_wallet' => false]);

        $demoCustomers = collect([
            ['number' => 'CUS-001001', 'first' => 'Demo', 'last' => 'Top Customer', 'phone' => '+919000000101', 'type' => 'vip', 'status' => 'active', 'purchase' => 245000, 'orders' => 24, 'last_purchase' => now()->subDays(5), 'birthday' => now()->setYear(1990)->toDateString(), 'group' => 'vip', 'points' => 2450, 'wallet' => 500, 'returns' => 1],
            ['number' => 'CUS-001002', 'first' => 'Demo', 'last' => 'Birthday Today', 'phone' => '+919000000102', 'type' => 'retail', 'status' => 'active', 'purchase' => 35000, 'orders' => 5, 'last_purchase' => now()->subDays(12), 'birthday' => now()->toDateString(), 'group' => 'loyalty-member', 'points' => 350, 'wallet' => 0, 'returns' => 0],
            ['number' => 'CUS-001003', 'first' => 'Demo', 'last' => 'Upcoming Birthday', 'phone' => '+919000000103', 'type' => 'retail', 'status' => 'active', 'purchase' => 28000, 'orders' => 4, 'last_purchase' => now()->subDays(20), 'birthday' => now()->addDays(3)->setYear(1992)->toDateString(), 'group' => 'regular', 'points' => 280, 'wallet' => 0, 'returns' => 0],
            ['number' => 'CUS-001004', 'first' => 'Demo', 'last' => 'Inactive Customer', 'phone' => '+919000000104', 'type' => 'retail', 'status' => 'inactive', 'purchase' => 65000, 'orders' => 8, 'last_purchase' => now()->subDays(110), 'birthday' => null, 'group' => 'regular', 'points' => 650, 'wallet' => 0, 'returns' => 0],
            ['number' => 'CUS-001005', 'first' => 'Demo', 'last' => 'Lost Customer', 'phone' => '+919000000105', 'type' => 'corporate', 'status' => 'lost', 'purchase' => 98000, 'orders' => 9, 'last_purchase' => now()->subDays(200), 'birthday' => null, 'group' => 'wholesale', 'points' => 980, 'wallet' => 0, 'returns' => 0],
            ['number' => 'CUS-001006', 'first' => 'Demo', 'last' => 'Frequent Returner', 'phone' => '+919000000106', 'type' => 'retail', 'status' => 'active', 'purchase' => 42000, 'orders' => 7, 'last_purchase' => now()->subDays(18), 'birthday' => null, 'group' => 'regular', 'points' => 120, 'wallet' => 100, 'returns' => 4],
            ['number' => 'CUS-001007', 'first' => 'Demo', 'last' => 'Wholesale Account', 'phone' => '+919000000107', 'type' => 'wholesale', 'status' => 'active', 'purchase' => 152000, 'orders' => 14, 'last_purchase' => now()->subDays(8), 'birthday' => null, 'group' => 'wholesale', 'points' => 1500, 'wallet' => 0, 'returns' => 1],
        ])->map(function (array $data) use ($company, $branch, $admin, $customerGroups): Customer {
            $customer = Customer::updateOrCreate(['company_id' => $company->id, 'customer_number' => $data['number']], ['branch_id' => $branch->id, 'first_name' => $data['first'], 'last_name' => $data['last'], 'display_name' => $data['first'].' '.$data['last'], 'phone' => $data['phone'], 'whatsapp' => $data['phone'], 'customer_type' => $data['type'], 'status' => $data['status'], 'source' => 'Demo foundation record', 'date_of_birth' => $data['birthday'], 'created_by' => $admin->id, 'last_purchase_at' => $data['last_purchase'], 'last_return_at' => $data['returns'] ? now()->subDays(14) : null, 'total_purchase_amount' => $data['purchase'], 'total_orders_count' => $data['orders'], 'total_return_amount' => $data['returns'] * 1250, 'total_returns_count' => $data['returns'], 'loyalty_points_balance' => $data['points'], 'wallet_balance' => $data['wallet'], 'is_active' => $data['status'] === 'active', 'notes' => 'Demo/foundation customer record. Not a real customer.']);
            CustomerGroupMember::updateOrCreate(['company_id' => $company->id, 'customer_id' => $customer->id, 'customer_group_id' => $customerGroups[$data['group']]->id], ['assigned_at' => now(), 'assigned_by' => $admin->id]);
            CustomerAddress::updateOrCreate(['customer_id' => $customer->id, 'type' => 'billing'], ['company_id' => $company->id, 'name' => $customer->display_name, 'phone' => $customer->phone, 'address_line_1' => 'Demo Retail Address', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'country' => 'India', 'postal_code' => '560001', 'is_default' => true]);
            CustomerContact::updateOrCreate(['customer_id' => $customer->id, 'name' => $customer->display_name], ['company_id' => $company->id, 'phone' => $customer->phone, 'is_primary' => true, 'is_active' => true]);
            $account = CustomerLoyaltyAccount::updateOrCreate(['company_id' => $company->id, 'customer_id' => $customer->id], ['loyalty_number' => 'LOY-'.str_pad((string)$customer->id, 6, '0', STR_PAD_LEFT), 'tier' => $data['type'] === 'vip' ? 'vip' : 'standard', 'points_balance' => $data['points'], 'lifetime_points_earned' => $data['points'], 'joined_at' => now()->subMonths(6), 'last_activity_at' => $data['last_purchase'], 'is_active' => true]);
            CustomerLoyaltyTransaction::updateOrCreate(['customer_id' => $customer->id, 'description' => 'Demo loyalty foundation credit'], ['company_id' => $company->id, 'loyalty_account_id' => $account->id, 'transaction_type' => 'adjustment_credit', 'points' => $data['points'], 'created_by' => $admin->id]);
            if ($data['wallet']) CustomerWalletTransaction::updateOrCreate(['customer_id' => $customer->id, 'description' => 'Demo wallet foundation credit'], ['company_id' => $company->id, 'transaction_type' => 'adjustment_credit', 'amount' => $data['wallet'], 'balance_after' => $data['wallet'], 'created_by' => $admin->id]);
            CustomerActivityLog::updateOrCreate(['customer_id' => $customer->id, 'title' => 'Demo customer foundation created'], ['company_id' => $company->id, 'activity_type' => 'created', 'description' => 'Demo-only customer intelligence foundation.', 'user_id' => $admin->id, 'occurred_at' => now()->subMonths(6)]);
            CustomerReturnSummary::updateOrCreate(['company_id' => $company->id, 'customer_id' => $customer->id], ['return_count' => $data['returns'], 'return_amount' => $data['returns'] * 1250, 'last_return_at' => $data['returns'] ? now()->subDays(14) : null, 'is_frequent_returner' => $data['returns'] >= 3, 'calculated_at' => now(), 'reason_summary' => ['source' => 'Demo/foundation return behavior only.']]);
            return $customer;
        });

        $demoCustomers->each(fn (Customer $customer) => app(\App\Services\Customers\CustomerInsightService::class)->calculate($customer));

        collect([
            ['key' => 'total_sales', 'label' => 'Total Sales', 'value' => '₹12.84L', 'trend' => '+18.4% this month', 'tone' => 'success', 'sort_order' => 1],
            ['key' => 'orders_today', 'label' => 'Orders Today', 'value' => '186', 'trend' => '+24 since noon', 'tone' => 'neutral', 'sort_order' => 2],
            ['key' => 'customers', 'label' => 'Customers', 'value' => '4,820', 'trend' => '+312 this quarter', 'tone' => 'neutral', 'sort_order' => 3],
            ['key' => 'products', 'label' => 'Products', 'value' => '1,248', 'trend' => '96 active categories', 'tone' => 'neutral', 'sort_order' => 4],
            ['key' => 'low_stock', 'label' => 'Low Stock', 'value' => '8', 'trend' => 'Needs replenishment', 'tone' => 'warning', 'sort_order' => 5],
            ['key' => 'leads', 'label' => 'Leads', 'value' => '64', 'trend' => '17 hot leads', 'tone' => 'success', 'sort_order' => 6],
            ['key' => 'branches', 'label' => 'Branches', 'value' => '5', 'trend' => '3 cities covered', 'tone' => 'neutral', 'sort_order' => 7],
            ['key' => 'employees', 'label' => 'Employees', 'value' => '42', 'trend' => '31 active today', 'tone' => 'neutral', 'sort_order' => 8],
        ])->each(fn (array $metric) => DashboardStatistic::updateOrCreate(
            [
                'company_id' => $company->id,
                'key' => $metric['key'],
            ],
            $metric + ['company_id' => $company->id],
        ));

        collect([
            'general' => [
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'date_format' => 'd M Y',
            ],
            'company' => [
                'company_name' => $company->name,
                'tax_id' => $company->tax_id,
                'registered_address' => $company->address,
            ],
            'business' => [
                'fiscal_year_start' => 'April',
                'default_branch' => $branch->name,
                'stock_alert_threshold' => 10,
            ],
            'email' => [
                'from_name' => 'RetailPOS Operations',
                'from_email' => 'notifications@retailpos.test',
                'support_email' => 'support@retailpos.test',
            ],
            'notifications' => [
                'low_stock_alerts' => true,
                'daily_sales_digest' => true,
                'lead_alerts' => true,
                'lead_notifications_enabled' => true,
                'lead_email_notifications_enabled' => false,
                'lead_notification_email' => null,
                'notify_admins_on_new_lead' => true,
                'notify_sales_on_new_lead' => true,
                'followup_reminders_enabled' => true,
            ],
            'theme' => [
                'mode' => 'system',
                'accent_color' => 'teal',
                'compact_sidebar' => false,
            ],
            'security' => [
                'session_timeout' => 120,
                'require_mfa' => false,
                'audit_retention_days' => 365,
            ],
        ])->each(function (array $settings, string $group) use ($company): void {
            collect($settings)->each(fn (mixed $value, string $key) => Setting::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'group' => $group,
                    'key' => $key,
                ],
                [
                    'value' => ['value' => $value],
                ],
            ));
        });

        collect([
            [
                'event_key' => 'crm.lead.assigned',
                'channel' => 'email',
                'name' => 'Lead assigned email',
                'subject' => 'New lead assigned: {{ lead_title }}',
                'body' => 'A CRM lead has been assigned to you. Lead: {{ lead_title }}. Business: {{ business_name }}.',
            ],
            [
                'event_key' => 'crm.follow_up.due',
                'channel' => 'email',
                'name' => 'Follow-up due email',
                'subject' => 'Follow-up due: {{ subject }}',
                'body' => 'Your CRM follow-up is due soon. Activity: {{ subject }}. Lead: {{ lead_title }}.',
            ],
            [
                'event_key' => 'crm.follow_up.overdue',
                'channel' => 'email',
                'name' => 'Follow-up overdue email',
                'subject' => 'Overdue follow-up: {{ subject }}',
                'body' => 'A CRM follow-up is overdue. Activity: {{ subject }}. Please update the activity outcome.',
            ],
            [
                'event_key' => 'cms.page.published',
                'channel' => 'database',
                'name' => 'CMS page published in-app',
                'subject' => 'CMS page published',
                'body' => 'The CMS page {{ title }} was published and is ready for website delivery.',
            ],
            [
                'event_key' => 'system.settings.updated',
                'channel' => 'database',
                'name' => 'Settings updated in-app',
                'subject' => 'Settings updated',
                'body' => 'Command Center settings were updated in {{ section }}.',
            ],
        ])->each(fn (array $template) => NotificationTemplate::updateOrCreate(
            [
                'company_id' => null,
                'event_key' => $template['event_key'],
                'channel' => $template['channel'],
                'locale' => 'en',
            ],
            $template + [
                'company_id' => null,
                'locale' => 'en',
                'is_system' => true,
                'is_active' => true,
                'version' => 1,
            ],
        ));

        collect([$admin, $manager, $sales])->each(function (User $user): void {
            collect(['crm.lead.created', 'crm.lead.assigned', 'crm.follow_up.due', 'crm.follow_up.overdue'])->each(
                fn (string $eventKey) => NotificationPreference::updateOrCreate(
                    [
                        'company_id' => $user->company_id,
                        'user_id' => $user->id,
                        'event_key' => $eventKey,
                    ],
                    [
                        'database_enabled' => true,
                        'email_enabled' => in_array($eventKey, ['crm.lead.assigned', 'crm.follow_up.due', 'crm.follow_up.overdue'], true),
                        'quiet_hours_enabled' => true,
                        'quiet_hours_start' => '20:00',
                        'quiet_hours_end' => '08:00',
                        'timezone' => 'Asia/Kolkata',
                    ],
                ),
            );
        });

        collect(config('cms.homepage_sections'))->each(function (array $section, string $key) use ($company): void {
            CmsHomepageSection::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'key' => $key,
                ],
                [
                    'name' => $section['name'],
                    'heading' => $section['name'],
                    'subheading' => match ($key) {
                        'hero' => 'Bring sales, inventory, customers, and branches into one confident retail workflow.',
                        'features' => 'Practical tools for teams that need clarity without the clutter.',
                        'modules' => 'Explore the connected capabilities available across the RetailPOS platform.',
                        'industries' => 'Built for retailers who want dependable operations as they grow.',
                        default => 'Demo content for the RetailPOS website builder. Review and approve before publishing.',
                    },
                    'content' => match ($key) {
                        'hero' => 'Demo website content: RetailPOS gives business owners a calmer way to see what is selling, what needs attention, and what comes next.',
                        'features' => 'Demo website content: organise day-to-day retail work with clear controls, useful reporting, and a team-friendly command center.',
                        'modules' => 'Demo website content: choose the RetailPOS capabilities that support your current operation and grow into the rest when ready.',
                        'industries' => 'Demo website content: adapt the platform story for fashion, grocery, lifestyle, specialty, and multi-store retail teams.',
                        default => 'Demo website content for this homepage section. Replace with approved customer-facing copy before publication.',
                    },
                    'is_enabled' => true,
                    'sort_order' => $section['sort_order'],
                ],
            );
        });

        $contentHomepage = CmsContentPage::updateOrCreate(
            ['company_id' => $company->id, 'page_key' => 'home'],
            ['route_path' => '/', 'page_type' => 'home', 'title' => 'RetailPOS Home', 'status' => CmsContentPage::STATUS_DRAFT, 'created_by' => $admin->id, 'updated_by' => $admin->id],
        );

        collect([
            ['home_hero', 'hero', 'Run retail operations with confidence'],
            ['product_highlights', 'product_highlights', 'Built for modern retail teams'],
            ['features', 'feature_grid', 'Everything your team needs'],
            ['industries', 'industry_use_cases', 'Made for your retail business'],
            ['ai_powered', 'benefits', 'Work smarter with RetailPOS'],
            ['testimonials', 'testimonials', 'Trusted by growing teams'],
            ['faq', 'faq', 'Frequently asked questions'],
            ['home_cta', 'cta', 'Ready to simplify your retail operations?'],
            ['footer_seo', 'footer_seo', 'RetailPOS business software'],
        ])->each(fn (array $section, int $index) => CmsContentSection::updateOrCreate(
            ['content_page_id' => $contentHomepage->id, 'section_key' => $section[0]],
            ['section_type' => $section[1], 'title' => $section[2], 'sort_order' => ($index + 1) * 10, 'is_enabled' => true],
        ));

        collect([
            ['Home', '/', 'header', 10], ['Products', '/products', 'header', 20], ['Pricing', '/pricing', 'header', 30], ['Contact', '/contact', 'header', 40],
        ])->each(fn (array $item) => CmsNavigationItem::updateOrCreate(
            ['company_id' => $company->id, 'label' => $item[0], 'location' => $item[2]],
            ['url' => $item[1], 'sort_order' => $item[3], 'is_enabled' => true, 'opens_new_tab' => false],
        ));

        CmsFooterBlock::updateOrCreate(
            ['company_id' => $company->id, 'block_key' => 'company_description'],
            ['title' => 'RetailPOS', 'content' => 'A connected retail operations platform for growing teams.', 'links' => [['label' => 'Contact', 'url' => '/contact']], 'sort_order' => 10, 'is_enabled' => true],
        );

        collect(config('cms.settings'))->each(function (array $definition, string $key) use ($company): void {
            CmsSetting::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'key' => $key,
                ],
                [
                    'group' => $definition['group'] ?? 'general',
                    'label' => $definition['label'],
                    'value' => match ($key) {
                        'website_name' => 'RetailPOS',
                        'tagline' => 'Enterprise retail operations platform',
                        'email' => 'hello@retailpos.biz',
                        'phone' => '+91 98765 43210',
                        'whatsapp' => '+91 98765 43210',
                        'address' => 'MG Road, Bengaluru, Karnataka',
                        default => null,
                    },
                    'value_type' => $definition['type'],
                    'is_public' => $definition['is_public'] ?? true,
                ],
            );
        });

        CmsFooterProfile::updateOrCreate(
            ['company_id' => $company->id],
            [
                'company_name' => 'RetailPOS',
                'address' => $company->address,
                'phone' => $company->phone,
                'email' => 'hello@retailpos.biz',
                'whatsapp' => '+91 98765 43210',
                'business_hours' => 'Monday to Saturday, 10:00 AM to 7:00 PM',
                'copyright_text' => 'Copyright '.now()->year.' RetailPOS. All rights reserved.',
            ],
        );

        CmsSeoSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                'default_meta_title' => 'RetailPOS - Enterprise Retail Platform',
                'default_meta_description' => 'RetailPOS helps retail teams manage sales, inventory, branches, customers, and operations from one command center.',
                'default_meta_keywords' => 'retail POS, inventory, CRM, retail platform',
                'robots_txt' => "User-agent: *\nAllow: /",
                'sitemap_enabled' => true,
            ],
        );

        $headerMenu = CmsMenu::updateOrCreate(
            [
                'company_id' => $company->id,
                'location' => 'header',
                'name' => 'Primary Header',
            ],
            ['is_enabled' => true],
        );

        collect([
            ['label' => 'Home', 'url' => '/', 'sort_order' => 1],
            ['label' => 'Products', 'url' => '/products', 'sort_order' => 2],
            ['label' => 'Industries', 'url' => '/industries', 'sort_order' => 3],
            ['label' => 'Solutions', 'url' => '/solutions', 'sort_order' => 4],
            ['label' => 'Pricing', 'url' => '/pricing', 'sort_order' => 5],
            ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 6],
        ])->each(fn (array $item) => CmsMenuItem::updateOrCreate(
            [
                'menu_id' => $headerMenu->id,
                'label' => $item['label'],
            ],
            $item + [
                'is_enabled' => true,
            ],
        ));

        collect([
            ['name' => 'Footer Navigation', 'location' => 'footer'],
            ['name' => 'Mobile Navigation', 'location' => 'mobile'],
        ])->each(function (array $menuData) use ($company): void {
            $menu = CmsMenu::updateOrCreate(
                ['company_id' => $company->id, 'location' => $menuData['location'], 'name' => $menuData['name']],
                ['is_enabled' => true],
            );

            collect([
                ['label' => 'Products', 'url' => '/products', 'sort_order' => 1],
                ['label' => 'Solutions', 'url' => '/solutions', 'sort_order' => 2],
                ['label' => 'Pricing', 'url' => '/pricing', 'sort_order' => 3],
                ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 4],
                ['label' => 'Book Demo', 'url' => '/book-demo', 'sort_order' => 5],
            ])->each(fn (array $item) => CmsMenuItem::updateOrCreate(
                ['menu_id' => $menu->id, 'label' => $item['label']],
                $item + ['is_enabled' => true],
            ));
        });

        collect([
            'brand_name' => 'RetailPOS',
            'brand_tagline' => 'Enterprise retail operations, connected.',
            'primary_brand_color' => '#0f766e',
            'secondary_brand_color' => '#1e293b',
            'accent_brand_color' => '#f59e0b',
            'button_style' => 'rounded',
            'default_cta_text' => 'Book a demo',
            'default_cta_link' => '/contact',
        ])->each(function (string $value, string $key) use ($company): void {
            $definition = config("cms.branding_settings.{$key}");
            CmsSetting::updateOrCreate(
                ['company_id' => $company->id, 'key' => $key],
                ['label' => $definition['label'], 'value' => $value, 'value_type' => $definition['type']],
            );
        });

        CmsThemeSetting::updateOrCreate(
            ['company_id' => $company->id],
            [
                'primary_color' => '#0f766e', 'secondary_color' => '#1e293b', 'accent_color' => '#f59e0b',
                'background_color' => '#ffffff', 'text_color' => '#0f172a', 'button_color' => '#0f766e',
                'button_radius_style' => 'rounded', 'card_radius_style' => 'soft', 'website_theme_mode' => 'clean_light',
                'header_style' => 'standard', 'footer_style' => 'structured', 'cta_button_style' => 'solid',
            ],
        );

        CmsFooterProfile::where('company_id', $company->id)->update([
            'india_contact' => 'Bengaluru, India | +91 98765 43210',
            'singapore_contact' => 'Singapore | Regional business contact',
            'malaysia_contact' => 'Malaysia | Regional business contact',
            'bahrain_contact' => 'Bahrain | Regional business contact',
        ]);

        collect([
            ['name' => 'Demo Apparel Group', 'industry' => 'Retail', 'location' => 'Bengaluru', 'short_description' => 'Demo client record for the logo wall. Not a real client endorsement.', 'display_style' => 'color', 'is_featured' => true, 'show_on_homepage' => true, 'show_on_case_studies' => true, 'is_active' => true, 'sort_order' => 1],
            ['name' => 'Demo Grocery Collective', 'industry' => 'Grocery', 'location' => 'Mumbai', 'short_description' => 'Demo client record for the logo wall. Not a real client endorsement.', 'display_style' => 'monochrome', 'is_featured' => false, 'show_on_homepage' => true, 'show_on_case_studies' => false, 'is_active' => true, 'sort_order' => 2],
            ['name' => 'Demo Lifestyle Stores', 'industry' => 'Lifestyle', 'location' => 'Delhi', 'short_description' => 'Demo client record for the logo wall. Not a real client endorsement.', 'display_style' => 'color', 'is_featured' => false, 'show_on_homepage' => true, 'show_on_case_studies' => false, 'is_active' => true, 'sort_order' => 3],
        ])->each(fn (array $logo) => CmsClientLogo::updateOrCreate(['company_id' => $company->id, 'name' => $logo['name']], $logo + ['company_id' => $company->id]));

        $caseStudy = CmsCaseStudy::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'demo-multi-store-retail-rollout'],
            [
                'title' => 'Demo Multi-Store Retail Rollout', 'client_name' => 'Demo Retail Group', 'industry' => 'Retail', 'location' => 'India', 'project_type' => 'Retail operations rollout',
                'short_summary' => 'Illustrative demo content showing a multi-store implementation story.', 'challenge' => 'Demo scenario: disconnected stock and sales visibility.',
                'solution' => 'Demo scenario: centralised retail operations and branch reporting.', 'key_features' => 'Inventory, branch controls, reporting, and customer workflows.',
                'results' => 'Illustrative results only. Replace with approved client outcomes before publication.', 'metrics' => ['stores' => '12', 'visibility' => 'Centralised'],
                'testimonial_quote' => 'Demo quote only: replace with approved client language before publishing.', 'related_product' => 'RetailPOS Platform', 'related_module' => 'Inventory', 'related_industry' => 'Retail',
                'cta_text' => 'Discuss your rollout', 'cta_link' => '/contact', 'status' => 'published', 'is_featured' => true, 'sort_order' => 1,
                'seo_title' => 'Demo Multi-Store Retail Rollout | RetailPOS', 'seo_description' => 'Illustrative RetailPOS case study content for CMS demonstration.', 'published_at' => now(),
            ],
        );

        collect([
            ['section_type' => 'challenge', 'title' => 'The challenge', 'content' => 'Illustrative challenge content for the CMS demo.', 'sort_order' => 1, 'is_active' => true],
            ['section_type' => 'solution', 'title' => 'The solution', 'content' => 'Illustrative solution content for the CMS demo.', 'sort_order' => 2, 'is_active' => true],
            ['section_type' => 'results', 'title' => 'The outcome', 'content' => 'Illustrative outcome content for the CMS demo.', 'sort_order' => 3, 'is_active' => true],
        ])->each(fn (array $section) => CmsCaseStudySection::updateOrCreate(['case_study_id' => $caseStudy->id, 'section_type' => $section['section_type']], $section + ['company_id' => $company->id, 'case_study_id' => $caseStudy->id]));

        collect([
            ['title' => 'Supermarket & Grocery Retail', 'slug' => 'supermarket-grocery-retail', 'industry' => 'Grocery Retail'],
            ['title' => 'Fashion & Apparel Store', 'slug' => 'fashion-apparel-store', 'industry' => 'Fashion Retail'],
            ['title' => 'Multi-Store Retail Chain', 'slug' => 'multi-store-retail-chain', 'industry' => 'Retail'],
            ['title' => 'Pharmacy / Healthcare Retail', 'slug' => 'pharmacy-healthcare-retail', 'industry' => 'Healthcare Retail'],
            ['title' => 'Newrie London', 'slug' => 'newrie-london', 'industry' => null],
        ])->each(fn (array $study) => CmsCaseStudy::updateOrCreate(
            ['company_id' => $company->id, 'slug' => $study['slug']],
            $study + ['company_id' => $company->id, 'client_name' => '', 'short_summary' => 'Draft case study framework. Optional claims must be approved before publication.', 'status' => 'draft', 'sort_order' => 50],
        ));

        collect([
            ['client_name' => 'Demo Retail Operations Lead', 'company_name' => 'Demo Retail Group', 'designation' => 'Operations Lead', 'testimonial_text' => 'Demo quote: RetailPOS gives our team a clearer daily view of the work that matters. Replace this with approved customer copy before publication.', 'rating' => 5, 'industry' => 'Retail', 'is_featured' => true, 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 1],
            ['client_name' => 'Demo Store Director', 'company_name' => 'Demo Lifestyle Stores', 'designation' => 'Store Director', 'testimonial_text' => 'Demo quote: we can review branch activity with more confidence and less manual follow-up. Replace this with approved customer copy before publication.', 'rating' => 5, 'industry' => 'Lifestyle', 'is_featured' => false, 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 2],
        ])->each(fn (array $testimonial) => CmsTestimonial::updateOrCreate(['company_id' => $company->id, 'client_name' => $testimonial['client_name']], $testimonial + ['company_id' => $company->id, 'case_study_id' => $caseStudy->id]));

        collect([
            ['label' => 'Businesses Served', 'value' => '500+', 'description' => 'Demo metric for the CMS manager.', 'icon' => 'users', 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 1],
            ['label' => 'Years Experience', 'value' => '15+', 'description' => 'Demo metric for the CMS manager.', 'icon' => 'calendar', 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 2],
            ['label' => 'Successful Software Projects', 'value' => '100+', 'description' => 'Demo metric for the CMS manager.', 'icon' => 'analytics', 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 3],
            ['label' => 'Support', 'value' => '24/7', 'description' => 'Demo metric for the CMS manager.', 'icon' => 'support', 'show_on_homepage' => true, 'is_active' => true, 'sort_order' => 4],
        ])->each(fn (array $metric) => CmsTrustMetric::updateOrCreate(['company_id' => $company->id, 'label' => $metric['label']], $metric + ['company_id' => $company->id]));

        CmsCtaBlock::updateOrCreate(
            ['company_id' => $company->id, 'title' => 'Ready to simplify retail operations?'],
            ['description' => 'Demo CTA: invite prospective customers to explore a calmer way to run retail operations.', 'button_text' => 'Book a demo', 'button_link' => '/contact', 'secondary_button_text' => 'Explore products', 'secondary_button_link' => '/products', 'location' => 'final_cta', 'style' => 'primary', 'is_active' => true, 'sort_order' => 1],
        );

        collect([
            ['question' => 'Which retail teams can use RetailPOS?', 'answer' => 'Demo answer: RetailPOS is designed for business owners and teams that need a more connected view of daily retail operations. Replace with approved public copy before publishing.', 'category' => 'General', 'page_location' => 'homepage', 'sort_order' => 1, 'is_active' => true],
            ['question' => 'Can website pages be managed without code?', 'answer' => 'Demo answer: administrators can manage structured website content through the CMS workspace. Replace with approved public copy before publishing.', 'category' => 'CMS', 'page_location' => 'homepage', 'sort_order' => 2, 'is_active' => true],
        ])->each(fn (array $faq) => CmsFaq::updateOrCreate(['company_id' => $company->id, 'question' => $faq['question']], $faq + ['company_id' => $company->id]));

        collect([
            ['slug' => 'home', 'title' => 'Home', 'page_type' => 'landing', 'subtitle' => 'Retail operations, connected', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Book a demo', 'cta_url' => '/book-demo', 'sort_order' => 0],
            ['slug' => 'about', 'title' => 'About', 'page_type' => 'standard', 'subtitle' => 'About RetailPOS', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Contact us', 'cta_url' => '/contact', 'sort_order' => 1],
            ['slug' => 'products', 'title' => 'Products', 'page_type' => 'product', 'subtitle' => 'Retail operations products', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Book a demo', 'cta_url' => '/book-demo', 'sort_order' => 2],
            ['slug' => 'industries', 'title' => 'Industries', 'page_type' => 'industry', 'subtitle' => 'Retail teams we support', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Explore solutions', 'cta_url' => '/solutions', 'sort_order' => 3],
            ['slug' => 'solutions', 'title' => 'Solutions', 'page_type' => 'solution', 'subtitle' => 'Connected retail workflows', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Explore products', 'cta_url' => '/products', 'sort_order' => 4],
            ['slug' => 'pricing', 'title' => 'Pricing', 'page_type' => 'standard', 'subtitle' => 'Plan your RetailPOS rollout', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Talk to our team', 'cta_url' => '/contact', 'sort_order' => 5],
            ['slug' => 'contact', 'title' => 'Contact', 'page_type' => 'standard', 'subtitle' => 'Talk with RetailPOS', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Book a demo', 'cta_url' => '/book-demo', 'sort_order' => 6],
            ['slug' => 'book-demo', 'title' => 'Book Demo', 'page_type' => 'landing', 'subtitle' => 'See RetailPOS in action', 'body_content' => 'Demo page content. Replace with approved public website content before publishing.', 'cta_label' => 'Contact us', 'cta_url' => '/contact', 'sort_order' => 7],
            ['slug' => 'retailpos-products', 'title' => 'RetailPOS Products', 'page_type' => 'product', 'subtitle' => 'Connected retail operations', 'body_content' => 'Demo page copy: explore the operational tools that help retail teams work with clearer information and steadier routines.', 'cta_label' => 'Book a demo', 'cta_url' => '/contact', 'sort_order' => 1],
            ['slug' => 'retail-solutions', 'title' => 'Retail Solutions', 'page_type' => 'solution', 'subtitle' => 'Solutions for growing teams', 'body_content' => 'Demo page copy: connect the workflows that matter now, then extend your platform as your retail operation grows.', 'cta_label' => 'Explore solutions', 'cta_url' => '/products', 'sort_order' => 2],
            ['slug' => 'retail-industry', 'title' => 'Retail Industry', 'page_type' => 'industry', 'subtitle' => 'Built for modern retail', 'body_content' => 'Demo page copy: adapt the RetailPOS story for the retail teams, categories, and locations you serve.', 'cta_label' => 'Talk to our team', 'cta_url' => '/contact', 'sort_order' => 3],
        ])->each(function (array $pageData) use ($company, $admin): void {
            $page = CmsPage::updateOrCreate(
                ['company_id' => $company->id, 'slug' => $pageData['slug']],
                $pageData + ['company_id' => $company->id, 'author_user_id' => $admin->id, 'status' => CmsPage::STATUS_PUBLISHED, 'is_active' => true, 'published_at' => now()],
            );
            $page->seo()->updateOrCreate([], ['meta_title' => $page->title.' | RetailPOS', 'meta_description' => 'Demo SEO content managed from the RetailPOS CMS.', 'canonical_url' => '/'.$page->slug, 'og_type' => 'website', 'twitter_card' => 'summary_large_image']);

            if ($page->slug === 'home') {
                CmsPageSection::updateOrCreate(
                    ['page_id' => $page->id, 'section_key' => 'hero'],
                    ['company_id' => $company->id, 'section_type' => 'hero', 'title' => 'Retail operations, connected', 'content' => 'Demo section content. Replace with approved public website content before publishing.', 'sort_order' => 1, 'is_active' => true],
                );
            }
        });

        $sources = collect([
            ['name' => 'Website Contact', 'description' => 'Inbound website contact enquiry.', 'tone' => 'success', 'sort_order' => 1],
            ['name' => 'Book Demo', 'description' => 'Inbound request to schedule a product demo.', 'tone' => 'info', 'sort_order' => 2],
            ['name' => 'Pricing Enquiry', 'description' => 'Pricing and commercial enquiry.', 'tone' => 'warning', 'sort_order' => 3],
            ['name' => 'WhatsApp', 'description' => 'WhatsApp enquiry.', 'tone' => 'info', 'sort_order' => 4],
            ['name' => 'Google Business Profile', 'description' => 'Google Business Profile enquiry.', 'tone' => 'neutral', 'sort_order' => 5],
            ['name' => 'Landing Page', 'description' => 'Campaign or landing page submission.', 'tone' => 'success', 'sort_order' => 6],
            ['name' => 'Manual Entry', 'description' => 'Lead captured by a Command Center user.', 'tone' => 'neutral', 'sort_order' => 7],
            ['name' => 'Other', 'description' => 'Unclassified inbound source.', 'tone' => 'neutral', 'sort_order' => 8],
            ['name' => 'Website Demo', 'description' => 'Legacy inbound website demo request.', 'tone' => 'success', 'sort_order' => 9],
            ['name' => 'Referral', 'description' => 'Partner or customer referral.', 'tone' => 'neutral', 'sort_order' => 10],
            ['name' => 'Retail Expo', 'description' => 'Event and booth conversations.', 'tone' => 'warning', 'sort_order' => 11],
        ])->mapWithKeys(fn (array $source): array => [
            Str::slug($source['name']) => CrmLeadSource::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => Str::slug($source['name']),
                ],
                $source + [
                    'company_id' => $company->id,
                    'slug' => Str::slug($source['name']),
                    'is_active' => true,
                ],
            ),
        ]);

        $statuses = collect([
            ['name' => 'New', 'stage_type' => LeadStageType::New, 'tone' => 'neutral', 'probability' => 10, 'sort_order' => 1],
            ['name' => 'Contacted', 'stage_type' => LeadStageType::Contacted, 'tone' => 'info', 'probability' => 25, 'sort_order' => 2],
            ['name' => 'Qualified', 'stage_type' => LeadStageType::Qualified, 'tone' => 'success', 'probability' => 45, 'sort_order' => 3],
            ['name' => 'Demo Scheduled', 'stage_type' => LeadStageType::DemoScheduled, 'tone' => 'warning', 'probability' => 60, 'sort_order' => 4],
            ['name' => 'Proposal Sent', 'stage_type' => LeadStageType::Proposal, 'tone' => 'info', 'probability' => 75, 'sort_order' => 5],
            ['name' => 'Proforma Sent', 'stage_type' => LeadStageType::ProformaSent, 'tone' => 'warning', 'probability' => 80, 'sort_order' => 6],
            ['name' => 'Partially Paid', 'stage_type' => LeadStageType::PartiallyPaid, 'tone' => 'info', 'probability' => 90, 'sort_order' => 7],
            ['name' => 'Follow Up', 'stage_type' => LeadStageType::FollowUp, 'tone' => 'warning', 'probability' => 55, 'sort_order' => 8],
            ['name' => 'Won', 'stage_type' => LeadStageType::Won, 'tone' => 'success', 'probability' => 100, 'is_won' => true, 'sort_order' => 9],
            ['name' => 'Lost', 'stage_type' => LeadStageType::Lost, 'tone' => 'danger', 'probability' => 0, 'is_lost' => true, 'sort_order' => 10],
            ['name' => 'Spam', 'stage_type' => LeadStageType::Spam, 'tone' => 'danger', 'probability' => 0, 'is_lost' => true, 'sort_order' => 11],
        ])->mapWithKeys(fn (array $status): array => [
            Str::slug($status['name']) => CrmLeadStatus::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'slug' => Str::slug($status['name']),
                ],
                [
                    'company_id' => $company->id,
                    'name' => $status['name'],
                    'slug' => Str::slug($status['name']),
                    'stage_type' => $status['stage_type']->value,
                    'tone' => $status['tone'],
                    'probability' => $status['probability'],
                    'is_won' => $status['is_won'] ?? false,
                    'is_lost' => $status['is_lost'] ?? false,
                    'is_active' => true,
                    'sort_order' => $status['sort_order'],
                ],
            ),
        ]);

        $tags = collect(['Hot Lead', 'Multi Branch', 'Fashion', 'Grocery', 'Implementation Ready'])
            ->mapWithKeys(fn (string $tag): array => [
                Str::slug($tag) => CrmTag::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($tag),
                    ],
                    [
                        'company_id' => $company->id,
                        'name' => $tag,
                        'slug' => Str::slug($tag),
                        'color' => '#0f766e',
                        'is_active' => true,
                    ],
                ),
            ]);

        $crmCompanies = collect([
            ['name' => 'Urban Threads Retail', 'industry' => 'Fashion', 'email' => 'ops@urbanthreads.test', 'phone' => '+91 90000 10001', 'city' => 'Mumbai', 'estimated_value' => 420000],
            ['name' => 'FreshMart Grocers', 'industry' => 'Grocery', 'email' => 'it@freshmart.test', 'phone' => '+91 90000 10002', 'city' => 'Bengaluru', 'estimated_value' => 680000],
            ['name' => 'StrideLine Footwear', 'industry' => 'Footwear', 'email' => 'retail@strideline.test', 'phone' => '+91 90000 10003', 'city' => 'Pune', 'estimated_value' => 350000],
            ['name' => 'GlowCare Beauty', 'industry' => 'Beauty', 'email' => 'admin@glowcare.test', 'phone' => '+91 90000 10004', 'city' => 'Delhi', 'estimated_value' => 510000],
        ])->map(fn (array $account) => CrmCompany::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => $account['name'],
            ],
            $account + [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'assigned_user_id' => $sales->id,
                'country' => 'India',
                'is_active' => true,
            ],
        ));

        $contacts = $crmCompanies->map(fn (CrmCompany $account, int $index) => CrmContact::updateOrCreate(
            [
                'company_id' => $company->id,
                'email' => 'buyer'.($index + 1).'@crm-demo.test',
            ],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'crm_company_id' => $account->id,
                'assigned_user_id' => $sales->id,
                'first_name' => ['Aarav', 'Meera', 'Kabir', 'Nisha'][$index],
                'last_name' => ['Shah', 'Iyer', 'Kapoor', 'Menon'][$index],
                'job_title' => 'Retail Operations Lead',
                'phone' => '+91 90000 20'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'preferred_contact_method' => PreferredContactMethod::WhatsApp->value,
                'is_primary' => true,
            ],
        ));

        $leadTitles = [
            'POS rollout for flagship fashion chain',
            'Multi-store grocery inventory sync',
            'Footwear stock visibility upgrade',
            'Beauty retail CRM and POS evaluation',
            'Franchise billing and branch controls',
            'Barcode-led checkout modernization',
            'Loyalty and repeat purchase tracking',
            'Centralized purchasing workflow',
            'Retail analytics dashboard review',
            'WhatsApp order follow-up process',
            'Store staff role controls',
            'Seasonal product catalog migration',
            'Wholesale-to-retail transition',
            'Cloud POS replacement',
            'Branch audit and sales controls',
            'Integrated website enquiry flow',
            'Mall kiosk quick billing',
            'Enterprise retail rollout discovery',
            'Inventory variance reduction program',
            'Executive demo for retail group',
        ];

        collect($leadTitles)->each(function (string $title, int $index) use ($company, $branch, $admin, $sales, $sources, $statuses, $crmCompanies, $contacts, $tags): void {
            $status = $statuses->values()[$index % $statuses->count()];
            $source = $sources->values()[$index % $sources->count()];
            $account = $crmCompanies[$index % $crmCompanies->count()];
            $contact = $contacts[$index % $contacts->count()];

            $lead = CrmLead::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'title' => $title,
                ],
                [
                    'branch_id' => $branch->id,
                    'crm_company_id' => $index < 10 ? $account->id : null,
                    'crm_contact_id' => $index < 10 ? $contact->id : null,
                    'source_id' => $source->id,
                    'status_id' => $status->id,
                    'assigned_user_id' => $sales->id,
                    'created_by' => $admin->id,
                    'business_name' => $account->name,
                    'contact_name' => $contact->fullName(),
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'industry' => $account->industry,
                    'city' => $account->city,
                    'country' => $account->country,
                    'business_type' => $account->industry,
                    'interested_modules' => ['crm', 'pos', 'inventory'],
                    'expected_value' => 125000 + ($index * 17500),
                    'expected_timeline' => ['This month', '30 days', 'This quarter'][$index % 3],
                    'currency' => 'INR',
                    'priority' => [LeadPriority::Medium, LeadPriority::High, LeadPriority::Urgent, LeadPriority::Low][$index % 4]->value,
                    'lead_score' => min(95, 35 + ($index * 3)),
                    'next_follow_up_at' => now()->addDays(($index % 8) - 2)->setTime(11, 0),
                    'last_contacted_at' => now()->subDays($index % 5),
                    'description' => 'Seeded CRM opportunity for Phase 2 dashboard, pipeline, and follow-up workflows.',
                    'metadata' => ['seeded' => true, 'intake_version' => 'v1'],
                    'converted_at' => $status->is_won ? now()->subDays(3) : null,
                    'won_at' => $status->is_won ? now()->subDays(3) : null,
                    'lost_at' => $status->is_lost ? now()->subDays(2) : null,
                ],
            );

            $lead->tags()->sync($tags->values()->take(($index % 3) + 1)->pluck('id')->all());

            CrmActivity::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'crm_lead_id' => $lead->id,
                    'subject' => 'Follow up on '.$lead->title,
                ],
                [
                    'crm_company_id' => $lead->crm_company_id,
                    'crm_contact_id' => $lead->crm_contact_id,
                    'assigned_user_id' => $sales->id,
                    'created_by' => $admin->id,
                    'type' => [ActivityType::Call, ActivityType::Meeting, ActivityType::Email, ActivityType::WhatsApp][$index % 4]->value,
                    'description' => 'Seeded follow-up activity for CRM demo data.',
                    'scheduled_at' => $lead->next_follow_up_at,
                    'completed_at' => $index % 6 === 0 ? now()->subDay() : null,
                    'outcome' => $index % 6 === 0 ? 'Discovery completed' : null,
                    'priority' => $lead->priority->value,
                ],
            );
        });

        $eventLog = DomainEventLog::updateOrCreate(
            ['correlation_id' => 'seed:system.settings.updated'],
            [
                'company_id' => $company->id,
                'user_id' => $admin->id,
                'event_key' => 'system.settings.updated',
                'event_class' => 'seed',
                'aggregate_type' => 'settings',
                'aggregate_id' => null,
                'causation_id' => null,
                'payload' => ['section' => 'notifications', 'keys' => ['lead_alerts']],
                'occurred_at' => now()->subHour(),
                'processed_at' => now()->subHour(),
                'status' => 'processed',
            ],
        );

        DatabaseNotification::query()->updateOrCreate(
            ['id' => '11111111-1111-4111-8111-111111111111'],
            [
                'type' => PlatformNotification::class,
                'notifiable_type' => $admin->getMorphClass(),
                'notifiable_id' => $admin->id,
                'data' => [
                    'title' => 'Notification Center ready',
                    'message' => 'Phase 2.5 demo notification preferences and delivery logs are available.',
                    'event_key' => 'system.settings.updated',
                    'severity' => 'info',
                    'action_url' => route('notifications.index'),
                ],
                'read_at' => null,
            ],
        );

        NotificationDelivery::updateOrCreate(
            [
                'company_id' => $company->id,
                'domain_event_log_id' => $eventLog->id,
                'event_key' => 'system.settings.updated',
                'channel' => 'database',
                'recipient' => (string) $admin->id,
            ],
            [
                'user_id' => $admin->id,
                'notification_id' => '11111111-1111-4111-8111-111111111111',
                'status' => 'delivered',
                'attempt_count' => 1,
                'payload' => ['title' => 'Notification Center ready'],
                'sent_at' => now()->subHour(),
                'delivered_at' => now()->subHour(),
            ],
        );

        $webhookEndpoint = WebhookEndpoint::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Demo automation endpoint',
            ],
            [
                'url' => 'https://automation.example.com/retailpos/events',
                'secret' => 'whsec_demo_secret_rotatable',
                'subscribed_events' => ['crm.lead.created', 'crm.lead.assigned', 'cms.page.published'],
                'is_active' => false,
            ],
        );

        WebhookDelivery::updateOrCreate(
            [
                'company_id' => $company->id,
                'webhook_endpoint_id' => $webhookEndpoint->id,
                'domain_event_log_id' => $eventLog->id,
                'event_key' => 'system.settings.updated',
            ],
            [
                'payload' => ['event_key' => 'system.settings.updated', 'data' => ['section' => 'notifications']],
                'status' => 'failed',
                'response_code' => 410,
                'response_body' => 'Seeded disabled endpoint sample.',
                'attempt_count' => 1,
                'failed_at' => now()->subMinutes(45),
                'next_retry_at' => now()->addMinutes(15),
            ],
        );

        $units = collect([
            ['name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'decimal_allowed' => false, 'is_system' => true],
            ['name' => 'Pair', 'short_code' => 'PAIR', 'type' => 'quantity', 'decimal_allowed' => false, 'is_system' => true],
            ['name' => 'Kilogram', 'short_code' => 'KG', 'type' => 'weight', 'decimal_allowed' => true, 'is_system' => true],
        ])->mapWithKeys(fn (array $unit): array => [
            $unit['short_code'] => InventoryUnit::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'short_code' => $unit['short_code'],
                ],
                $unit + [
                    'company_id' => $company->id,
                    'conversion_factor' => 1,
                    'is_active' => true,
                ],
            ),
        ]);

        $taxRates = collect([
            ['name' => 'GST 0%', 'rate' => 0],
            ['name' => 'GST 5%', 'rate' => 5],
            ['name' => 'GST 12%', 'rate' => 12],
            ['name' => 'GST 18%', 'rate' => 18],
        ])->mapWithKeys(fn (array $tax): array => [
            $tax['name'] => InventoryTaxRate::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $tax['name'],
                ],
                $tax + [
                    'company_id' => $company->id,
                    'tax_type' => 'gst',
                    'country' => 'India',
                    'is_default' => $tax['rate'] === 18,
                    'is_active' => true,
                ],
            ),
        ]);

        $inventoryCategories = collect(['Footwear', 'Apparel', 'Grocery', 'Electronics'])
            ->mapWithKeys(fn (string $name, int $index): array => [
                Str::slug($name) => InventoryCategory::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'company_id' => $company->id,
                        'name' => $name,
                        'description' => 'Demo inventory category for '.$name.'.',
                        'sort_order' => $index + 1,
                        'is_active' => true,
                    ],
                ),
            ]);

        $brands = collect(['StrideLine', 'Urban Threads', 'FreshMart', 'Crystal'])
            ->mapWithKeys(fn (string $name): array => [
                Str::slug($name) => InventoryBrand::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'company_id' => $company->id,
                        'name' => $name,
                        'description' => 'Demo inventory brand.',
                        'is_active' => true,
                    ],
                ),
            ]);

        $sizeAttribute = ProductAttribute::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'size'],
            ['company_id' => $company->id, 'name' => 'Size', 'type' => 'select', 'is_active' => true, 'sort_order' => 1],
        );
        $colorAttribute = ProductAttribute::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'color'],
            ['company_id' => $company->id, 'name' => 'Color', 'type' => 'select', 'is_active' => true, 'sort_order' => 2],
        );

        $attributeValues = collect([
            [$sizeAttribute, '8'],
            [$sizeAttribute, '9'],
            [$sizeAttribute, '10'],
            [$colorAttribute, 'Black'],
            [$colorAttribute, 'Tan'],
            [$colorAttribute, 'Blue'],
        ])->mapWithKeys(fn (array $item, int $index): array => [
            Str::slug($item[0]->slug.'-'.$item[1]) => ProductAttributeValue::updateOrCreate(
                [
                    'attribute_id' => $item[0]->id,
                    'slug' => Str::slug($item[1]),
                ],
                [
                    'company_id' => $company->id,
                    'attribute_id' => $item[0]->id,
                    'value' => $item[1],
                    'slug' => Str::slug($item[1]),
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            ),
        ]);

        $products = collect([
            ['name' => 'StrideLine Runner Shoe', 'sku' => 'DEMO-SHOE-001', 'barcode' => '890100000001', 'category' => 'footwear', 'brand' => 'strideline', 'unit' => 'PAIR', 'tax' => 'GST 18%', 'selling_price' => 2499, 'cost_price' => 1450, 'track_inventory' => true, 'has_variants' => true, 'type' => 'variant_parent'],
            ['name' => 'Urban Cotton Tee', 'sku' => 'DEMO-TEE-001', 'barcode' => '890100000002', 'category' => 'apparel', 'brand' => 'urban-threads', 'unit' => 'PCS', 'tax' => 'GST 12%', 'selling_price' => 799, 'cost_price' => 320, 'track_inventory' => true, 'has_variants' => false, 'type' => 'simple'],
            ['name' => 'FreshMart Organic Rice 5kg', 'sku' => 'DEMO-RICE-005', 'barcode' => '890100000003', 'category' => 'grocery', 'brand' => 'freshmart', 'unit' => 'KG', 'tax' => 'GST 5%', 'selling_price' => 640, 'cost_price' => 430, 'track_inventory' => true, 'has_variants' => false, 'type' => 'simple'],
            ['name' => 'Crystal Barcode Scanner', 'sku' => 'DEMO-SCAN-001', 'barcode' => '890100000004', 'category' => 'electronics', 'brand' => 'crystal', 'unit' => 'PCS', 'tax' => 'GST 18%', 'selling_price' => 3499, 'cost_price' => 2200, 'track_inventory' => true, 'has_variants' => false, 'type' => 'simple'],
        ])->mapWithKeys(fn (array $item): array => [
            $item['sku'] => Product::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'sku' => $item['sku'],
                ],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'category_id' => $inventoryCategories[$item['category']]->id,
                    'brand_id' => $brands[$item['brand']]->id,
                    'unit_id' => $units[$item['unit']]->id,
                    'tax_rate_id' => $taxRates[$item['tax']]->id,
                    'type' => $item['type'],
                    'name' => $item['name'],
                    'slug' => Str::slug($item['name']),
                    'barcode' => $item['barcode'],
                    'hsn_code' => 'DEMO'.substr($item['sku'], -3),
                    'description' => 'Demo product seeded for Phase 3 inventory workflows.',
                    'cost_price' => $item['cost_price'],
                    'selling_price' => $item['selling_price'],
                    'mrp' => $item['selling_price'] + 300,
                    'purchase_price' => $item['cost_price'],
                    'track_inventory' => $item['track_inventory'],
                    'allow_negative_stock' => false,
                    'has_variants' => $item['has_variants'],
                    'is_variant' => false,
                    'status' => 'active',
                    'is_active' => true,
                ],
            ),
        ]);

        $variantParent = $products['DEMO-SHOE-001'];
        collect([
            ['name' => 'StrideLine Runner Shoe / 8 Black', 'sku' => 'DEMO-SHOE-8-BLK', 'barcode' => '890100000081', 'size' => 'size-8', 'color' => 'color-black'],
            ['name' => 'StrideLine Runner Shoe / 9 Tan', 'sku' => 'DEMO-SHOE-9-TAN', 'barcode' => '890100000092', 'size' => 'size-9', 'color' => 'color-tan'],
        ])->each(function (array $variant) use ($company, $branch, $variantParent, $units, $taxRates, $inventoryCategories, $brands, $attributeValues): void {
            $product = Product::updateOrCreate(
                ['company_id' => $company->id, 'sku' => $variant['sku']],
                [
                    'company_id' => $company->id,
                    'branch_id' => $branch->id,
                    'category_id' => $inventoryCategories['footwear']->id,
                    'brand_id' => $brands['strideline']->id,
                    'unit_id' => $units['PAIR']->id,
                    'tax_rate_id' => $taxRates['GST 18%']->id,
                    'parent_product_id' => $variantParent->id,
                    'type' => 'variant',
                    'name' => $variant['name'],
                    'slug' => Str::slug($variant['name']),
                    'barcode' => $variant['barcode'],
                    'selling_price' => 2499,
                    'cost_price' => 1450,
                    'track_inventory' => true,
                    'allow_negative_stock' => false,
                    'has_variants' => false,
                    'is_variant' => true,
                    'variant_name' => str($variant['name'])->after('/ ')->toString(),
                    'status' => 'active',
                    'is_active' => true,
                ],
            );

            $product->attributeValues()->sync([
                $attributeValues[$variant['size']]->id => ['attribute_id' => $attributeValues[$variant['size']]->attribute_id],
                $attributeValues[$variant['color']]->id => ['attribute_id' => $attributeValues[$variant['color']]->attribute_id],
            ]);
        });

        $warehouse = Warehouse::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-MAIN'],
            [
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Demo Main Warehouse',
                'type' => 'store',
                'address_line_1' => 'MG Road Flagship Store',
                'city' => 'Bengaluru',
                'state' => 'Karnataka',
                'country' => 'India',
                'contact_name' => 'Demo Stock Manager',
                'phone' => '+91 98765 43212',
                'is_primary' => true,
                'is_active' => true,
            ],
        );

        $location = StockLocation::updateOrCreate(
            ['warehouse_id' => $warehouse->id, 'code' => 'A1-R1-B1'],
            [
                'company_id' => $company->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'Demo Aisle A1 Rack R1 Bin B1',
                'type' => 'bin',
                'aisle' => 'A1',
                'rack' => 'R1',
                'shelf' => 'S1',
                'bin' => 'B1',
                'is_active' => true,
            ],
        );

        Product::query()->where('company_id', $company->id)->where('track_inventory', true)->get()->each(function (Product $product, int $index) use ($company, $branch, $warehouse, $location, $admin): void {
            $quantity = [12, 4, 28, 0, 6, 3][$index % 6];
            $level = StockLevel::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'warehouse_id' => $warehouse->id,
                    'stock_location_id' => $location->id,
                    'product_id' => $product->id,
                ],
                [
                    'branch_id' => $branch->id,
                    'quantity_on_hand' => $quantity,
                    'quantity_reserved' => $index % 2,
                    'quantity_available' => max(0, $quantity - ($index % 2)),
                    'reorder_point' => 5,
                    'reorder_quantity' => 12,
                    'minimum_stock' => 3,
                    'maximum_stock' => 50,
                    'safety_stock' => 2,
                    'supplier_lead_time_days' => 7,
                    'average_daily_sales' => 1.5,
                    'last_stock_movement_at' => now()->subDays($index),
                ],
            );

            StockMovement::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'warehouse_id' => $warehouse->id,
                    'stock_location_id' => $location->id,
                    'product_id' => $product->id,
                    'movement_type' => 'opening',
                ],
                [
                    'branch_id' => $branch->id,
                    'direction' => 'initial',
                    'quantity' => $level->quantity_on_hand,
                    'quantity_before' => 0,
                    'quantity_after' => $level->quantity_on_hand,
                    'unit_cost' => $product->cost_price,
                    'reason' => 'Seeded opening stock',
                    'notes' => 'Demo data for Phase 3 inventory foundation.',
                    'created_by' => $admin->id,
                    'occurred_at' => now()->subDays($index + 1),
                ],
            );
        });

        $posCustomer = $demoCustomers->first();
        $posSale = PosSale::updateOrCreate(
            ['company_id' => $company->id, 'sale_number' => 'POS-DEMO-0001'],
            ['branch_id' => $branch->id, 'customer_id' => $posCustomer->id, 'status' => 'completed', 'subtotal' => 1438, 'discount_amount' => 40, 'tax_amount' => 0, 'total_amount' => 1398, 'paid_amount' => 1400, 'change_amount' => 2, 'device_type' => 'desktop', 'completed_by' => $sales->id, 'completed_at' => now()->subDays(5), 'notes' => 'Demo-only POS sale for customer suggestion foundations.'],
        );
        $demoSaleItems = [
            ['product' => $products['DEMO-TEE-001'], 'quantity' => 1, 'unit_price' => 799],
            ['product' => $products['DEMO-RICE-005'], 'quantity' => 1, 'unit_price' => 640],
        ];
        foreach ($demoSaleItems as $item) {
            PosSaleItem::updateOrCreate(
                ['pos_sale_id' => $posSale->id, 'product_id' => $item['product']->id],
                ['company_id' => $company->id, 'category_id' => $item['product']->category_id, 'product_name' => $item['product']->name, 'sku' => $item['product']->sku, 'barcode' => $item['product']->barcode, 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'discount_amount' => 0, 'tax_amount' => 0, 'line_total' => $item['quantity'] * $item['unit_price']],
            );
            CustomerProductSummary::updateOrCreate(
                ['customer_id' => $posCustomer->id, 'product_id' => $item['product']->id],
                ['company_id' => $company->id, 'category_id' => $item['product']->category_id, 'purchase_count' => 5, 'quantity_purchased' => 7, 'total_spent' => $item['quantity'] * $item['unit_price'] * 5, 'first_purchased_at' => now()->subMonths(4), 'last_purchased_at' => now()->subDays(5)],
            );
        }
        PosProductPairSummary::updateOrCreate(['company_id' => $company->id, 'product_id' => $products['DEMO-TEE-001']->id, 'related_product_id' => $products['DEMO-RICE-005']->id], ['co_purchase_count' => 4, 'last_purchased_together_at' => now()->subDays(5)]);
        PosProductPairSummary::updateOrCreate(['company_id' => $company->id, 'product_id' => $products['DEMO-RICE-005']->id, 'related_product_id' => $products['DEMO-TEE-001']->id], ['co_purchase_count' => 4, 'last_purchased_together_at' => now()->subDays(5)]);
        PosPayment::updateOrCreate(['pos_sale_id' => $posSale->id, 'payment_method' => 'cash'], ['company_id' => $company->id, 'amount' => 1400, 'paid_at' => now()->subDays(5), 'created_by' => $sales->id]);

        $template = BarcodeLabelTemplate::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Demo Retail Shelf Label'],
            [
                'company_id' => $company->id,
                'description' => 'Demo label template for Phase 3 barcode preview.',
                'industry_type' => 'Retail',
                'paper_size' => 'A4',
                'label_width_mm' => 50,
                'label_height_mm' => 25,
                'columns' => 2,
                'rows' => 10,
                'gap_horizontal_mm' => 3,
                'gap_vertical_mm' => 2,
                'margin_top_mm' => 8,
                'margin_right_mm' => 8,
                'margin_bottom_mm' => 8,
                'margin_left_mm' => 8,
                'barcode_type' => 'CODE128',
                'font_size' => 10,
                'show_product_name' => true,
                'show_sku' => true,
                'show_barcode_text' => true,
                'show_price' => true,
                'show_mrp' => false,
                'show_company_name' => true,
                'is_default' => true,
                'is_active' => true,
            ],
        );

        $printBatch = BarcodePrintBatch::updateOrCreate(
            ['company_id' => $company->id, 'batch_number' => 'BC-DEMO-0001'],
            [
                'template_id' => $template->id,
                'title' => 'Demo shelf label batch',
                'created_by' => $admin->id,
                'status' => 'draft',
                'total_labels' => 6,
            ],
        );

        $products->values()->take(2)->each(fn (Product $product) => $printBatch->items()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'quantity' => 3,
                'price_override' => null,
                'label_data' => ['name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price' => $product->selling_price],
            ],
        ));

        $reorderRule = ReorderRule::updateOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'product_id' => $products['DEMO-TEE-001']->id],
            [
                'branch_id' => $branch->id,
                'minimum_stock' => 3,
                'maximum_stock' => 40,
                'reorder_point' => 5,
                'reorder_quantity' => 15,
                'safety_stock' => 2,
                'supplier_lead_time_days' => 5,
                'average_daily_sales' => 2,
                'seasonal_factor' => 1,
                'auto_generate_purchase_request' => false,
                'requires_approval' => true,
                'is_active' => true,
            ],
        );

        ReorderSuggestion::updateOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'product_id' => $reorderRule->product_id, 'status' => 'pending'],
            [
                'branch_id' => $branch->id,
                'current_stock' => 4,
                'available_stock' => 3,
                'reorder_point' => $reorderRule->reorder_point,
                'suggested_quantity' => $reorderRule->reorder_quantity,
                'stockout_risk_level' => 'medium',
                'estimated_stockout_date' => now()->addDays(2)->toDateString(),
                'reason' => 'Demo suggestion: available stock is at or below reorder point.',
            ],
        );

        PurchaseSettings::updateOrCreate(
            ['company_id' => $company->id],
            [
                'po_prefix' => 'PO',
                'pr_prefix' => 'PR',
                'grn_prefix' => 'GRN',
                'return_prefix' => 'PRN',
                'next_po_number' => 25,
                'next_pr_number' => 18,
                'next_grn_number' => 12,
                'next_return_number' => 6,
                'require_po_approval' => true,
                'require_purchase_request_approval' => true,
                'require_return_approval' => true,
                'default_payment_terms' => 'Net 15',
                'allow_receive_without_po' => false,
                'auto_create_pr_from_reorder' => false,
            ],
        );

        $suppliers = collect([
            ['code' => 'SUP-STRIDE', 'name' => 'StrideLine Wholesale', 'supplier_type' => 'wholesaler', 'email' => 'supply@strideline.test', 'phone' => '+91 90000 31001', 'lead_time_days' => 5, 'manual_rating' => 86],
            ['code' => 'SUP-URBAN', 'name' => 'Urban Threads Distribution', 'supplier_type' => 'distributor', 'email' => 'orders@urbanthreads.test', 'phone' => '+91 90000 31002', 'lead_time_days' => 4, 'manual_rating' => 82],
            ['code' => 'SUP-FRESH', 'name' => 'FreshMart Staples Supply', 'supplier_type' => 'manufacturer', 'email' => 'procurement@freshmart.test', 'phone' => '+91 90000 31003', 'lead_time_days' => 3, 'manual_rating' => 78],
        ])->mapWithKeys(fn (array $supplier): array => [
            $supplier['code'] => Supplier::updateOrCreate(
                ['company_id' => $company->id, 'code' => $supplier['code']],
                $supplier + [
                    'company_id' => $company->id,
                    'display_name' => $supplier['name'],
                    'gstin' => '29DEMO'.substr($supplier['code'], -3).'1Z5',
                    'payment_terms' => 'Net 15',
                    'credit_limit' => 250000,
                    'default_currency' => 'INR',
                    'service_notes' => 'Demo supplier service profile for Phase 4 scoring.',
                    'notes' => 'Seeded supplier for purchase foundation.',
                    'is_active' => true,
                ],
            ),
        ]);

        $suppliers->each(function (Supplier $supplier): void {
            SupplierContact::updateOrCreate(
                ['company_id' => $supplier->company_id, 'supplier_id' => $supplier->id, 'email' => 'primary.'.$supplier->code.'@supplier-demo.test'],
                [
                    'name' => 'Primary Contact',
                    'designation' => 'Account Manager',
                    'phone' => $supplier->phone,
                    'whatsapp' => $supplier->phone,
                    'is_primary' => true,
                    'is_active' => true,
                ],
            );

            SupplierAddress::updateOrCreate(
                ['company_id' => $supplier->company_id, 'supplier_id' => $supplier->id, 'type' => 'office'],
                [
                    'address_line_1' => 'Demo Supplier Office',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'country' => 'India',
                    'postal_code' => '560001',
                    'is_default' => true,
                ],
            );
        });

        $supplierMappings = collect([
            ['supplier' => 'SUP-STRIDE', 'product' => 'DEMO-SHOE-001', 'purchase_price' => 1425, 'lead_time_days' => 5, 'is_preferred' => true],
            ['supplier' => 'SUP-URBAN', 'product' => 'DEMO-TEE-001', 'purchase_price' => 315, 'lead_time_days' => 4, 'is_preferred' => true],
            ['supplier' => 'SUP-FRESH', 'product' => 'DEMO-RICE-005', 'purchase_price' => 420, 'lead_time_days' => 3, 'is_preferred' => true],
        ])->map(function (array $mapping) use ($company, $suppliers, $products, $taxRates): SupplierProduct {
            return SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $suppliers[$mapping['supplier']]->id,
                    'product_id' => $products[$mapping['product']]->id,
                ],
                [
                    'company_id' => $company->id,
                    'supplier_sku' => $mapping['supplier'].'-'.$products[$mapping['product']]->sku,
                    'supplier_product_name' => $products[$mapping['product']]->name,
                    'purchase_price' => $mapping['purchase_price'],
                    'mrp' => $products[$mapping['product']]->mrp,
                    'minimum_order_quantity' => 5,
                    'lead_time_days' => $mapping['lead_time_days'],
                    'tax_rate_id' => $taxRates->first()->id,
                    'is_preferred' => $mapping['is_preferred'],
                    'is_active' => true,
                    'last_purchase_price' => $mapping['purchase_price'],
                    'last_purchased_at' => now()->subDays(4),
                    'price_score' => 75,
                    'delivery_score' => 88,
                    'return_quality_score' => 94,
                    'service_score' => 82,
                    'overall_score' => 84.75,
                ],
            );
        });

        $purchaseRequest = PurchaseRequest::updateOrCreate(
            ['company_id' => $company->id, 'request_number' => 'PR-000017'],
            [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'source_type' => 'reorder_suggestion',
                'source_id' => $reorderRule->id,
                'status' => 'approved',
                'priority' => 'high',
                'requested_by' => $manager->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(2),
                'notes' => 'Seeded purchase request from reorder suggestion.',
                'expected_by' => now()->addDays(5)->toDateString(),
            ],
        );

        $purchaseRequest->items()->updateOrCreate(
            ['product_id' => $products['DEMO-TEE-001']->id],
            [
                'supplier_id' => $suppliers['SUP-URBAN']->id,
                'requested_quantity' => 15,
                'approved_quantity' => 15,
                'estimated_price' => 315,
                'expected_by' => now()->addDays(5)->toDateString(),
                'notes' => 'Replenish cotton tees before weekend demand.',
            ],
        );

        $purchaseOrder = PurchaseOrder::updateOrCreate(
            ['company_id' => $company->id, 'po_number' => 'PO-000024'],
            [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $suppliers['SUP-URBAN']->id,
                'purchase_request_id' => $purchaseRequest->id,
                'status' => 'sent',
                'order_date' => now()->subDays(2)->toDateString(),
                'expected_delivery_date' => now()->addDays(2)->toDateString(),
                'currency' => 'INR',
                'subtotal' => 4725,
                'discount_total' => 0,
                'tax_total' => 567,
                'shipping_total' => 0,
                'grand_total' => 5292,
                'payment_terms' => 'Net 15',
                'notes' => 'Seeded purchase order for Phase 4.',
                'created_by' => $manager->id,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDay(),
                'sent_at' => now()->subDay(),
            ],
        );

        $purchaseOrderItem = $purchaseOrder->items()->updateOrCreate(
            ['product_id' => $products['DEMO-TEE-001']->id],
            [
                'supplier_product_id' => $supplierMappings->firstWhere('product_id', $products['DEMO-TEE-001']->id)?->id,
                'product_name_snapshot' => $products['DEMO-TEE-001']->name,
                'sku_snapshot' => $products['DEMO-TEE-001']->sku,
                'ordered_quantity' => 15,
                'received_quantity' => 5,
                'pending_quantity' => 10,
                'unit_price' => 315,
                'discount_amount' => 0,
                'tax_rate' => 12,
                'tax_amount' => 567,
                'line_total' => 5292,
                'notes' => 'Partial receipt expected.',
            ],
        );

        $goodsReceipt = GoodsReceipt::updateOrCreate(
            ['company_id' => $company->id, 'grn_number' => 'GRN-000011'],
            [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $suppliers['SUP-URBAN']->id,
                'purchase_order_id' => $purchaseOrder->id,
                'receipt_date' => now()->subDay()->toDateString(),
                'status' => 'partially_accepted',
                'received_by' => $manager->id,
                'checked_by' => $admin->id,
                'checked_at' => now()->subDay(),
                'supplier_invoice_number' => 'INV-URBAN-1024',
                'supplier_invoice_date' => now()->subDay()->toDateString(),
                'notes' => 'Seeded partial GRN for Phase 4.',
            ],
        );

        $goodsReceipt->items()->updateOrCreate(
            ['purchase_order_item_id' => $purchaseOrderItem->id, 'product_id' => $products['DEMO-TEE-001']->id],
            [
                'stock_location_id' => $location->id,
                'ordered_quantity' => 15,
                'received_quantity' => 6,
                'accepted_quantity' => 5,
                'rejected_quantity' => 1,
                'unit_cost' => 315,
                'batch_number' => 'TEE-BATCH-01',
                'notes' => 'One piece rejected for demo quality issue.',
            ],
        );

        $purchaseReturn = PurchaseReturn::updateOrCreate(
            ['company_id' => $company->id, 'return_number' => 'PRN-000005'],
            [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'supplier_id' => $suppliers['SUP-URBAN']->id,
                'goods_receipt_id' => $goodsReceipt->id,
                'status' => 'completed',
                'return_date' => now()->toDateString(),
                'reason' => 'Quality rejection',
                'notes' => 'Seeded return for rejected demo item.',
                'created_by' => $manager->id,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );

        $purchaseReturn->items()->updateOrCreate(
            ['product_id' => $products['DEMO-TEE-001']->id],
            [
                'stock_location_id' => $location->id,
                'quantity' => 1,
                'unit_cost' => 315,
                'reason' => 'Rejected on receipt.',
            ],
        );

        collect([
            [$purchaseRequest, PurchaseRequest::class, 'approved', 'pending_review', 'approved'],
            [$purchaseOrder, PurchaseOrder::class, 'approved', 'pending_approval', 'approved'],
            [$purchaseReturn, PurchaseReturn::class, 'completed', 'approved', 'completed'],
        ])->each(fn (array $log) => PurchaseApprovalLog::updateOrCreate(
            [
                'company_id' => $company->id,
                'approvable_type' => $log[1],
                'approvable_id' => $log[0]->id,
                'action' => $log[2],
            ],
            [
                'from_status' => $log[3],
                'to_status' => $log[4],
                'user_id' => $admin->id,
                'comments' => 'Seeded approval history.',
            ],
        ));

        StockMovement::updateOrCreate(
            [
                'company_id' => $company->id,
                'warehouse_id' => $warehouse->id,
                'stock_location_id' => $location->id,
                'product_id' => $products['DEMO-TEE-001']->id,
                'movement_type' => 'purchase',
                'reference_type' => GoodsReceipt::class,
                'reference_id' => $goodsReceipt->id,
            ],
            [
                'branch_id' => $branch->id,
                'direction' => 'in',
                'quantity' => 5,
                'quantity_before' => 4,
                'quantity_after' => 9,
                'unit_cost' => 315,
                'reason' => 'Seeded goods receipt',
                'notes' => 'Demo Phase 4 purchase movement.',
                'created_by' => $admin->id,
                'occurred_at' => now()->subDay(),
            ],
        );

        StockMovement::updateOrCreate(
            [
                'company_id' => $company->id,
                'warehouse_id' => $warehouse->id,
                'stock_location_id' => $location->id,
                'product_id' => $products['DEMO-TEE-001']->id,
                'movement_type' => 'purchase_return',
                'reference_type' => PurchaseReturn::class,
                'reference_id' => $purchaseReturn->id,
            ],
            [
                'branch_id' => $branch->id,
                'direction' => 'out',
                'quantity' => 1,
                'quantity_before' => 9,
                'quantity_after' => 8,
                'unit_cost' => 315,
                'reason' => 'Seeded supplier return',
                'notes' => 'Demo Phase 4 purchase return movement.',
                'created_by' => $admin->id,
                'occurred_at' => now(),
            ],
        );

        $suppliers->each(fn (Supplier $supplier) => SupplierScoreSnapshot::updateOrCreate(
            ['company_id' => $company->id, 'supplier_id' => $supplier->id, 'calculated_at' => now()->startOfDay()],
            [
                'product_performance_score' => null,
                'price_score' => 75,
                'delivery_score' => max(40, min(95, 100 - ((int) $supplier->lead_time_days * 2))),
                'return_quality_score' => $supplier->id === $suppliers['SUP-URBAN']->id ? 90 : 96,
                'service_score' => $supplier->manual_rating,
                'overall_score' => $supplier->id === $suppliers['SUP-URBAN']->id ? 83.75 : 85.5,
                'purchase_value' => $supplier->id === $suppliers['SUP-URBAN']->id ? 5292 : 0,
                'received_quantity' => $supplier->id === $suppliers['SUP-URBAN']->id ? 5 : 0,
                'rejected_quantity' => $supplier->id === $suppliers['SUP-URBAN']->id ? 1 : 0,
                'returned_quantity' => $supplier->id === $suppliers['SUP-URBAN']->id ? 1 : 0,
                'delayed_delivery_count' => 0,
                'notes' => 'Seeded rule-based score. Product sales score is future-ready and does not use fake POS sales.',
            ],
        ));

        collect([
            ['event_key' => 'purchase.request.submitted', 'channel' => 'database', 'name' => 'Purchase request approval', 'subject' => 'Purchase request needs approval', 'body' => 'Purchase request {{ request_number }} is waiting for approval.'],
            ['event_key' => 'purchase.order.submitted', 'channel' => 'database', 'name' => 'Purchase order approval', 'subject' => 'Purchase order needs approval', 'body' => 'Purchase order {{ po_number }} is waiting for approval.'],
            ['event_key' => 'purchase.goods_received', 'channel' => 'database', 'name' => 'Goods received', 'subject' => 'Goods received: {{ grn_number }}', 'body' => 'Goods receipt {{ grn_number }} has been posted to stock.'],
            ['event_key' => 'purchase.reorder_request.created', 'channel' => 'database', 'name' => 'Reorder request created', 'subject' => 'Reorder converted to purchase request', 'body' => 'Purchase request {{ request_number }} was created from reorder suggestions.'],
            ['event_key' => 'purchase.supplier.score_updated', 'channel' => 'database', 'name' => 'Supplier score updated', 'subject' => 'Supplier score updated', 'body' => 'Supplier score updated to {{ overall_score }}.'],
        ])->each(fn (array $template) => NotificationTemplate::updateOrCreate(
            [
                'company_id' => null,
                'event_key' => $template['event_key'],
                'channel' => $template['channel'],
                'locale' => 'en',
            ],
            $template + [
                'company_id' => null,
                'locale' => 'en',
                'is_system' => true,
                'is_active' => true,
                'version' => 1,
            ],
        ));

        $channels = collect([
            ['name' => 'Flagship Store', 'code' => 'STORE', 'type' => 'store', 'is_online' => false, 'sync_enabled' => false],
            ['name' => 'RetailPOS Website', 'code' => 'WEB', 'type' => 'website', 'is_online' => true, 'sync_enabled' => false],
            ['name' => 'Marketplace Placeholder', 'code' => 'MKT', 'type' => 'marketplace', 'is_online' => true, 'sync_enabled' => false],
        ])->mapWithKeys(fn (array $channel, int $index): array => [
            $channel['code'] => SalesChannel::updateOrCreate(
                ['company_id' => $company->id, 'code' => $channel['code']],
                $channel + [
                    'company_id' => $company->id,
                    'description' => 'Demo internal channel. No external integration is connected in Phase 3.',
                    'is_active' => true,
                    'price_strategy' => 'selling_price',
                    'stock_strategy' => 'available_stock',
                    'sort_order' => $index + 1,
                ],
            ),
        ]);

        $channels->each(function (SalesChannel $channel) use ($company, $warehouse, $products): void {
            $product = $products->values()->first();
            $mapping = ChannelProductMapping::updateOrCreate(
                ['sales_channel_id' => $channel->id, 'product_id' => $product->id],
                [
                    'company_id' => $company->id,
                    'channel_sku' => $channel->code.'-'.$product->sku,
                    'channel_product_name' => $product->name,
                    'channel_price' => $product->selling_price,
                    'channel_mrp' => $product->mrp,
                    'stock_buffer_quantity' => 1,
                    'max_listed_quantity' => 10,
                    'sync_product' => true,
                    'sync_price' => true,
                    'sync_stock' => true,
                    'sync_status' => 'not_synced',
                ],
            );

            ChannelStockLevel::updateOrCreate(
                ['sales_channel_id' => $channel->id, 'product_id' => $mapping->product_id, 'warehouse_id' => $warehouse->id],
                [
                    'company_id' => $company->id,
                    'listed_quantity' => 10,
                    'reserved_quantity' => 1,
                    'available_quantity' => 9,
                    'buffer_quantity' => 1,
                    'sync_status' => 'not_synced',
                ],
            );
        });

        InventorySyncLog::updateOrCreate(
            ['company_id' => $company->id, 'sales_channel_id' => $channels['WEB']->id, 'action' => 'demo_inventory_sync'],
            [
                'status' => 'warning',
                'message' => 'Demo channel sync warning. External adapters are intentionally not connected in Phase 3.',
                'payload_summary' => ['demo' => true, 'products_checked' => 1],
                'started_at' => now()->subMinutes(20),
                'completed_at' => now()->subMinutes(19),
            ],
        );

        PromotionSettings::updateOrCreate(
            ['company_id' => $company->id],
            [
                'allow_stacking' => true,
                'default_priority_strategy' => 'priority_then_benefit',
                'allow_coupon_with_auto_discount' => true,
                'max_discount_percentage_per_bill' => 50,
                'max_discount_amount_per_bill' => 5000,
                'require_approval_for_promotions' => false,
                'show_discount_breakup_on_bill_future' => true,
            ],
        );

        $promotionCampaign = PromotionCampaign::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'demo-festive-retail-offers'],
            [
                'name' => 'Demo Festive Retail Offers',
                'description' => 'Seeded demonstration campaign for Phase 4.5. These are not live customer promotions.',
                'campaign_type' => 'festival',
                'start_at' => now()->subDay(),
                'end_at' => now()->addMonths(2),
                'status' => 'active',
                'priority' => 200,
                'is_active' => true,
                'created_by' => $admin->id,
                'approved_by' => $admin->id,
                'approved_at' => now()->subDay(),
            ],
        );

        $demoRules = collect([
            ['slug' => 'demo-buy-1-get-1-free', 'name' => 'Demo Buy 1 Get 1 Free', 'type' => 'buy_x_get_y', 'discount_type' => 'free_quantity', 'action' => ['action_type' => 'free_quantity', 'buy_quantity' => 1, 'get_quantity' => 1, 'applies_to_same_product' => true], 'product' => 'DEMO-TEE-001'],
            ['slug' => 'demo-buy-1-get-2-free', 'name' => 'Demo Buy 1 Get 2 Free', 'type' => 'buy_x_get_y', 'discount_type' => 'free_quantity', 'action' => ['action_type' => 'free_quantity', 'buy_quantity' => 1, 'get_quantity' => 2, 'applies_to_same_product' => true], 'product' => 'DEMO-RICE-005'],
            ['slug' => 'demo-buy-2-get-1-free', 'name' => 'Demo Buy 2 Get 1 Free', 'type' => 'buy_x_get_y', 'discount_type' => 'free_quantity', 'action' => ['action_type' => 'free_quantity', 'buy_quantity' => 2, 'get_quantity' => 1, 'applies_to_same_product' => true], 'product' => 'DEMO-SCAN-001'],
            ['slug' => 'demo-buy-2-get-3-free', 'name' => 'Demo Buy 2 Get 3 Free', 'type' => 'buy_x_get_y', 'discount_type' => 'free_quantity', 'action' => ['action_type' => 'free_quantity', 'buy_quantity' => 2, 'get_quantity' => 3, 'applies_to_same_product' => true], 'product' => 'DEMO-SHOE-001'],
            ['slug' => 'demo-buy-3-get-20-percent', 'name' => 'Demo Buy 3 Get 20% Off', 'type' => 'quantity_discount', 'discount_type' => 'percentage', 'minimum_quantity' => 3, 'action' => ['action_type' => 'percentage_off', 'discount_percentage' => 20], 'category' => 'apparel'],
            ['slug' => 'demo-bill-1000-get-10-percent', 'name' => 'Demo Bill Above 1000 Get 10% Off', 'type' => 'minimum_bill_discount', 'discount_type' => 'percentage', 'minimum_bill_amount' => 1000, 'action' => ['action_type' => 'percentage_off', 'discount_percentage' => 10]],
            ['slug' => 'demo-apparel-category-15-percent', 'name' => 'Demo Apparel Category 15% Off', 'type' => 'percentage_discount', 'discount_type' => 'percentage', 'action' => ['action_type' => 'percentage_off', 'discount_percentage' => 15], 'category' => 'apparel'],
            ['slug' => 'demo-urban-brand-20-percent', 'name' => 'Demo Urban Threads Brand 20% Off', 'type' => 'percentage_discount', 'discount_type' => 'percentage', 'action' => ['action_type' => 'percentage_off', 'discount_percentage' => 20], 'brand' => 'urban-threads'],
            ['slug' => 'demo-website-100-off', 'name' => 'Demo Website Only 100 Off', 'type' => 'channel_discount', 'discount_type' => 'fixed_amount', 'action' => ['action_type' => 'amount_off', 'discount_value' => 100], 'channel' => 'WEB'],
            ['slug' => 'demo-main-store-50-off', 'name' => 'Demo Main Store 50 Off', 'type' => 'branch_discount', 'discount_type' => 'fixed_amount', 'action' => ['action_type' => 'amount_off', 'discount_value' => 50], 'branch' => true],
            ['slug' => 'demo-festive10-coupon', 'name' => 'Demo FESTIVE10 Coupon', 'type' => 'coupon_discount', 'discount_type' => 'percentage', 'requires_coupon' => true, 'auto_apply' => false, 'action' => ['action_type' => 'percentage_off', 'discount_percentage' => 10]],
        ])->mapWithKeys(function (array $definition, int $index) use ($company, $admin, $promotionCampaign): array {
            $rule = PromotionRule::updateOrCreate(
                ['company_id' => $company->id, 'slug' => $definition['slug']],
                [
                    'campaign_id' => $promotionCampaign->id,
                    'name' => $definition['name'],
                    'description' => 'Demo promotion seeded for Phase 4.5 only.',
                    'promotion_type' => $definition['type'],
                    'discount_type' => $definition['discount_type'],
                    'priority' => 300 - $index,
                    'stackable' => false,
                    'exclusive' => false,
                    'requires_coupon' => $definition['requires_coupon'] ?? false,
                    'auto_apply' => $definition['auto_apply'] ?? true,
                    'start_at' => now()->subDay(),
                    'end_at' => now()->addMonths(2),
                    'minimum_bill_amount' => $definition['minimum_bill_amount'] ?? null,
                    'minimum_quantity' => $definition['minimum_quantity'] ?? null,
                    'status' => 'active',
                    'is_active' => true,
                    'created_by' => $admin->id,
                    'approved_by' => $admin->id,
                    'approved_at' => now()->subDay(),
                ],
            );
            PromotionAction::updateOrCreate(['promotion_rule_id' => $rule->id, 'action_type' => $definition['action']['action_type']], ['company_id' => $company->id] + $definition['action']);
            return [$definition['slug'] => $rule];
        });

        foreach ($demoRules as $slug => $rule) {
            $definition = collect([
                'demo-buy-1-get-1-free' => ['product' => 'DEMO-TEE-001'], 'demo-buy-1-get-2-free' => ['product' => 'DEMO-RICE-005'], 'demo-buy-2-get-1-free' => ['product' => 'DEMO-SCAN-001'], 'demo-buy-2-get-3-free' => ['product' => 'DEMO-SHOE-001'],
                'demo-buy-3-get-20-percent' => ['category' => 'apparel'], 'demo-apparel-category-15-percent' => ['category' => 'apparel'], 'demo-urban-brand-20-percent' => ['brand' => 'urban-threads'], 'demo-website-100-off' => ['channel' => 'WEB'], 'demo-main-store-50-off' => ['branch' => true],
            ])->get($slug, []);
            if (isset($definition['product'])) PromotionProductTarget::updateOrCreate(['promotion_rule_id' => $rule->id, 'product_id' => $products[$definition['product']]->id], ['company_id' => $company->id, 'include_or_exclude' => 'include']);
            if (isset($definition['category'])) PromotionCategoryTarget::updateOrCreate(['promotion_rule_id' => $rule->id, 'category_id' => $inventoryCategories[$definition['category']]->id], ['company_id' => $company->id, 'include_or_exclude' => 'include']);
            if (isset($definition['brand'])) PromotionBrandTarget::updateOrCreate(['promotion_rule_id' => $rule->id, 'brand_id' => $brands[$definition['brand']]->id], ['company_id' => $company->id, 'include_or_exclude' => 'include']);
            if (isset($definition['channel'])) PromotionChannelTarget::updateOrCreate(['promotion_rule_id' => $rule->id, 'sales_channel_id' => $channels[$definition['channel']]->id], ['company_id' => $company->id, 'include_or_exclude' => 'include']);
            if (isset($definition['branch'])) PromotionBranchTarget::updateOrCreate(['promotion_rule_id' => $rule->id, 'branch_id' => $branch->id], ['company_id' => $company->id, 'include_or_exclude' => 'include']);
        }

        PromotionCoupon::updateOrCreate(
            ['company_id' => $company->id, 'code' => 'FESTIVE10'],
            ['promotion_rule_id' => $demoRules['demo-festive10-coupon']->id, 'description' => 'Demo-only coupon for Phase 4.5 validation.', 'usage_limit_total' => 100, 'used_count' => 0, 'start_at' => now()->subDay(), 'end_at' => now()->addMonths(2), 'is_active' => true],
        );

        collect([
            'default_cost_method' => 'weighted_average',
            'low_stock_notifications' => true,
            'allow_negative_stock_default' => false,
            'barcode_price_source' => 'selling_price',
        ])->each(fn (mixed $value, string $key) => Setting::updateOrCreate(
            [
                'company_id' => $company->id,
                'group' => 'inventory',
                'key' => $key,
            ],
            ['value' => ['value' => $value]],
        ));

        collect([
            ['event_key' => 'inventory.stock.low', 'channel' => 'database', 'name' => 'Low stock in-app', 'subject' => 'Low stock: {{ product_name }}', 'body' => '{{ product_name }} has reached the configured stock threshold.'],
            ['event_key' => 'inventory.stock.out', 'channel' => 'database', 'name' => 'Out of stock in-app', 'subject' => 'Out of stock: {{ product_name }}', 'body' => '{{ product_name }} is out of stock.'],
            ['event_key' => 'inventory.reorder.suggested', 'channel' => 'database', 'name' => 'Reorder suggestion in-app', 'subject' => 'Reorder suggestion generated', 'body' => 'A reorder suggestion was generated for product {{ product_id }}.'],
            ['event_key' => 'inventory.channel.sync_warning', 'channel' => 'database', 'name' => 'Channel sync warning in-app', 'subject' => 'Inventory channel warning', 'body' => '{{ message }}'],
            ['event_key' => 'promotion.approval.required', 'channel' => 'database', 'name' => 'Promotion approval required', 'subject' => 'Promotion approval required', 'body' => 'Promotion {{ rule_name }} requires manager review.'],
            ['event_key' => 'promotion.rule.activated', 'channel' => 'database', 'name' => 'Promotion activated', 'subject' => 'Promotion activated: {{ rule_name }}', 'body' => 'Promotion {{ rule_name }} is active.'],
            ['event_key' => 'promotion.coupon.used', 'channel' => 'database', 'name' => 'Promotion coupon used', 'subject' => 'Coupon used: {{ coupon_code }}', 'body' => 'Coupon {{ coupon_code }} was redeemed for ₹{{ discount_amount }}.'],
        ])->each(fn (array $template) => NotificationTemplate::updateOrCreate(
            [
                'company_id' => null,
                'event_key' => $template['event_key'],
                'channel' => $template['channel'],
                'locale' => 'en',
            ],
            $template + [
                'company_id' => null,
                'locale' => 'en',
                'is_system' => true,
                'is_active' => true,
                'version' => 1,
            ],
        ));

        collect([
            [
                'key' => 'demo_app_boot',
                'name' => 'Demo application boot',
                'category' => 'Demo',
                'status' => 'healthy',
                'message' => 'Demonstration snapshot only. Run operations:health-check for live health data.',
                'payload' => ['demo' => true],
            ],
            [
                'key' => 'demo_queue_connection',
                'name' => 'Demo queue connection',
                'category' => 'Demo',
                'status' => 'unknown',
                'message' => 'Demonstration snapshot only. Live queue state is captured by operations:capture-queue-snapshot.',
                'payload' => ['demo' => true],
            ],
        ])->each(fn (array $check) => SystemHealthCheck::updateOrCreate(
            ['key' => $check['key']],
            $check + [
                'company_id' => null,
                'checked_at' => now()->subMinutes(30),
            ],
        ));

        QueueJobSnapshot::updateOrCreate(
            ['queue' => 'demo-default'],
            [
                'pending_count' => 0,
                'failed_count' => 0,
                'processed_count' => null,
                'reserved_count' => 0,
                'captured_at' => now()->subMinutes(15),
            ],
        );
    }
}
