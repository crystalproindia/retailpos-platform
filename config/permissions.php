<?php

$crmManagementRoles = ['administrator', 'manager'];
$crmUserRoles = ['administrator', 'manager', 'sales'];
$notificationManagementRoles = ['administrator', 'manager'];
$notificationUserRoles = ['administrator', 'manager', 'sales'];
$operationsManagementRoles = ['administrator', 'manager'];
$administratorRoles = ['administrator'];

return [
    'capabilities' => [
        'crm.view' => $crmUserRoles,
        'crm.leads.view' => $crmUserRoles,
        'crm.leads.create' => $crmUserRoles,
        'crm.leads.update' => $crmUserRoles,
        'crm.leads.delete' => $crmManagementRoles,
        'crm.leads.assign' => $crmManagementRoles,
        'crm.leads.convert' => $crmUserRoles,
        'crm.companies.manage' => $crmUserRoles,
        'crm.contacts.manage' => $crmUserRoles,
        'crm.activities.manage' => $crmUserRoles,
        'crm.pipeline.manage' => $crmUserRoles,
        'crm.settings.manage' => $crmManagementRoles,
        'notifications.view' => $notificationUserRoles,
        'notifications.manage_own' => $notificationUserRoles,
        'notifications.preferences.manage_own' => $notificationUserRoles,
        'notifications.preferences.manage_company' => $notificationManagementRoles,
        'notifications.events.view' => $notificationManagementRoles,
        'notifications.deliveries.view' => $notificationManagementRoles,
        'notifications.templates.manage' => $administratorRoles,
        'notifications.webhooks.view' => $notificationManagementRoles,
        'notifications.webhooks.manage' => $administratorRoles,
        'notifications.webhooks.retry' => $notificationManagementRoles,
        'notifications.settings.manage' => $administratorRoles,
        'operations.view' => $operationsManagementRoles,
        'operations.health.view' => $operationsManagementRoles,
        'operations.queue.view' => $operationsManagementRoles,
        'operations.failed_jobs.view' => $operationsManagementRoles,
        'operations.failed_jobs.retry' => $administratorRoles,
        'operations.failed_jobs.delete' => $administratorRoles,
        'operations.schedule.view' => $operationsManagementRoles,
        'operations.application.view' => $operationsManagementRoles,
        'operations.settings.manage' => $administratorRoles,
    ],
];
