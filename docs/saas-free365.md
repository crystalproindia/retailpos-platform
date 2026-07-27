# Free 365 Package

`free-365` is a stable plan code, not a display-name check. The editable SaaS plan record supplies the feature and limit snapshots used by subscriptions created from it.

## Rules

- Activation creates an active subscription ending exactly 365 days later.
- Automatic renewal is disabled. Expiry never creates a second Free 365 subscription.
- The package includes `pos.billing`, `sales.invoices`, `inventory.basic`, `customers.basic`, and `dashboard.basic`.
- It has one active user, one active outlet, and 25 finalised sales invoices per tenant calendar month.
- Draft, cancelled, and void invoices do not count. Issued, sent, viewed, partially-paid, paid, and overdue CRM invoices count. Completed POS sales share the same meter.
- Meter boundaries use the tenant company timezone. Usage exposes used, remaining, reset timestamp, and current package only to that tenant.

## Enforcement

`UsageService` takes a locked company row inside the invoice/POS write transaction before reading the shared monthly meter. This serializes concurrent finalisation attempts and prevents a 26th finalised invoice.

An expired Free 365 account can sign in, view its records, and use existing authorised GET/export paths. It cannot write through protected application paths. Its data and subscription history are retained.

`saas:process-renewals` records readiness events 14, 7, and 1 day before expiry, then expires the subscription on the end date. These events are intentionally notification-ready; delivery is not assumed unless the configured notification channel is safe and tested.

## Deployment and rollback

Run migrations forward, clear configuration/route/view caches, and run `saas:process-renewals --dry-run` before enabling entitlement enforcement. Do not roll back this additive migration in production; disable `SAAS_ENTITLEMENT_ENFORCEMENT` to pause feature enforcement while retaining the data model.

## Limitations

Mobile OTP requires a provider-neutral SMS adapter and provider credentials. Email OTP records are ready for the configured email delivery infrastructure, but production delivery policy remains an operational configuration.

Free 365 administrators can use the optional Store Setup Wizard to prepare small industry starter categories and configuration recommendations without changing plan limits or enabling premium modules. See [Store Setup Wizard](store-setup-wizard.md).
# Public registration

Free 365 can be offered through the public, feature-flagged `/start-free` flow. It always resolves the stable `free-365` plan code server-side, provisions one administrator and one primary outlet, and retains the existing 365-day and 25-finalised-invoice monthly limits. See [Public Free 365 Signup](public-free365-signup.md) for OTP, consent, duplicate-account, and deployment controls.
