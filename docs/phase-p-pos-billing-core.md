# Phase P: POS Billing Core V1

## Scope and reused architecture

Phase P extends the existing POS terminal, register/session, product catalogue, stock ledger, customer lookup, GST calculator, receipt PDF, audit log, domain-event, outlet-access, and reporting foundations. It does not introduce parallel product, customer, stock, tax, payment, or invoice systems.

The billing route remains `/pos` with dedicated terminal and mobile-safe views at `/pos/terminal` and `/pos/mobile`. Existing offline-POS code is not expanded or enabled by this phase; completed online retail billing uses the server transaction described below.

## Billing flow

1. An authorized user selects an assigned outlet and, where the company requires it, an open POS register session.
2. Barcode or SKU entry resolves an exact active product first. The product catalogue remains outlet-filtered and does not preload the full catalogue.
3. The cart is client-side convenience state only. The server reloads active products, applies authorized prices and discounts, then recalculates all final values.
4. Completion validates session, outlet, quantities, stock, GST configuration, payments, and a stable idempotency key inside one database transaction.
5. The service allocates a fiscal-year invoice number, writes sale/item/payment snapshots, posts stock movements, records audit/domain events, and commits together.
6. The user is redirected to a printable receipt and A4 PDF download.

Any validation failure rolls back the complete transaction. Client totals, tax values, stock, and payment status are never authoritative.

## Money precision and GST

`PosBillingTotalsService` performs POS monetary calculations in integer paise. Quantities use a fixed three-decimal representation. Product sale prices, discounts, GST rates, and payment amounts are parsed as bounded decimals before persistence.

The service reuses `GstTaxCalculator` for tax-inclusive and tax-exclusive treatment. A taxable sale requires valid company GST state configuration and a place of supply. GST values are snapshotted at item and sale level:

- taxable value
- CGST, SGST, IGST, and cess
- product tax profile/rate and HSN/SAC
- tax treatment and place of supply

No GST rate is invented. A zero-rated item can be sold without GST configuration. This is a GST-ready invoice foundation; it does not file GST returns or submit e-invoices.

## Pricing, discounts, and payments

Configured product selling price is the default. A price change requires `pos.price.override`; manual fixed or percentage discounts require `pos.discount.apply`, and company discount caps require `pos.discount.override` when exceeded.

Supported recorded payment methods are cash, card, UPI, bank transfer, and other/manual. Card, UPI, and bank transfer require a manual reference. Split payments are supported. Only cash may exceed the grand total, producing change due. No card data, gateway secret, or external payment gateway is stored or called.

## Invoice sequencing and idempotency

`pos_invoice_sequences` allocates an invoice number under a row lock per company, outlet, fiscal year, and prefix. The format is `PREFIX-YYYY-YY-000001`. Concurrent first allocation handles a unique-series race by reloading the row under lock.

Held carts receive a non-invoice `HLD-...` reference. A completed bill receives its final receipt/invoice number only at successful completion. The supplied `completion_key` is unique per company. Retrying a completed request with the same key returns the original sale instead of posting a duplicate payment or stock movement.

## Register, stock, holds, and voids

`pos_billing_settings` provides per-company session enforcement and GST price-mode controls. When session enforcement is on and active registers exist for the outlet, checkout requires an open register session.

Stock changes only after completed sale creation. Each tracked product posts one `sale` stock movement with the sale reference and before/after values. Held carts do not reserve or decrement stock; price, product state, and availability are rechecked upon completion.

Held carts are scoped to the cashier and outlet, can be resumed once, and can be discarded with confirmation. Completed sales cannot be deleted. A privileged void sets an audited immutable `voided` status and requires a reason. Phase P intentionally does **not** reverse stock or payments on void; returns/refunds own those future accounting and inventory actions.

## Permissions and audit events

The established POS permissions remain in force, with Phase P aliases for billing access, resume, payment recording, invoice print/reprint, and all-sales visibility. Backend services recheck tenant, outlet, register-session, price, discount, and completed-sale access; sidebar visibility is not used for authorization.

Events/audit records cover register lifecycle, held/held-cancelled bills, completed sales, voids, and receipt PDF prints. Payment references are stored only as manual references; metadata contains no secrets.

## Receipt output and reporting

The existing responsive receipt supports 80 mm/58 mm print layouts, browser printing, and A4 PDF download. It shows product snapshots, HSN/SAC, tax breakdown, payment breakdown, change, invoice number, outlet, cashier, and configured customer details.

Existing Phase H reports continue to query only completed POS sales and stock movements. Held and voided sales do not inflate completed-sales reporting.

## Known V1 limitations

- No returns, exchanges, refunds, or automatic void reversals.
- No new offline synchronization work in Phase P; existing offline foundations are unchanged.
- No Bluetooth/native scanner or printer SDK.
- No payment-gateway integration, accounting journals, GST filing, e-invoice submission, or payment QR generation.
- No loyalty redemption or store-credit settlement changes.
- Google Calendar/Meet and AI forecast integrations are not involved in billing and remain inactive.

## Production deployment

Run standard maintenance, code, migration, cache, and asset deployment procedures. The Vite build directory must be synchronized from `retailpos-platform/public/build/` to `/home/u237933956/domains/app.retailpos.biz/public_html/build/`. Do not remove existing POS or Google-related database columns; this release only adds forward-compatible POS billing tables and columns.
