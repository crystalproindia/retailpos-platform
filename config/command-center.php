<?php

return [
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
