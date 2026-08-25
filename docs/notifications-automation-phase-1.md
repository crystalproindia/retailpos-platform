# Notifications & Automation Phase 1

## Architecture

- `notification_automation_settings` stores one company policy row with conservative defaults. Customer email reminders and owner summaries default off.
- `notification_condition_states` stores the current lifecycle for a tenant/business subject/stage. The unique key is MySQL-safe and prevents parallel scheduler runs from creating competing state rows.
- `NotificationAutomationEvaluator` converts authoritative provider output into concise conditions. It never changes a sale, invoice, quotation, proforma, purchase order, or stock record.
- `AutomationNotificationService` locks condition state, creates an in-app delivery ledger record and database notification atomically, and queues optional email through `EmailDeliveryService`.
- `notifications:evaluate-automations` processes active companies in a bounded batch. Laravel schedules it hourly with overlap protection.

## Deduplication

Each condition has a stable company, type, subject, and stage identity. Each activation cycle increments only after recovery or meaningful severity escalation. Deliveries use an idempotency key containing the condition state, cycle, recipient, and channel. Scheduler reruns and queue retries therefore reuse the existing delivery rather than sending again.

Low stock and out-of-stock are separate stages. A location that recovers is marked inactive; a later fall starts a new cycle. Reorder alerts use the Inventory Intelligence recommendation and are suppressed when a more direct low/out-of-stock alert already explains the same product-location condition.

## Data Sources

- Inventory: `InventoryIntelligenceService`, including configured minimums and rule-based reorder quantities.
- Receivables: current `crm_invoices.balance_due`, which is maintained by payment and CRM credit-note/return services. Paid, credited, cancelled, void, draft, zero-balance, and snoozed invoices are excluded.
- Quotations: active sent/viewed documents and their stored `valid_until` date.
- Proformas: active sent/partially-paid/overdue documents and their stored due date.
- Purchasing: Inventory Intelligence reorder evidence plus overdue expected receipt dates on active purchase orders. No supplier lead time is invented.
- Owner summaries: `ExecutiveReportingService` for the company administrator and company timezone. AI is not required.

## Delivery Safety

In-app notification creation is primary. Optional email reuses encrypted company SMTP settings, the queue, delivery status ledger, lifecycle events, retries, and safe error handling. Missing SMTP produces `skipped_not_configured` without losing the in-app alert. Customer payment reminders are independently disabled until an administrator opts in. Missing customer email skips external delivery.

No raw SMTP error, secret, or provider credential is exposed in notification content. WhatsApp remains an unsupported channel with no active provider or runtime credential requirement.

## Current Limits

- Evaluations run hourly and process at most 100 companies by default and 500 source rows per domain.
- Reminder stages are date-based and intentionally avoid daily repetition.
- Owner summaries are internal and administrator-only.
- WhatsApp, SMS, push, customer campaigns, autonomous PO creation, and AI-required wording are deferred.
