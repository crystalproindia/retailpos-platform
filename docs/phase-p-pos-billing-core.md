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

## Company branding and document logos

Company Branding lives in **Settings → Company Profile → Company branding**. It is deliberately separate from CMS website media: CMS files remain public website assets, while document logos are private tenant files stored on the local private disk under `companies/{company_id}/branding/`.

Two nullable company paths are supported:

- `company_logo_path`: the primary business logo.
- `invoice_logo_path`: an optional invoice and receipt override.

The renderer resolves document branding in this order: invoice/receipt override, company primary logo, then no image. Branch logos are not part of the current branch profile architecture, so the company default applies to every outlet. No invoice-template record stores a separate uploaded logo.

Only PNG, JPEG, and WEBP images up to 2 MB and 5000 × 5000 pixels are accepted. The server derives an opaque UUID filename from the verified MIME type, never trusts the original path, does not accept SVG or remote URLs, and does not expose document-logo paths through the public storage disk. Documents receive an in-memory data URI only while rendering, allowing browser, Dompdf A4, and thermal receipt output to work without a browser-only asset URL.

Invoice design settings retain presentation controls in their existing `options` JSON: `show_logo`, `logo_position` (`left`, `center`, `right`), `logo_size` (`small`, `medium`, `large`), and `show_company_name`. Safe defaults are on, left, medium, and on. Every supported A4 design, the public invoice page, CRM payment receipt, POS browser receipt, and POS A4 receipt use the same central resolver. When the file is missing or logo display is off, the company name remains visible as the document fallback.

Logo uploads, replacements, removals, and invoice presentation setting changes are audited without binary content. Company Profile permission (`company.profile.update`) is required for branding changes; cashier users can print the configured documents but cannot alter branding.

## Known V1 limitations

- No returns, exchanges, refunds, or automatic void reversals.
- No new offline synchronization work in Phase P; existing offline foundations are unchanged.
- No Bluetooth/native scanner or printer SDK.
- No payment-gateway integration, accounting journals, GST filing, e-invoice submission, or payment QR generation.
- No loyalty redemption or store-credit settlement changes.
- No branch-specific logo override in V1; the company logo is the outlet default.
- No SVG input, image optimization pipeline, printer SDK, or external image hosting.
- Google Calendar/Meet and AI forecast integrations are not involved in billing and remain inactive.

## Production deployment

Run standard maintenance, code, migration, cache, and asset deployment procedures. The Vite build directory must be synchronized from `retailpos-platform/public/build/` to `/home/u237933956/domains/app.retailpos.biz/public_html/build/`. Do not remove existing POS or Google-related database columns; this release only adds forward-compatible POS billing tables and columns.
