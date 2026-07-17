<?php

return [
    'sla' => [
        'urgent' => ['first_response_hours' => 2, 'resolution_days' => 1],
        'high' => ['first_response_hours' => 4, 'resolution_days' => 2],
        'normal' => ['first_response_hours' => 24, 'resolution_days' => 5],
        'low' => ['first_response_hours' => 48, 'resolution_days' => 7],
    ],
    'waiting_reminder_hours' => 24,
];
