<?php

return [
    'health_weights' => [
        'sales' => 25,
        'money' => 30,
        'onboarding' => 20,
        'support' => 15,
        'customer_growth' => 10,
    ],
    'stale_lead_days' => 14,
    'customer_lifecycle' => [
        // Precedence: Dormant, At Risk, Repeat Customer, New Customer,
        // Active Customer, Prospect. Thresholds use the latest recognized
        // (non-draft/non-cancelled/non-void) CRM invoice only.
        'new_customer_days' => 30,
        'at_risk_after_days' => 90,
        'dormant_after_days' => 180,
    ],
    'health_thresholds' => [
        'healthy' => 80,
        'attention' => 60,
        'risk' => 40,
    ],
];
