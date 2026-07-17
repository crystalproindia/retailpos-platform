<?php

namespace App\Services\Crm;

class CrmOnboardingTemplateService
{
    /** @return array<int, array<string, mixed>> */
    public function defaultTasks(): array
    {
        return [
            ['task_key' => 'business-legal-name', 'title' => 'Collect business legal name', 'category' => 'business_details'],
            ['task_key' => 'business-contact-details', 'title' => 'Collect contact person details', 'category' => 'business_details'],
            ['task_key' => 'business-tax-details', 'title' => 'Collect GST or tax details if applicable', 'category' => 'business_details'],
            ['task_key' => 'business-address', 'title' => 'Confirm billing and contact address', 'category' => 'business_details'],
            ['task_key' => 'store-count', 'title' => 'Confirm number of stores or branches', 'category' => 'store_setup'],
            ['task_key' => 'store-locations', 'title' => 'Collect store names and locations', 'category' => 'store_setup'],
            ['task_key' => 'access-roles', 'title' => 'Confirm user roles and access levels', 'category' => 'user_setup'],
            ['task_key' => 'hardware-needs', 'title' => 'Confirm hardware, printer, and barcode needs', 'category' => 'store_setup'],
            ['task_key' => 'product-master', 'title' => 'Receive product master', 'category' => 'data_collection'],
            ['task_key' => 'product-attributes', 'title' => 'Receive category, brand, and unit details', 'category' => 'data_collection'],
            ['task_key' => 'supplier-list', 'title' => 'Receive supplier list', 'category' => 'data_collection', 'is_required' => false],
            ['task_key' => 'customer-list', 'title' => 'Receive customer list if applicable', 'category' => 'data_collection', 'is_required' => false],
            ['task_key' => 'opening-stock', 'title' => 'Verify opening stock data if applicable', 'category' => 'data_collection', 'is_required' => false],
            ['task_key' => 'company-profile', 'title' => 'Create company and store profile', 'category' => 'user_setup'],
            ['task_key' => 'tax-settings', 'title' => 'Configure tax and settings', 'category' => 'user_setup'],
            ['task_key' => 'users-roles', 'title' => 'Create users and roles', 'category' => 'user_setup'],
            ['task_key' => 'invoice-format', 'title' => 'Configure invoice and bill format', 'category' => 'user_setup'],
            ['task_key' => 'payment-modes', 'title' => 'Configure payment modes', 'category' => 'user_setup'],
            ['task_key' => 'admin-training', 'title' => 'Schedule admin training', 'category' => 'training'],
            ['task_key' => 'cashier-training', 'title' => 'Schedule cashier and user training', 'category' => 'training'],
            ['task_key' => 'training-complete', 'title' => 'Complete training', 'category' => 'training'],
            ['task_key' => 'training-confirmation', 'title' => 'Collect training confirmation', 'category' => 'training'],
            ['task_key' => 'final-review', 'title' => 'Complete final checklist review', 'category' => 'go_live'],
            ['task_key' => 'opening-data', 'title' => 'Confirm opening data', 'category' => 'go_live'],
            ['task_key' => 'customer-approval', 'title' => 'Confirm customer approval', 'category' => 'go_live'],
            ['task_key' => 'mark-live', 'title' => 'Mark customer live', 'category' => 'go_live'],
        ];
    }
}
