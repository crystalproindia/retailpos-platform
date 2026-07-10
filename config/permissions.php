<?php

$crmManagementRoles = ['administrator', 'manager'];
$crmUserRoles = ['administrator', 'manager', 'sales'];

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
    ],
];
