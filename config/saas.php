<?php

return [
    /*
     * Enforcement is intentionally opt-in while existing tenants are migrated
     * to the grandfathered plan. Enable it only after a rollout review.
     */
    'enforcement_enabled' => filter_var(env('SAAS_ENTITLEMENT_ENFORCEMENT', false), FILTER_VALIDATE_BOOL),

    'grandfathered_plan_code' => env('SAAS_GRANDFATHERED_PLAN_CODE', 'legacy-unlimited'),

    'features' => [
        'crm', 'quotations', 'sales_invoices', 'pos', 'gst_compliance',
        'purchases', 'inventory', 'cms', 'email_integration', 'reports',
        'api_access', 'webhooks', 'multi_branch', 'multi_warehouse',
        'custom_roles', 'priority_support', 'white_label', 'reseller_management',
        'mobile_apps', 'ai_features',
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
];
