# POS Returns, Refunds and Exchanges V1

## Scope

Phase Q adds post-sale returns to the existing POS billing ledger. It never edits a completed `pos_sales` invoice total, sends a payment-gateway refund, files GST, or enables offline returns. A completed return is an additional immutable credit-note-style document linked to its original sale.

## Data and Services

Migration `2026_08_06_010000_create_pos_return_foundation_tables.php` adds `pos_return_settings`, `pos_return_sequences`, `pos_returns`, `pos_return_items`, and `pos_refunds`, plus read-only derived return metadata on `pos_sales`. Existing `customer_wallet_transactions` provides customer store credit; no competing wallet is introduced.

Follow-up migration `2026_08_06_010100_add_return_line_guards_to_pos_movements_and_refunds.php` gives each posted stock-restoration movement a nullable return-line reference and enforces one movement per return line. It also enforces unique non-null external refund references within a company, preventing an operator from recording the same payment-provider reference twice.

`PosReturnService` is the transaction boundary. It uses `PosSaleRepository` and `OutletAccessService` for company/outlet scope, original POS sale-item snapshots for all money and GST calculations, the existing stock ledger for restoration, `WalletService` for store credit, and the Audit/Domain Event services for traceability. Completion locks the return and sale, posts each stock movement at most once per return line, records manual refunds, issues any store credit, allocates the credit-note number, and then updates only the sale's derived return status and total.

## Calculation and Controls

Every amount is calculated in integer paise. A return line uses the original sold quantity and the sum of prior approved/completed return quantities. For each stored original line value, the service calculates the rounded cumulative allocation after the requested quantity and subtracts the rounded allocation before it. This makes repeated partial returns deterministic and ensures the final return reconciles to the immutable line snapshot.

Tenant settings cover return-window days, receipt confirmation, manager approval, cashier initiation, original-method refunds, store credit, damaged-goods restocking, anonymous returns, and an optional approval threshold. Users cannot return draft, held, voided, out-of-scope, expired, or fully returned sales; nor can they exceed remaining item quantities. Idempotency keys prevent duplicate completion. A manager cannot approve their own request unless they are an Administrator. Only the requester or an outlet manager can cancel a draft or pending request; cancelled requests are audited and do not post stock, wallet, or refund effects.

## Inventory, Refunds and GST

Restock returns increase the exact branch/warehouse/location used by the original sale movement. Damaged, scrap, quarantine, and no-stock-change dispositions produce neutral, auditable movements and never make goods saleable. Cash, card, UPI, bank transfer, other, and store-credit refund records are operational records only; no payment provider API is called and no card data is retained. Cash refunds are management-authorized and reduce the expected cash for the original register session at close.

Credit notes preserve original product, SKU, HSN/SAC, price, discount, taxable amount, and CGST/SGST/IGST/cess allocations. `RET-FY-######` and `CN-FY-######` values come from a row-locked per-company/outlet/fiscal sequence. The PDF labels the result as a credit-note-style adjustment and explicitly does not claim GST filing.

## Routes and Permissions

The POS routes are `/pos/returns`, `/pos/returns/create?sale={id}`, `/pos/returns/{id}`, approval, rejection, completion, settings, and completed-credit-note PDF endpoints. `pos.returns.view` and `pos.returns.initiate` follow POS user access. Approve, complete, override-window, PDF/reprint, and settings capabilities are management-only. The Module Registry exposes Returns under POS only to users with `pos.returns.view`.

## Reporting and Limitations

The existing authorised reporting service now exposes a separate `sales_returns` report with completed return/refund, store-credit, tax-adjustment, and exchange-adjustment totals and rows. Gross POS sales remain visible as their original completed-sale value, while dashboard net sales and profitability use gross POS sales less completed sales-return totals. Purchase returns retain their existing report and accounting meaning.

V1 exchanges link an authorised, completed replacement POS sale from the same outlet; RetailPOS does not create a second sale/payment system. Refunds are manual operational records, not gateway confirmations. There is no cross-outlet return, no offline return, no separate quarantine inventory bucket, no automatic GST filing, no payment-gateway refund, and no automated fraud decision.
