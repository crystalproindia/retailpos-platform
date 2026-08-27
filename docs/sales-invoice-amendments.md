# Sales Invoice Amendments

RetailPOS keeps draft editing and issued-invoice amendments as separate workflows. Draft invoices continue through the existing full edit form. An active issued, sent, viewed, partially paid, paid, or overdue invoice can receive an additive amendment from an authorized user. Cancelled, void, fully credited, and cross-outlet invoices cannot be amended.

## Immutable ledger

`crm_invoice_amendments` stores the version transition, reason, totals before/added/after, tax components, actor, time, and a tenant-scoped idempotency digest. `crm_invoice_amendment_items` stores customer-safe line snapshots and links each addition to its ordinary `crm_invoice_items` row. Original issued rows are never updated or removed by the amendment service. An incorrect original row must be credited through CRM Returns and then replaced with a new amendment line.

The invoice begins at version 1. Finalization locks the invoice row and rejects a stale `expected_version`, so two users cannot silently overwrite each other. The idempotency key prevents a browser retry or double click from creating the same amendment twice.

## Calculation and finance

Added lines reuse `InvoiceService` quantity, discount, tax-exclusive revenue, product cost, category, and brand snapshots. GST components use `GstTaxCalculator` and the invoice's snapshotted supplier/place-of-supply states. Existing lines and tax snapshots are not recalculated. Free-text services retain `unavailable` cost rather than zero cost.

The authoritative invoice total is increased by the confirmed amendment once. `FinanceBalanceService` then derives paid, credited, and outstanding amounts from the existing payment and credit allocations. A paid invoice can therefore become partially paid with a new outstanding amount while its payment history remains intact. `CreditLimitService` evaluates only the additional exposure and uses the existing override permission and audited reason.

Customer statements, reminders, sharing, deterministic AI, GST reports, profitability, and the Owner Command Center continue reading the authoritative invoice/items and require no amendment-specific arithmetic.

## Inventory and returns

An inventory-tracked product requires an authorized warehouse in the invoice outlet. `StockService::recordSale` posts one linked outbound movement per added invoice item. Free-text and non-inventory product lines create no stock movement. CRM Returns first resolves this explicit item movement, then retains its legacy invoice/product fallback for older records. This lets amended lines be credited and, where requested, returned to their original warehouse without guessing.

## Documents and limitations

Current invoice views and PDFs include all authoritative lines and show an `Amended · Version N` marker. Internal invoice detail shows the permanent amendment reasons and line summary; technical audit details are not exposed to customers.

Phase 1 is additive only. It does not edit or delete original issued lines, allocate document adjustments across amendment lines, create a separate debit-note document, choose batch/serial identities, or amend cancelled/fully credited invoices. Tracked products with batch or serial requirements should continue through the specialized inventory/POS workflow until an explicit CRM fulfilment selector is introduced.
