<?php

namespace App\Services\Tasks;

class TaskRuleRegistry
{
    /**
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     permission: string,
     *     record_type: string,
     *     assignee_strategy: string,
     *     due_strategy: string,
     *     idempotency_strategy: string,
     *     threshold_hours: int
     * }>
     */
    public function definitions(): array
    {
        return [
            'lead_first_contact_due' => [
                'name' => 'Lead first contact due',
                'description' => 'Creates a work task when an active lead has not received an initial contact within the selected time.',
                'permission' => 'crm.leads.update',
                'record_type' => 'lead',
                'assignee_strategy' => 'lead owner',
                'due_strategy' => 'current run when the selected threshold is reached',
                'idempotency_strategy' => 'one task per lead until an explicit future cycle is introduced',
                'threshold_hours' => 24,
            ],
            'lead_follow_up_due' => [
                'name' => 'Lead follow-up due',
                'description' => 'Creates a work task when an active lead reaches its planned follow-up time.',
                'permission' => 'crm.leads.update',
                'record_type' => 'lead',
                'assignee_strategy' => 'lead owner',
                'due_strategy' => 'the lead follow-up time',
                'idempotency_strategy' => 'one task per lead until an explicit future cycle is introduced',
                'threshold_hours' => 0,
            ],
            'crm_invoice_overdue' => [
                'name' => 'CRM invoice overdue',
                'description' => 'Creates a work task when an open CRM invoice is overdue with a remaining balance.',
                'permission' => 'sales.invoices.update',
                'record_type' => 'invoice',
                'assignee_strategy' => 'linked lead owner',
                'due_strategy' => 'current run after the due date',
                'idempotency_strategy' => 'one task per invoice until an explicit future cycle is introduced',
                'threshold_hours' => 0,
            ],
        ];
    }

    public function definition(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }
}
