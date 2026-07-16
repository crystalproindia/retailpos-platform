<?php

return [
    'ruleset_version' => 'v1',
    'high_value_amount' => 100000,
    'stale_activity_days' => 3,
    'hot_threshold' => 70,
    'warm_threshold' => 45,
    'weights' => [
        'base' => 20,
        'phone' => 5,
        'email' => 5,
        'company' => 3,
        'demo_scheduled' => 15,
        'demo_completed' => 20,
        'quotation_created' => 10,
        'quotation_sent' => 10,
        'proforma_sent' => 20,
        'partial_payment' => 25,
        'high_value' => 10,
        'follow_up_today' => 5,
        'overdue_follow_up' => -10,
        'stale_activity' => -10,
        'stale_proposal' => -8,
    ],
];
