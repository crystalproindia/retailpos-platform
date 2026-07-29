# Invoice Payment Foundation Repair

## Original failures

`InvoicePaymentsFoundationTest` had two failures before Phase C and at the isolated pre-Phase-C commit `4d5916f`:

- `test_pending_payment_does_not_reduce_balance_until_it_is_cleared` raised `Payments can only be recorded against an issued invoice.`
- `test_draft_can_be_updated_but_issued_invoice_cannot_be_silently_changed` did not receive its expected `ValidationException`.

## Root cause and repair

`InvoiceService::issue()` persists and returns a fresh invoice model. The payment and update services previously trusted the caller-provided model before beginning their transactions. A caller retaining the original instance therefore still appeared to have a draft invoice after it had been issued in the database.

The repair reloads and locks the invoice inside the transaction, scoped to the acting user's company, before checking its status or changing financial records. Payment clearing and reversal now likewise reload and lock their payment and invoice records under the tenant scope. Payment idempotency uses the existing integer-minor-unit decimal convention, so equivalent `400` and `400.00` retries share a key.

## Financial and security invariants

- Invoice subtotal, discount, tax, rounding, and grand total calculations are unchanged.
- Only non-pending, non-failed, non-reversed payments contribute to `amount_paid` and `balance_due`.
- Partial, paid, pending, reversal, zero/negative amount, overpayment, and retry protections continue to use integer minor units.
- Payment retry does not create a second payment or alter the invoice total.
- Direct service access cannot load another tenant's invoice or payment for update, clear, reversal, or payment recording.
- All reads that govern invoice/payment mutations happen inside the existing database transactions.

## Regression coverage

`InvoicePaymentsFoundationTest` covers stale-instance payment recording after issue, draft immutability after issue, pending-to-cleared balance behaviour, partial and final payments, reversal, tenant-scoped payment access, decimal-normalised idempotency, amount validation, and unchanged invoice totals.

## Deployment and rollback

This is application-code and test/documentation only: no migration or data backfill is required. Deploy normally after the full suite is green. Rollback is a normal application release rollback; existing payment rows and invoice totals are not changed by this repair.

Multi-Outlet operations do not alter CRM invoice calculation, payment, receipt, or idempotency paths in this release. Existing invoices remain company scoped while outlet-specific CRM invoice numbering is deferred. See [Multi-Outlet Setup](multi-outlet-setup.md).
