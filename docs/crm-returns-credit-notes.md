# CRM Returns and Credit Notes

## Boundary

CRM returns are immediate-finalization documents. The original invoice and item snapshots remain immutable. Draft returns, automatic refunds, customer-wallet credits, and inferred historical returns are intentionally outside this phase.

## Financial Rules

- A line can be returned more than once, but finalized cumulative quantity cannot exceed the original quantity.
- Each proportional amount uses cumulative rounding: the new return receives the difference between the rounded cumulative allocation before and after the requested quantity.
- Gross value, line discount, taxable value, CGST, SGST, IGST, cess, and line total come only from the original invoice item snapshots.
- Known COGS uses the original immutable item cost snapshot. Missing cost stays unavailable and is never treated as zero.
- The invoice's original totals are never rewritten. `credited_total` and `balance_due` record the receivable effect separately.
- Credit beyond the open receivable is recorded as `customer_credit_due`; it does not create a refund transaction.
- Existing invoice-level `adjustment_total` is not reversed because no authoritative line allocation exists.

## Inventory Rules

CRM invoices do not universally post inventory. A return can increase sellable stock only when an original stock movement references the CRM invoice and product. The return then posts an immutable `crm_sale_return` movement into that exact warehouse and stock location. Otherwise, financial return processing remains available with restocking disabled.

## Security and Concurrency

All reads are company and outlet scoped through `OutletAccessService`. Invoice rows are locked before remaining quantities are recalculated. A company-scoped idempotency key and a second check after locking prevent browser retries and double clicks from creating duplicate credit notes. Unique sequence and stock-movement constraints provide database-level protection.

## Reporting

Finalized credit-note rows are reversing entries in profitability. They reduce CRM net sales, known COGS, and gross profit using original evidence. GST and sales-return reports expose the credit separately, while Owner Command Center and AI consume the same authoritative report outputs.
