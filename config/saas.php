<?php

return [
    /*
     * Enforcement is intentionally opt-in while existing tenants are migrated
     * to the grandfathered plan. Enable it only after a rollout review.
     */
    'enforcement_enabled' => filter_var(env('SAAS_ENTITLEMENT_ENFORCEMENT', false), FILTER_VALIDATE_BOOL),

    // Stable internal code. This must not vary by deployment environment.
    'grandfathered_plan_code' => 'existing-tenant-access',

    'free365_plan_code' => 'free-365',

    'public_signup' => [
        'enabled' => filter_var(env('SAAS_PUBLIC_SIGNUP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'email_otp_enabled' => filter_var(env('SAAS_PUBLIC_SIGNUP_EMAIL_OTP_ENABLED', true), FILTER_VALIDATE_BOOL),
        'email_delivery_company_id' => env('SAAS_PUBLIC_SIGNUP_EMAIL_DELIVERY_COMPANY_ID'),
        'mobile_otp_enabled' => filter_var(env('SAAS_PUBLIC_SIGNUP_MOBILE_OTP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'mobile_otp_provider' => env('SAAS_MOBILE_OTP_PROVIDER'),
        'session_ttl_minutes' => 30,
        'terms_url' => env('SAAS_TERMS_URL', '/terms'),
        'privacy_url' => env('SAAS_PRIVACY_URL', '/privacy'),
        'terms_version' => env('SAAS_TERMS_VERSION', 'v1'),
        'privacy_version' => env('SAAS_PRIVACY_VERSION', 'v1'),
    ],

    'entitlement_aliases' => [
        'pos' => 'pos.billing',
        'sales_invoices' => 'sales.invoices',
        'inventory' => 'inventory.basic',
        'crm' => 'customers.basic',
        'reports' => 'reports.advanced',
        'ai_features' => 'ai.assistant',
        'gst_compliance' => 'gst.advanced',
    ],

    'features' => [
        'crm', 'quotations', 'sales_invoices', 'pos', 'gst_compliance',
        'purchases', 'inventory', 'cms', 'email_integration', 'reports',
        'api_access', 'webhooks', 'multi_branch', 'multi_warehouse',
        'custom_roles', 'priority_support', 'white_label', 'reseller_management',
        'mobile_apps', 'ai_features',
        'pos.billing', 'sales.invoices', 'inventory.basic', 'customers.basic',
        'dashboard.basic', 'dashboard.advanced', 'reports.advanced', 'ai.assistant',
        'users.additional', 'outlets.additional', 'whatsapp.automation', 'gst.advanced', 'audit.logs',
    ],

    'usage_limits' => [
        'users' => 'users',
        'branches' => 'branches',
        'warehouses' => 'warehouses',
        'products' => 'products',
        'monthly_invoices' => 'monthly_invoices',
        'monthly_pos_transactions' => 'monthly_pos_transactions',
        'storage_mb' => 'storage_mb',
        'monthly_api_requests' => 'monthly_api_requests',
        'monthly_email_volume' => 'monthly_email_volume',
    ],

    'renewal_reminder_days' => [30, 15, 7, 3, 1, 0],

    'free365_expiry_notice_days' => [14, 7, 1, 0],

    'verification' => [
        'code_ttl_minutes' => 10,
        'resend_cooldown_seconds' => 60,
        'max_attempts' => 5,
    ],

    'industries' => [
        ['key' => 'fashion_apparel', 'label' => 'Fashion & Apparel', 'icon' => 'tag', 'sort_order' => 10, 'is_enabled' => true, 'description' => 'Clothing, fashion, and apparel stores.'],
        ['key' => 'grocery_supermarket', 'label' => 'Grocery & Supermarket', 'icon' => 'package', 'sort_order' => 20, 'is_enabled' => true, 'description' => 'Grocery, supermarket, and daily-needs retail.'],
        ['key' => 'electronics', 'label' => 'Electronics', 'icon' => 'products', 'sort_order' => 30, 'is_enabled' => true, 'description' => 'Consumer electronics and appliances.'],
        ['key' => 'mobile_accessories', 'label' => 'Mobile & Accessories', 'icon' => 'products', 'sort_order' => 40, 'is_enabled' => true, 'description' => 'Mobile phones, repairs, and accessories.'],
        ['key' => 'pharmacy', 'label' => 'Pharmacy', 'icon' => 'products', 'sort_order' => 50, 'is_enabled' => true, 'description' => 'Pharmacy and healthcare retail.'],
        ['key' => 'jewellery', 'label' => 'Jewellery', 'icon' => 'tag', 'sort_order' => 60, 'is_enabled' => true, 'description' => 'Jewellery and precious goods.'],
        ['key' => 'footwear', 'label' => 'Footwear', 'icon' => 'products', 'sort_order' => 70, 'is_enabled' => true, 'description' => 'Footwear and fashion accessories.'],
        ['key' => 'furniture_home', 'label' => 'Furniture & Home', 'icon' => 'company', 'sort_order' => 80, 'is_enabled' => true, 'description' => 'Furniture, home decor, and household retail.'],
        ['key' => 'hardware_building', 'label' => 'Hardware & Building', 'icon' => 'inventory', 'sort_order' => 90, 'is_enabled' => true, 'description' => 'Hardware, building materials, and tools.'],
        ['key' => 'beauty_cosmetics', 'label' => 'Beauty & Cosmetics', 'icon' => 'marketing', 'sort_order' => 100, 'is_enabled' => true, 'description' => 'Beauty, cosmetics, and personal care.'],
        ['key' => 'books_stationery', 'label' => 'Books & Stationery', 'icon' => 'blog', 'sort_order' => 110, 'is_enabled' => true, 'description' => 'Books, stationery, and gifts.'],
        ['key' => 'general_retail', 'label' => 'General Retail', 'icon' => 'sales', 'sort_order' => 120, 'is_enabled' => true, 'description' => 'General-purpose retail stores.'],
        ['key' => 'other', 'label' => 'Other', 'icon' => 'module', 'sort_order' => 999, 'is_enabled' => true, 'description' => 'Another retail business type.'],
    ],

    'billing' => [
        'invoice_lead_days' => 7,
    ],
];
