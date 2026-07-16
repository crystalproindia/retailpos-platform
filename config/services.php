<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'retailpos' => [
        'public_lead_token' => env('RETAILPOS_PUBLIC_LEAD_TOKEN'),
        'public_lead_company_id' => env('RETAILPOS_PUBLIC_LEAD_COMPANY_ID'),
        'public_lead_assignee_id' => env('RETAILPOS_PUBLIC_LEAD_ASSIGNEE_ID'),
        'public_lead_rate_limit' => env('RETAILPOS_PUBLIC_LEAD_RATE_LIMIT', 30),
        'public_lead_max_payload_kb' => env('RETAILPOS_PUBLIC_LEAD_MAX_PAYLOAD_KB', 64),
        'lead_notifications' => [
            'lead_notifications_enabled' => true,
            'lead_email_notifications_enabled' => env('RETAILPOS_LEAD_EMAIL_NOTIFICATIONS', false),
            'lead_notification_email' => env('RETAILPOS_LEAD_NOTIFY_EMAIL'),
            'notify_admins_on_new_lead' => true,
            'notify_sales_on_new_lead' => true,
            'followup_reminders_enabled' => true,
        ],
    ],

];
