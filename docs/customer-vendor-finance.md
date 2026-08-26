# Customer and Vendor Finance

## Authoritative balances

`ReceivableService` is the shared read path for customer balances, aging, statements, Owner Command Center metrics, notification conditions, and deterministic AI facts. It reads issued CRM invoice `balance_due` values after the existing invoice lifecycle has applied valid payment allocations and finalized credit allocations. It never rebuilds invoice tax or totals and never treats a customer credit as a cash refund.

`PayableService` is the equivalent supplier read path. It reads approved purchase invoice `outstanding_total` after existing supplier-payment allocations. Purchase returns currently have no authoritative purchase-invoice linkage, so this release does not guess supplier return credits or reduce payables from unrelated return records.

Opening balances are calculated from authoritative transactions dated before the requested statement period. The system does not create inferred opening-balance entries.

## Payments, credits, and reconciliation

Customer payments can be recorded against one or several outstanding invoices or left partly or fully on account. `CustomerPaymentAllocationService` uses integer minor-unit comparisons, a database transaction, payment and invoice row locks, company/outlet-scoped invoice queries, and request-derived idempotency keys. A later reconciliation can allocate an on-account remainder without rewriting the payment or invoice document.

Finalized CRM return credit can be explicitly applied through `CustomerCreditService`. The allocation is atomic, tenant/outlet scoped, idempotent, audited, and limited by both the remaining credit and invoice balance. It creates no payment and no automatic bank, cash, card, wallet, or gateway refund. `Refund due` remains zero until RetailPOS has an authoritative refund-due ledger distinct from available customer credit.

Supplier payments continue through the established purchasing service and allocation tables. The finance reconciliation page surfaces remaining customer and supplier payment amounts; it is an internal transaction review and does not claim to be bank-feed reconciliation.

## Credit limits

Customer credit limits and optional terms are tenant data. Invoice issue checks net exposure after available customer credit. Ordinary users cannot exceed the limit. Authorized managers and administrators can override it only with a reason; the override is audited. A nullable limit means unlimited and does not alter historical invoices.

## Security and exports

Every finance query begins with the acting company and the existing `OutletAccessService` scope. Sales users retain the established assigned-sales restrictions where applicable. Route permissions separate receivables, payables, statements, payment allocation, credit-limit management/override, reconciliation, and exports.

Receivable/payable screens are paginated. CSV exports stream the same authorized filtered query in bounded chunks and neutralize spreadsheet formula prefixes. Statements use deterministic date/type/id ordering and share one data provider across HTML, PDF, and CSV.

## Migration and operations

`2026_08_28_010000_create_customer_vendor_finance_foundation.php` is additive for existing customer, invoice, payment, return, purchase, and supplier data. It adds customer credit terms, permits on-account customer payments, creates normalized customer payment/credit allocation ledgers, and creates reconciliation metadata. Existing valid direct invoice payments are backfilled into allocation rows without changing their amount or status.

Fresh install and pre-feature upgrade paths are supported. Before live data uses nullable on-account payments, rollback/reapply is available for qualification. After an on-account payment exists, do not run a destructive production rollback because the historical schema required every payment to reference one invoice. Use a forward remediation migration if the release must be disabled; application navigation and permissions can be withdrawn while additive data remains inert.

No Google Calendar/Meet integration, bank feed, payment gateway action, automatic refund, inferred historical opening balance, or autonomous finance mutation is introduced.
