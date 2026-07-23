# SaaS Billing Architecture

## Scope of the first checkpoint

SaaS billing uses its own immutable subscription ledger. It does not reuse CRM sales invoices, POS payments, or purchase payments because those records represent different commercial relationships and reporting obligations.

The billing ledger is built around four records:

- `saas_subscription_invoices`: one tenant invoice per subscription billing period and invoice type.
- `saas_subscription_invoice_items`: immutable plan, tax, discount, adjustment, and credit line snapshots.
- `saas_billing_payments`: append-only payment allocations, initially one payment to one subscription invoice.
- `saas_billing_refunds`: controlled refund history without deleting payment records.

## Existing systems reused

- `GstTaxCalculator` determines CGST/SGST or IGST. Billing does not contain a second tax engine.
- `gst_document_series` provides transactional financial-year numbering. SaaS uses distinct document types and prefixes, so existing series remain untouched.
- `GstSetting` supplies supplier GST identity and place-of-supply defaults.
- `SubscriptionService` remains the only component that renews or reactivates subscriptions and clears entitlement state.
- `AuditLogger`, domain events, notification delivery, queues, and the Laravel scheduler remain the operational infrastructure for future reminders and emails.
- The encrypted integration connection pattern is reserved for the later payment-gateway settings checkpoint.

## Safety rules

- Amounts are calculated server-side with fixed two-decimal values.
- A unique subscription-period constraint prevents duplicate recurring invoices.
- Issued financial values are stored as plan and tax snapshots, so later plan changes do not change historical invoices.
- Manual payment allocation uses row locking, idempotency keys, and rejects overpayments.
- A subscription renews only after an invoice is fully paid. Partial payments leave the existing entitlement period unchanged.
- Invoice and payment history is never hard-deleted by billing services.

## Planned checkpoints

The next checkpoints add invoice generation and presentation, receipt/PDF delivery, provider-neutral payment contracts, a test-mode Razorpay adapter, secure webhooks, refunds, reconciliation, dunning, dashboards, and tenant-facing billing screens.
