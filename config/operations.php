<?php

return [
    'health_retention_days' => env('OPERATIONS_HEALTH_RETENTION_DAYS', 30),

    'snapshot_retention_days' => env('OPERATIONS_SNAPSHOT_RETENTION_DAYS', 30),

    'sensitive_keys' => [
        'authorization',
        'api_key',
        'apikey',
        'access_token',
        'refresh_token',
        'password',
        'secret',
        'webhook_secret',
        'token',
        'key',
        'credential',
    ],

    'scheduled_commands' => [
        'notifications:retry-failed-deliveries' => [
            'description' => 'Retry failed notification and webhook deliveries.',
            'frequency' => 'Every 15 minutes',
        ],
        'notifications:dispatch-followup-due' => [
            'description' => 'Dispatch due CRM follow-up reminders.',
            'frequency' => 'Every 15 minutes',
        ],
        'notifications:dispatch-followup-overdue' => [
            'description' => 'Dispatch overdue CRM follow-up reminders.',
            'frequency' => 'Hourly',
        ],
        'notifications:prune-domain-events' => [
            'description' => 'Prune domain event logs beyond retention.',
            'frequency' => 'Daily at 02:30',
        ],
        'operations:health-check' => [
            'description' => 'Run system health checks and store snapshots.',
            'frequency' => 'Every 5 minutes',
        ],
        'operations:capture-queue-snapshot' => [
            'description' => 'Capture queue and failed job metrics.',
            'frequency' => 'Every 15 minutes',
        ],
        'operations:prune-health-checks' => [
            'description' => 'Prune old operations monitor snapshots.',
            'frequency' => 'Daily at 03:00',
        ],
    ],
];
