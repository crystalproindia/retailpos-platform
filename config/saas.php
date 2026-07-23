<?php

return [
    /*
     * Enforcement is intentionally opt-in while existing tenants are migrated
     * to the grandfathered plan. Enable it only after a rollout review.
     */
    'enforcement_enabled' => filter_var(env('SAAS_ENTITLEMENT_ENFORCEMENT', false), FILTER_VALIDATE_BOOL),

    // Stable internal code. This must not vary by deployment environment.
    'grandfathered_plan_code' => 'existing-tenant-access',

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

    'billing' => [
        'invoice_lead_days' => 7,
    ],
];
