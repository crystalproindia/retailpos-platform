<?php

namespace Database\Seeders;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\PreferredContactMethod;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Cms\CmsFooterProfile;
use App\Models\Cms\CmsHomepageSection;
use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\Cms\CmsSeoSetting;
use App\Models\Cms\CmsSetting;
use App\Models\Company;
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
use App\Models\QueueJobSnapshot;
use App\Models\Setting;
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
                    'subheading' => 'Managed content for '.$section['name'],
                    'content' => 'Editable website content managed from the RetailPOS CMS.',
                    'is_enabled' => true,
                    'sort_order' => $section['sort_order'],
                ],
            );
        });

        collect(config('cms.settings'))->each(function (array $definition, string $key) use ($company): void {
            CmsSetting::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'key' => $key,
                ],
                [
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
            ['label' => 'Pricing', 'url' => '/pricing', 'sort_order' => 3],
            ['label' => 'Contact', 'url' => '/contact', 'sort_order' => 4],
        ])->each(fn (array $item) => CmsMenuItem::updateOrCreate(
            [
                'menu_id' => $headerMenu->id,
                'label' => $item['label'],
            ],
            $item + [
                'is_enabled' => true,
            ],
        ));

        $sources = collect([
            ['name' => 'Website Demo', 'description' => 'Inbound website demo request.', 'tone' => 'success', 'sort_order' => 1],
            ['name' => 'WhatsApp', 'description' => 'WhatsApp enquiry.', 'tone' => 'info', 'sort_order' => 2],
            ['name' => 'Referral', 'description' => 'Partner or customer referral.', 'tone' => 'neutral', 'sort_order' => 3],
            ['name' => 'Retail Expo', 'description' => 'Event and booth conversations.', 'tone' => 'warning', 'sort_order' => 4],
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
            ['name' => 'Proposal', 'stage_type' => LeadStageType::Proposal, 'tone' => 'info', 'probability' => 75, 'sort_order' => 5],
            ['name' => 'Won', 'stage_type' => LeadStageType::Won, 'tone' => 'success', 'probability' => 100, 'is_won' => true, 'sort_order' => 6],
            ['name' => 'Lost', 'stage_type' => LeadStageType::Lost, 'tone' => 'danger', 'probability' => 0, 'is_lost' => true, 'sort_order' => 7],
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
                    'interested_modules' => ['crm', 'pos', 'inventory'],
                    'expected_value' => 125000 + ($index * 17500),
                    'currency' => 'INR',
                    'priority' => [LeadPriority::Medium, LeadPriority::High, LeadPriority::Urgent, LeadPriority::Low][$index % 4]->value,
                    'lead_score' => min(95, 35 + ($index * 3)),
                    'next_follow_up_at' => now()->addDays(($index % 8) - 2)->setTime(11, 0),
                    'last_contacted_at' => now()->subDays($index % 5),
                    'description' => 'Seeded CRM opportunity for Phase 2 dashboard, pipeline, and follow-up workflows.',
                    'converted_at' => $status->is_won ? now()->subDays(3) : null,
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
