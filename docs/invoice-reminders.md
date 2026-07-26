# Invoice Payment Reminders

## Scope

Phase 8F adds tenant-scoped email reminders for unpaid CRM sales invoices. It reuses the existing CRM invoice PDF service, selected Invoice Design, secure public invoice link, `notification_deliveries` ledger, delivery lifecycle, queued email job, retry schedule, and generic SMTP integration. It does not affect SaaS subscription invoices, platform billing, invoice amounts, payment records, GST calculation, Google Calendar, Google Meet, or WhatsApp sending.

## Stages and Recommended Defaults

Each company receives five configurable automatic stages. Automatic dispatch is disabled by default so a tenant must deliberately opt in.

| Stage | Default timing | Default intent |
| --- | --- | --- |
| `due_soon` | 3 days before due date | Friendly preparation reminder |
| `due_today` | On the due date | Clear, neutral notice |
| `overdue` | 3 days after due date | Firm, professional reminder |
| `second_overdue` | 7 days after due date | Follow-up on the outstanding balance |
| `final_notice` | 15 days after due date | Formal contact request without unsupported legal claims |

`manual` is a delivery source, not an automatic stage. A user can choose an enabled stage when sending a manual reminder from an invoice.

`crm_invoice_reminder_settings` holds the company master switch and cooldown. `crm_invoice_reminder_rules` stores each company stage, timing offset, enabled state, attachment choice, secure-link choice, subject, introductory text, and sort order. Reminder stage and source are stored on the existing `notification_deliveries` record for history and idempotency; no second delivery ledger exists.

## Eligibility and Fatigue Protection

An automatic reminder is considered only when its company is active, automatic reminders are enabled, the invoice is issued/finalised, due dated, outstanding, and has a valid recipient email. Draft, paid, cancelled, void, zero-balance, paused, invalid-recipient, and inactive-company invoices are skipped. Partially paid invoices use their current outstanding balance.

The scheduled run matches the configured offset against the invoice due date. It does not resend an automatic stage that is queued, processing, sent, or delivered. A successful automatic final notice stops future automatic reminders. Bounced, rejected, and permanently failed reminder records stop further automatic reminders for that invoice until the contact data is corrected.

The tenant cooldown defaults to 24 hours and applies across reminder stages. Manual reminders are also rate protected. The `company_id` plus delivery idempotency key remains the database concurrency boundary, so concurrent scans cannot create duplicate automatic delivery jobs. The delivery worker performs a last eligibility check before sending an automatic reminder, cancelling it when the invoice was paid, cancelled, disabled, or otherwise became ineligible after queueing.

## Manual Flow

Users with `sales.reminders.send` can select **Send payment reminder** from an eligible invoice. The confirmation surface shows the fixed customer email, invoice number, outstanding balance, selected stage, PDF selection, and the fact that the secure link is retained. A short plain-text note is optional. The customer email cannot be changed from this form; update it through the invoice/customer data instead.

Manual deliveries have `reminder_source=manual` and an `invoice_reminder_*` template key, keeping them distinct from the original `invoice_issued` email. Rapid duplicate submissions are blocked by the same tenant cooldown and daily idempotency key.

## Email, PDF, and Secure Link Behavior

Reminder content includes the customer, business, invoice number, issue/due dates, original total, paid amount, outstanding balance, stage, and contact guidance. Subjects and introductory text are plain text only. The supported safe placeholders are `{invoice_number}`, `{due_date}`, `{outstanding_balance}`, and `{business_name}`.

When a rule or manual form enables the attachment, `InvoiceEmailAttachmentService` generates the active tenant Invoice Design in memory at **send time**. No reminder PDF is stored on disk. That means the current design, GST presentation, and configured payment QR behavior apply when the queue worker sends the email. The secure public link is generated at **queue time**, remains hashed at rest, and expires after 30 days for reminder use. Manual reminders always retain this link; automatic rules can disable it.

Generic SMTP acceptance produces `sent`, not provider-confirmed `delivered`. The disabled-by-default provider webhook foundation remains the only way to receive a trusted delivered/bounce/rejection event. Existing retry rules continue to retry temporary failures only; permanent failures, bounce, and rejection are not retried.

## Authorization and History

`sales.reminders.manage` is available to Administrator and Manager roles. It protects **Settings → Invoice Reminders**, including recommended-default restoration. `sales.reminders.send` follows the existing sales invoice send policy. Normal invoice repository and route authorization continue to enforce tenant and Sales assignment boundaries.

The invoice detail page keeps original invoice delivery history separate from payment reminder activity. Reminder history shows stage, automatic/manual source, recipient, queued/sent/failure time, retry count, attachment indicator, safe failure reason, and the user for manual actions. The history query remains invoice- and company-scoped.

## Scheduler and Hostinger

`invoices:dispatch-reminders` runs hourly through Laravel's scheduler. It evaluates invoices in chunks of 100, supports `--dry-run`, and supports `--company=<id>` for controlled tenant checks. Console output contains aggregate counts only, never customer details or message bodies. Company-local dates use `companies.timezone`, falling back to `app.timezone`.

The production scheduler should run Laravel's scheduler every minute. Because shared hosting may not provide a persistent queue worker, schedule a bounded worker invocation separately:

```cron
* * * * * cd /home/USER/domains/retailpos.biz/retailpos-platform && /usr/bin/php artisan schedule:run --no-interaction >> /dev/null 2>&1
* * * * * cd /home/USER/domains/retailpos.biz/retailpos-platform && /usr/bin/php artisan queue:work --stop-when-empty --tries=3 --max-time=50 --no-interaction >> /dev/null 2>&1
```

Adjust the PHP binary and project path to the actual Hostinger account. Confirm the cron user can write Laravel logs/cache and that the production queue connection is configured before enabling automatic reminders. A dry run should be completed for one tenant before enabling the master switch.

## Deployment and Rollback

Deploy the additive migration, run `retailpos:sync-permissions --dry-run` to verify the code-defined matrix, clear and rebuild framework caches, and verify `php artisan schedule:list`. No new environment variable is required. SMTP and the provider webhook configuration remain unchanged.

To pause automatic traffic, disable the tenant master switch; existing invoices and delivery history remain untouched. Reverting the application code before rolling back the migration leaves additive data inert. Do not roll back delivery or invoice migrations on a live system without a separate recovery plan.

## Limitations

- No WhatsApp, SMS, provider-specific adapter, or provider-confirmed delivery is introduced.
- No global customer suppression list is created; reminder safeguards apply only to the related invoice.
- Reminder wording is plain text and does not implement collection fees, penalties, or legal notices.
- A customer email correction is a normal invoice/customer data action, not a reminder-settings feature.
