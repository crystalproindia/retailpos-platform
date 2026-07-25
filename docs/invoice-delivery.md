# Phase 8C: Invoice Delivery and Communication Hardening

## Objective

Customer sales-invoice emails now include the tenant's currently selected CRM invoice design as a PDF attachment while retaining the existing secure public invoice link. This phase extends the established CRM invoice, Invoice Designs, DomPDF, SMTP, queue, audit, and notification-delivery systems. It does not alter SaaS subscription billing invoices, Google Calendar, or Google Meet.

## Architecture

`InvoiceShareService` marks only the standard Sales Invoice "Send email" workflow with the `sales_invoice_pdf` attachment type. The existing `EmailDeliveryService` continues to create the tenant-scoped, idempotent `notification_deliveries` record and queue `SendNotificationDeliveryJob`.

At send time, `InvoiceEmailAttachmentService` validates that the delivery is related to a `CrmInvoice` in the same company, reloads that invoice with its company and line items, and asks `InvoicePdfService` to render the active tenant Invoice Designs template. `InvoicePdfService` remains the one rendering path for CRM print, download, public PDF, preview, and email attachment output. It uses the current template setting, GST presentation, totals, and optional payment QR configuration.

The PDF stays in memory and is passed to `CommandCenterEmail` through Laravel's `Attachment::fromData` support. No permanent or temporary invoice-PDF file is written to application storage. The attachment filename is deterministic and normalized to ASCII-safe header characters.

## Security and Tenant Controls

- Invoice retrieval for a delivery is constrained by both `notification_deliveries.company_id` and the invoice company ID.
- The existing `InvoiceRepository` continues to scope all invoice UI actions to the authenticated company and sales-user ownership policy.
- The existing `sales.invoices.send` authorization remains mandatory for the send route; Staff cannot send invoice email.
- Public invoice links still use the existing random-token, hash-at-rest, expiry, and revocation flow. The email retains that link without logging the raw token separately.
- Delivery records store only the attachment type, related invoice reference, recipient, safe email content metadata, status, and safe failure reason. They never store the PDF binary, SMTP credentials, or payment QR payload.

## Delivery Lifecycle

The invoice send screen queues the normal delivery record and confirms that a PDF attachment is queued. The worker sets the normal `sending`, `sent`, or `failed` state. Attachment-render failures receive the safe reason `Invoice PDF attachment could not be generated.` and preserve the existing retry scheduling. The invoice detail page shows the latest standard invoice email as queued, sent, or safely failed; the existing Email Delivery Logs remain the retry surface.

Invoice state is not changed by PDF rendering or mail transport failures. Existing reminders and receipt email behavior are intentionally unchanged.

## Known Limitations

- A `sent` delivery means the configured SMTP transport accepted the message; inbox delivery, bounce, and suppression-state webhooks remain future work.
- The PDF is regenerated at worker execution time, so an email that remains queued while an administrator changes the tenant template will use the active template at send time.
- PDF binary generation occurs in the queue worker process; deploy workers need the existing DomPDF and GD runtime requirements.
- There is no long-term PDF archive in this phase. Customers can use the existing protected link and authorized users can download again from the invoice workspace.

## Deployment and Rollback

No migration is required. Deploy application code and built assets, then clear and rebuild Laravel caches before restarting queue workers. Keep the normal queue worker running so delivery records leave the queued state.

To roll back, deploy the prior application revision, clear/rebuild caches, and restart queue workers. Existing delivery records remain safe: older application code ignores the attachment descriptor and no invoice or SaaS billing data requires reversal.
