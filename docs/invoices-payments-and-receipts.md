# Invoices, Payments and Receipts

## Purpose and scope

This sales-invoice foundation turns an approved quotation or authorized manual sale into a tenant-scoped invoice, then tracks collection without introducing POS billing, stock movement, payment gateways, accounting ledgers, GST filing, e-invoicing, or any Google Calendar/Meet dependency.

## Workflow

1. A manager or sales user opens an accepted quotation and selects **Create Sales Invoice**.
2. The confirmation screen creates one draft invoice. The quotation remains unchanged; its customer-facing line items, prices, discounts, taxes, notes, and terms are copied as invoice snapshots.
3. A draft can be issued. Issued invoices are intentionally not silently edited.
4. The user may download a branded PDF, send a queued invoice email, prepare a WhatsApp share link, or issue a secure public link.
5. Payments are recorded against the outstanding balance. Partial payments create `partially_paid`; a zero balance creates `paid`; a reversal restores the balance and recalculates the status.
6. Each recorded payment receives a receipt number and supports a PDF, customer email, WhatsApp share, and secure public receipt download.
7. Collection reminders are manual in this release. Automated reminders are off by design.

## Data and numbering

- `crm_invoices` stores tenant-scoped billing snapshots, monetary totals, lifecycle state, secure-link state, and non-public internal notes.
- `crm_invoice_items` stores immutable item snapshots. Later quotation or catalogue edits do not modify invoices.
- `crm_invoice_payments` stores append-only payment records, idempotency keys, and reversals. Cleared or recorded payments are not deleted.
- Invoice numbers are generated transactionally per tenant: `RPOS-INV-YYYY-00001`.
- Payment and receipt references use `RPOS-PAY-YYYY-00001` and `RPOS-RCPT-YYYY-00001` respectively.
- Calculations use integer minor units and server-side recalculation. Submitted totals are ignored.

## Statuses and collections

Invoice status is recalculated in `InvoiceService`: `draft`, `issued`, `sent`, `viewed`, `partially_paid`, `paid`, `overdue`, `cancelled`, and `void`.

Overdue status is derived from the current due date and outstanding balance whenever an invoice is viewed or queried; it does not depend on a scheduler being available. Pending, failed, and reversed payments do not reduce the balance. An invoice with a payment cannot be cancelled until the payment is reversed.

The invoice list groups collection totals by currency so values from different currencies are never added together. Authorized managers and administrators can export a bounded (5,000-row) CSV containing safe billing, status, and monetary fields; it excludes tokens, private notes, email configuration, and audit internals.

## Secure sharing and PDFs

Public URLs are under `/i/{opaque-token}`. Tokens are cryptographically random, only SHA-256 hashes are stored, and the public page is rate-limited and `noindex, nofollow`. The page excludes internal notes, audit data, user IDs, and SMTP information. A secure link can be revoked; expired or revoked tokens return a normal 404.

Invoice and receipt PDFs use the existing DomPDF integration. They use customer-facing fields only and include RetailPOS.biz contact details. Public receipt downloads remain protected by the current invoice token.

## Email, WhatsApp, queues, and reminders

Invoice, reminder, overdue, payment-received, and receipt flows use the existing `EmailDeliveryService` and `notification_deliveries` queue. Missing SMTP creates a `skipped_not_configured` delivery and never blocks invoice or payment persistence. Delivery idempotency keys prevent repeated button clicks from producing duplicate sends.

WhatsApp actions generate `wa.me` share links only. No paid WhatsApp API is installed. Prepared messages contain the secure public link but neither the customer phone nor the token is written to audit metadata.

Automated reminder delivery is intentionally disabled. Users with reminder permission can send a manual upcoming/overdue reminder. Future automation should read tenant settings, honor `do_not_remind_before`, apply a bounded schedule, and queue through the existing notification system.

The existing Operations Center and queue tooling remain the operational source of truth. On Hostinger, a queue worker can be invoked by a scheduled `queue:work --stop-when-empty` job where a long-running worker is unavailable. The scheduler/worker must be monitored, but an unavailable worker cannot change invoice balances or overdue calculations.

## Tax and GST foundation

Invoices retain optional customer tax number, place of supply, tax classification, tax rate, and taxable totals. This is a calculation and presentation foundation only. RetailPOS does not determine legal treatment automatically and does not implement GST return filing, CGST/SGST/IGST allocation, HSN/SAC, e-invoices, e-way bills, or government submission. Company tax and payment instructions must be reviewed and configured by an authorized finance owner before customer use.

## Access, auditing, and tenant safety

Routes use the existing sales role group plus permissions for invoices, payments, receipts, public links, reminders, finance dashboards, exports, and invoice settings. Repository queries always apply `company_id`; sales users are limited to invoices tied to their assigned leads. Create, issue, payment, reversal, cancellation, public-link, email, WhatsApp, and reminder events are audited without storing raw public tokens or sensitive email configuration.

## Deployment and rollback

Run the standard Laravel migration process before enabling the menu in production. Verify `php artisan test`, `php artisan view:cache`, and the queue configuration. No Google Calendar or Google Meet configuration, migrations, routes, or environment variables are required or introduced by this release.

To roll back before invoices are used, roll back the invoice migration as a normal Laravel migration. Once production invoices exist, use controlled cancellation and payment reversal rather than destructive database rollback.
