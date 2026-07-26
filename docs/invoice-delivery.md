# Phase 8C and 8D: Invoice Delivery, Tracking, and Reliability

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

## Delivery Lifecycle and Reliability

The invoice send screen queues the normal delivery record and confirms that a PDF attachment is queued. The lifecycle is `queued`, `processing`, `sent`, `delivered`, `temporarily_failed`, `permanently_failed`, `bounced`, `rejected`, or `cancelled`. Lifecycle changes are append-only `notification_delivery_events` records; terminal delivery states cannot move backwards.

`sent` means the configured SMTP transport accepted the message. It does not claim inbox delivery. `delivered` is set only by a verified provider delivery event. Attachment-render failures receive the safe reason `Invoice PDF attachment could not be generated.`; transport failures receive a distinct safe transport reason. Both may retry with bounded exponential timing. Invalid recipients are `rejected`, while permanent failure, bounce, and rejection are never automatically retried.

The fallback scheduler claims due temporary failures atomically before dispatching a retry, avoiding duplicate queued retries. Each send attempt regenerates the existing Phase 8C PDF in memory, preserving the current selected invoice design without storing a duplicate PDF file.

The invoice detail page shows the latest standard invoice email, its last attempt, retry count, a masked provider reference where available, and a tenant-scoped history. It exposes one email action at a time: normal send, or an authorized manual resend after a temporary or permanent failure. A manual resend creates a fresh queued delivery record, preserves the invoice link and PDF descriptor, retains the original history, and is rate-protected against rapid duplicates. It never changes invoice financial state.

## Provider Events and Webhook Security

`POST /api/email-delivery/{provider}/webhook` is a provider-neutral event boundary and is disabled by default. No external provider integration is enabled by this release. A future adapter must set the provider and provider message ID on the delivery at send time, normalize the provider event to the documented payload, and use the same lifecycle service.

When enabled, the generic endpoint requires a raw-body HMAC SHA-256 signature in `X-RetailPOS-Email-Signature`, formatted as `sha256=<digest>`, using `EMAIL_DELIVERY_WEBHOOK_SECRET`. The body must contain `company_id`, `event_id`, `event_type`, `provider_message_id`, and an ISO-8601 `timestamp`. Events are rate limited, expire after `EMAIL_DELIVERY_WEBHOOK_TOLERANCE_SECONDS` (300 seconds by default), verify the delivery's company, provider, and provider message ID, and are replay-safe through the unique provider event ID. Unsigned, stale, unknown, cross-company, and invalid events are rejected with no internal details. Raw bodies, secrets, and email content are never persisted or logged.

Set `EMAIL_DELIVERY_WEBHOOK_ENABLED=true` only after a provider adapter and secret are configured. `EMAIL_DELIVERY_PROVIDER` is intentionally empty in this release because the repository's active configuration identifies only generic SMTP, not a credentialed provider with a verified webhook adapter. The settings workspace exposes only safe cache-backed event diagnostics: enabled state, selected provider, accepted/rejected event times, signature failures, processed events, and ignored duplicates. The supported normalized V1 event types are `delivered`, `bounced`, `rejected`, `permanently_failed`, and `temporarily_failed` (with `bounce`, `hard_failed`, `soft_failed`, and `deferred` accepted aliases).

Invoice state is not changed by PDF rendering or mail transport failures. Existing reminders and receipt email behavior are intentionally unchanged.

## Known Limitations

- No external mail provider adapter is bundled. The generic event endpoint is a disabled-by-default contract, so this release does not claim provider delivery confirmation until a verified adapter is configured.
- The PDF is regenerated at worker execution time, so an email that remains queued while an administrator changes the tenant template will use the active template at send time.
- PDF binary generation occurs in the queue worker process; deploy workers need the existing DomPDF and GD runtime requirements.
- There is no long-term PDF archive in this phase. Customers can use the existing protected link and authorized users can download again from the invoice workspace.

## Deployment and Rollback

Run the additive migration before deploying code: `php artisan migrate --force`. Deploy application code and built assets, then clear and rebuild Laravel caches before restarting queue workers. Keep the normal queue worker and scheduler running so delivery records leave the queued state and temporary failures can recover.

To roll back, deploy the prior application revision, clear/rebuild caches, and restart queue workers. Existing delivery records remain safe: older application code ignores the attachment descriptor and no invoice or SaaS billing data requires reversal.
