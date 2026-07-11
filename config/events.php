<?php

$channels = ['database', 'email', 'webhook'];
$futureChannels = ['whatsapp', 'sms', 'push'];

return [
    'retention_days' => env('DOMAIN_EVENT_RETENTION_DAYS', 180),

    'channels' => [
        'database' => ['name' => 'In-app', 'enabled' => true],
        'email' => ['name' => 'Email', 'enabled' => true],
        'webhook' => ['name' => 'Webhook', 'enabled' => true],
        'whatsapp' => ['name' => 'WhatsApp', 'enabled' => false],
        'sms' => ['name' => 'SMS', 'enabled' => false],
        'push' => ['name' => 'Push', 'enabled' => false],
    ],

    'catalog' => [
        'auth.login' => ['name' => 'User login', 'description' => 'A user signed in.', 'category' => 'Authentication', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => false, 'webhook_enabled' => false, 'future_ai_eligible' => false],
        'auth.logout' => ['name' => 'User logout', 'description' => 'A user signed out.', 'category' => 'Authentication', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => false, 'webhook_enabled' => false, 'future_ai_eligible' => false],
        'auth.password_reset_requested' => ['name' => 'Password reset requested', 'description' => 'A password reset link was requested.', 'category' => 'Authentication', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'warning', 'user_preference_enabled' => false, 'webhook_enabled' => false, 'future_ai_eligible' => false],
        'auth.password_reset_completed' => ['name' => 'Password reset completed', 'description' => 'A password was reset.', 'category' => 'Authentication', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'warning', 'user_preference_enabled' => false, 'webhook_enabled' => false, 'future_ai_eligible' => false],

        'crm.lead.created' => ['name' => 'Lead created', 'description' => 'A CRM lead was created.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.lead.updated' => ['name' => 'Lead updated', 'description' => 'A CRM lead was updated.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.lead.assigned' => ['name' => 'Lead assigned', 'description' => 'A CRM lead was assigned to a user.', 'category' => 'CRM', 'default_channels' => ['database', 'email'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.lead.status_changed' => ['name' => 'Lead status changed', 'description' => 'A CRM lead moved to a new status.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.lead.converted' => ['name' => 'Lead converted', 'description' => 'A CRM lead was converted.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'success', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.follow_up.due' => ['name' => 'Follow-up due', 'description' => 'A CRM follow-up is due.', 'category' => 'CRM', 'default_channels' => ['database', 'email'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'warning', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.follow_up.overdue' => ['name' => 'Follow-up overdue', 'description' => 'A CRM follow-up is overdue.', 'category' => 'CRM', 'default_channels' => ['database', 'email'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'danger', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.activity.created' => ['name' => 'Activity created', 'description' => 'A CRM activity was created.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],
        'crm.activity.completed' => ['name' => 'Activity completed', 'description' => 'A CRM activity was completed.', 'category' => 'CRM', 'default_channels' => ['database'], 'allowed_channels' => array_merge($channels, $futureChannels), 'severity' => 'success', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => true],

        'cms.page.created' => ['name' => 'CMS page created', 'description' => 'A CMS page was created.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.page.updated' => ['name' => 'CMS page updated', 'description' => 'A CMS page was updated.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.page.published' => ['name' => 'CMS page published', 'description' => 'A CMS page was published.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'success', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.page.unpublished' => ['name' => 'CMS page unpublished', 'description' => 'A CMS page was unpublished.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'warning', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.media.uploaded' => ['name' => 'CMS media uploaded', 'description' => 'A media file was uploaded.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.redirect.created' => ['name' => 'CMS redirect created', 'description' => 'A redirect was created.', 'category' => 'CMS', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'cms.broken_link.detected' => ['name' => 'Broken link detected', 'description' => 'A broken website link was detected.', 'category' => 'CMS', 'default_channels' => ['database', 'email'], 'allowed_channels' => $channels, 'severity' => 'danger', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],

        'system.user.created' => ['name' => 'User created', 'description' => 'A platform user was created.', 'category' => 'System', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'system.user.deactivated' => ['name' => 'User deactivated', 'description' => 'A platform user was deactivated.', 'category' => 'System', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'warning', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'system.settings.updated' => ['name' => 'Settings updated', 'description' => 'Command Center settings were updated.', 'category' => 'System', 'default_channels' => ['database'], 'allowed_channels' => $channels, 'severity' => 'info', 'user_preference_enabled' => true, 'webhook_enabled' => true, 'future_ai_eligible' => false],
        'system.webhook.failed' => ['name' => 'Webhook failed', 'description' => 'A webhook delivery failed.', 'category' => 'System', 'default_channels' => ['database', 'email'], 'allowed_channels' => $channels, 'severity' => 'danger', 'user_preference_enabled' => true, 'webhook_enabled' => false, 'future_ai_eligible' => false],
        'system.queue.failed' => ['name' => 'Queue failed', 'description' => 'A queued job failed.', 'category' => 'System', 'default_channels' => ['database', 'email'], 'allowed_channels' => $channels, 'severity' => 'danger', 'user_preference_enabled' => true, 'webhook_enabled' => false, 'future_ai_eligible' => false],
    ],
];
