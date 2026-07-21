# Purchase Finance Architecture

Phase 1A extends the existing supplier, purchase order, GRN, stock posting, return, approval, dashboard, and navigation foundations. It does not duplicate them.

`purchase_invoices` records a supplier invoice and immutable line snapshots. Lines may reference `goods_receipt_items`; accepted quantity minus previously invoiced quantity limits the invoiceable quantity. The server recalculates all invoice tax and totals.

`supplier_payments` records a payment or advance. `supplier_payment_allocations` links it to approved invoices. Supplier outstanding is derived from approved invoice outstanding values and unallocated payment balances; no separate supplier balance is stored.

Financial document status changes, approval, cancellation, and payment reversal are recorded in the existing purchase approval log and audit log. Posted records are never hard-deleted.

## Boundaries

This phase is a purchase payable sub-ledger, not a general ledger. It does not create accounting journal entries, bank reconciliation, GST portal reconciliation, or statutory return filing.
