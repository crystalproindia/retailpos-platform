<?php

return [
    'navigation' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'dashboard'],
        ['label' => 'CRM', 'route' => 'modules.show', 'params' => ['module' => 'crm'], 'icon' => 'crm'],
        ['label' => 'POS', 'route' => 'modules.show', 'params' => ['module' => 'pos'], 'icon' => 'pos'],
        ['label' => 'Inventory', 'route' => 'modules.show', 'params' => ['module' => 'inventory'], 'icon' => 'inventory'],
        ['label' => 'Orders', 'route' => 'modules.show', 'params' => ['module' => 'orders'], 'icon' => 'orders'],
        ['label' => 'Customers', 'route' => 'modules.show', 'params' => ['module' => 'customers'], 'icon' => 'customers'],
        ['label' => 'Products', 'route' => 'modules.show', 'params' => ['module' => 'products'], 'icon' => 'products'],
        ['label' => 'Purchases', 'route' => 'modules.show', 'params' => ['module' => 'purchases'], 'icon' => 'purchases'],
        ['label' => 'Suppliers', 'route' => 'modules.show', 'params' => ['module' => 'suppliers'], 'icon' => 'suppliers'],
        ['label' => 'Finance', 'route' => 'modules.show', 'params' => ['module' => 'finance'], 'icon' => 'finance'],
        ['label' => 'Marketing', 'route' => 'modules.show', 'params' => ['module' => 'marketing'], 'icon' => 'marketing'],
        ['label' => 'Website CMS', 'route' => 'modules.show', 'params' => ['module' => 'website-cms'], 'icon' => 'cms'],
        ['label' => 'Blog', 'route' => 'modules.show', 'params' => ['module' => 'blog'], 'icon' => 'blog'],
        ['label' => 'SEO', 'route' => 'modules.show', 'params' => ['module' => 'seo'], 'icon' => 'seo'],
        ['label' => 'WhatsApp', 'route' => 'modules.show', 'params' => ['module' => 'whatsapp'], 'icon' => 'whatsapp'],
        ['label' => 'Analytics', 'route' => 'modules.show', 'params' => ['module' => 'analytics'], 'icon' => 'analytics'],
        ['label' => 'Reports', 'route' => 'modules.show', 'params' => ['module' => 'reports'], 'icon' => 'reports'],
        ['label' => 'Settings', 'route' => 'settings.show', 'params' => ['section' => 'general'], 'icon' => 'settings'],
        ['label' => 'AI Assistant', 'route' => 'modules.show', 'params' => ['module' => 'ai-assistant'], 'icon' => 'ai'],
        ['label' => 'Users', 'route' => 'modules.show', 'params' => ['module' => 'users'], 'icon' => 'users'],
        ['label' => 'Branches', 'route' => 'modules.show', 'params' => ['module' => 'branches'], 'icon' => 'branches'],
        ['label' => 'Company', 'route' => 'modules.show', 'params' => ['module' => 'company'], 'icon' => 'company'],
        ['label' => 'Audit Logs', 'route' => 'modules.show', 'params' => ['module' => 'audit-logs'], 'icon' => 'audit'],
    ],

    'settings_sections' => [
        'general' => [
            'label' => 'General',
            'description' => 'Core Command Center preferences.',
            'fields' => [
                'timezone' => ['label' => 'Timezone', 'type' => 'text'],
                'currency' => ['label' => 'Currency', 'type' => 'text'],
                'date_format' => ['label' => 'Date format', 'type' => 'text'],
            ],
        ],
        'company' => [
            'label' => 'Company',
            'description' => 'Company identity and operating location.',
            'fields' => [
                'company_name' => ['label' => 'Company name', 'type' => 'text'],
                'tax_id' => ['label' => 'Tax ID', 'type' => 'text'],
                'registered_address' => ['label' => 'Registered address', 'type' => 'textarea'],
            ],
        ],
        'business' => [
            'label' => 'Business',
            'description' => 'Retail operating defaults.',
            'fields' => [
                'fiscal_year_start' => ['label' => 'Fiscal year start', 'type' => 'text'],
                'default_branch' => ['label' => 'Default branch', 'type' => 'text'],
                'stock_alert_threshold' => ['label' => 'Stock alert threshold', 'type' => 'number'],
            ],
        ],
        'email' => [
            'label' => 'Email',
            'description' => 'Transactional email sender details.',
            'fields' => [
                'from_name' => ['label' => 'From name', 'type' => 'text'],
                'from_email' => ['label' => 'From email', 'type' => 'email'],
                'support_email' => ['label' => 'Support email', 'type' => 'email'],
            ],
        ],
        'notifications' => [
            'label' => 'Notifications',
            'description' => 'Operational alerts and channel preferences.',
            'fields' => [
                'low_stock_alerts' => ['label' => 'Low stock alerts', 'type' => 'checkbox'],
                'daily_sales_digest' => ['label' => 'Daily sales digest', 'type' => 'checkbox'],
                'lead_alerts' => ['label' => 'Lead alerts', 'type' => 'checkbox'],
            ],
        ],
        'theme' => [
            'label' => 'Theme',
            'description' => 'Interface appearance defaults.',
            'fields' => [
                'mode' => ['label' => 'Mode', 'type' => 'select', 'options' => ['light' => 'Light', 'dark' => 'Dark', 'system' => 'System']],
                'accent_color' => ['label' => 'Accent color', 'type' => 'text'],
                'compact_sidebar' => ['label' => 'Compact sidebar', 'type' => 'checkbox'],
            ],
        ],
        'security' => [
            'label' => 'Security',
            'description' => 'Session and access-control defaults.',
            'fields' => [
                'session_timeout' => ['label' => 'Session timeout minutes', 'type' => 'number'],
                'require_mfa' => ['label' => 'Require MFA', 'type' => 'checkbox'],
                'audit_retention_days' => ['label' => 'Audit retention days', 'type' => 'number'],
            ],
        ],
    ],
];
