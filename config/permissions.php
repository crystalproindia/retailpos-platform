<?php

$crmManagementRoles = ['administrator', 'manager'];
$crmUserRoles = ['administrator', 'manager', 'sales'];
$notificationManagementRoles = ['administrator', 'manager'];
$notificationUserRoles = ['administrator', 'manager', 'sales'];
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
    ],
];
