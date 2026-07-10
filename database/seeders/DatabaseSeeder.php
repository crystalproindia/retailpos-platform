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
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
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
    }
}
