<?php

return [
    'presets' => [
        'full_admin' => ['label' => 'Full Admin', 'visible' => [], 'pinned' => ['dashboard', 'sales', 'pos', 'customers', 'inventory', 'purchases', 'reports']],
        'manager' => ['label' => 'Manager', 'visible' => ['dashboard', 'sales', 'pos', 'customers', 'inventory', 'purchases', 'reports', 'workforce'], 'pinned' => ['dashboard', 'sales', 'pos', 'reports']],
        'retail_cashier' => ['label' => 'Retail Cashier', 'visible' => ['dashboard', 'pos', 'customers', 'sales', 'inventory'], 'pinned' => ['pos', 'customers', 'sales']],
        'sales' => ['label' => 'Sales', 'visible' => ['dashboard', 'crm', 'sales', 'customers', 'reports'], 'pinned' => ['leads', 'crm-quotations', 'sales', 'customers']],
        'inventory' => ['label' => 'Inventory', 'visible' => ['dashboard', 'inventory', 'purchases', 'reports'], 'pinned' => ['products', 'inventory-stock-ledger', 'outlet-transfer', 'stock-take']],
        'accounts_gst' => ['label' => 'Accounts / GST', 'visible' => ['dashboard', 'sales', 'purchases', 'gst-compliance', 'reports'], 'pinned' => ['sales', 'purchase-invoices', 'gst-compliance', 'reports']],
        'purchasing' => ['label' => 'Purchasing', 'visible' => ['dashboard', 'purchases', 'inventory', 'reports'], 'pinned' => ['purchase-orders', 'purchase-invoices', 'suppliers']],
        'crm' => ['label' => 'CRM', 'visible' => ['dashboard', 'crm', 'sales', 'customers', 'reports'], 'pinned' => ['leads', 'crm-activities', 'crm-follow-ups', 'crm-quotations']],
        'workforce' => ['label' => 'Workforce', 'visible' => ['dashboard', 'workforce', 'attendance', 'tasks'], 'pinned' => ['workforce-employees', 'attendance', 'tasks-today']],
    ],
];
