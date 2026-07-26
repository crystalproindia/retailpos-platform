<?php

return [
    'provider' => env('EMAIL_DELIVERY_PROVIDER'),
    'webhook' => [
        'enabled' => env('EMAIL_DELIVERY_WEBHOOK_ENABLED', false),
        'secret' => env('EMAIL_DELIVERY_WEBHOOK_SECRET'),
        'max_age_seconds' => (int) env('EMAIL_DELIVERY_WEBHOOK_TOLERANCE_SECONDS', env('EMAIL_DELIVERY_WEBHOOK_MAX_AGE_SECONDS', 300)),
    ],
];
