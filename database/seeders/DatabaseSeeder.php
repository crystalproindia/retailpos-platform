<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DashboardStatistic;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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

        User::updateOrCreate(
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
    }
}
