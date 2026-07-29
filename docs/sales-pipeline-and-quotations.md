# Sales Pipeline and Quotations

## Scope

This release extends the existing tenant-scoped CRM. It does not add invoicing, accounting, POS billing, payment collection, or Google Calendar/Meet behaviour.

## Workflow

Leads retain the established CRM status model and Kanban board at `/crm/pipeline`; `/sales/pipeline` is a permission-protected entry point to the same board. Existing status history and audit events remain the source of truth. Sales users are limited to their assigned CRM records by the existing repositories and policies.

Opportunities are separate tenant-scoped commercial records linked to a lead, optional CRM company/contact, owner, value, probability, expected close date, and stage history. The weighted value is `expected_value * probability_percentage / 100`. Create one from a lead at `/sales/leads/{lead}/opportunities/create`; review the pipeline at `/sales/opportunities`.

## Follow-ups

The existing `crm_activities` table remains the unified activity system. Follow-up records now carry status, timezone, reminder time, completion/cancellation actors, optional opportunity, and completion outcome. They are open, completed, cancelled, or query-derived overdue without relying on a scheduler. `/crm/follow-ups` and `/sales/follow-ups` expose the existing queue.

## Quotations

Quotations remain company-scoped and use the existing transactional numbering and server-side calculation flow. Browser totals are ignored. Items snapshot their description, unit, pricing, fixed or percentage discount, tax, and ordering. Accepted quotations remain non-editable; use a new draft for a commercial revision until the broader revision UI is introduced.

PDFs use the installed DomPDF integration and include only customer-facing data. Internal remarks never appear in the public page or PDF. Contact information is included for India (`+91 8072682244`), Malaysia (`+60 104305163`), Singapore (`+65 92475024`), `info@retailpos.biz`, and `global@retailpos.biz`.

## Public Links and Decisions

Public quotation URLs use a cryptographically random opaque token. Only its SHA-256 hash is stored; neither the raw token nor its URL is persisted. A link is shown only when issued, can be regenerated, expires with the quotation validity period, is rate-limited, and is excluded from indexing with `noindex, nofollow` headers and meta tags.

The public page exposes client-safe content, PDF download, and one customer accept/reject response. A decision needs a name and confirmation, records safe audit metadata, updates view/decision timestamps, locks conflicting responses, and notifies the lead owner through the existing database notification channel.

## Email and WhatsApp

Quotation email uses `EmailDeliveryService` with the `quotation_sent` template key and the existing queue/delivery-log infrastructure. No SMTP work runs inline. If SMTP is absent, the delivery is recorded as `skipped_not_configured` and the quotation stays usable. Idempotency prevents duplicate matching sends.

WhatsApp is a click-to-send `wa.me` link only. It uses normalized phone numbers and records a generic preparation event without placing the full customer phone number in audit metadata.

## Deployment and Rollback

Run migrations, clear optimized caches, start the normal queue worker for configured SMTP delivery, and run the test suite. The release contains no Google Calendar/Meet migrations, routes, configuration, documentation, or environment variables. Roll back only through normal forward remediation in production; do not restore plaintext public-token storage.
