# POS Core Foundation

## Scope

The POS core is separate from CRM quotations and CRM sales invoices. It reuses the existing company, branch, product, shared customer, stock ledger, audit, PDF, email, permissions, and responsive POS terminal foundations. A completed counter sale is stored in `pos_sales`; it is never represented as a CRM invoice.

## Stores and registers

Existing `branches` are extended with optional store/legal/tax/location metadata while keeping the original company-scoped branch code and active state intact. A branch must be active to create a new register-aware sale.

`pos_registers` are company and branch scoped. Register codes are unique within a branch. `pos_register_sessions` record opening cash, expected cash, closing cash, variance, operator, timestamps, and notes. A register can have only one open session. Once an active branch has registers, the terminal requires the cashier to select an open register before checkout. This compatibility rule allows existing branches to migrate to registers without breaking historical or legacy bills.

## Products, catalogue, stock, and customers

The existing tenant-scoped products, SKU/barcode search, self-referencing variant model, categories, brands, units, tax rates, stock levels, stock movements, and shared customer model are reused. Existing product fields support stock, service/non-stock behavior, wholesale prices, tax rates, HSN codes, and negative-stock policy. New sale-item fields persist product, variant, unit, price-source, discount, and tax snapshots, so later catalogue edits do not alter a completed bill.

Opening stock continues to use the existing ledger-backed inventory workflow. A POS completion deducts stock transactionally for tracked products; a void creates a compensating `sale_void` movement. Services and non-stock items do not affect stock.

## Billing lifecycle

1. Search or scan an active product by name, SKU, or barcode.
2. Add it to the responsive desktop/tablet/mobile cart and optionally select or quick-create a shared customer.
3. Hold a bill without posting stock or payments, or complete it with a payment/split payment.
4. The checkout service recalculates the bill server-side, validates product activity and stock, assigns a transaction-safe receipt number, records payment snapshots, posts stock movements, and writes audit/domain events.
5. A completed sale is immutable. A manager may void it with a mandatory reason; payments are marked reversed and stock is restored transactionally. The original receipt number remains reserved.

The terminal can select an open register. Its receipt prefix is used for newly completed bills. Existing held-sale, responsive terminal, PWA/offline foundations, and customer product suggestions remain in place.

## Receipts and delivery

The existing printable receipt supports standard, 80mm, and 58mm print treatments. This release adds an A4 PDF receipt route that contains customer-facing snapshots only, never internal notes or payment credentials. POS-specific email and WhatsApp receipt delivery remain future adapters; they can reuse the existing email and share-link foundations and must never block sale completion when a delivery provider is unavailable.

## Access and audit

The existing POS access permission remains in effect. Added POS permissions cover register viewing/management, session open/close, voiding, stock opening, product management, reporting/export, settings, and dashboard access. Register, session, completion, and void actions are company scoped and audited. No card numbers, CVVs, PINs, raw public tokens, or credentials are stored.

## Deployment and rollback

Apply the additive `2026_07_20_010000_add_pos_operational_controls` migration before creating registers. It does not seed branches, products, or commercial data. Create a register and open a session for each live counter before requiring register-aware checkout.

Before customer use, verify the branch is active, the user has the appropriate POS permissions, product stock locations exist, and PDF/email dependencies are configured as appropriate. A database rollback is appropriate only before operational register/session data exists. After live usage, preserve history and use sale voids rather than destructive data changes.

## Limitations and later phases

This foundation does not add purchases or suppliers, warehouse transfers, stock counting, returns or exchanges, loyalty/reward settlement, advanced offers, GST return filing, e-invoicing, e-way bills, payment gateways, accounting integration, or hardware-specific scanner drivers. Tax and GST fields are calculation/presentation foundations and require accountant review. Product import tooling, branch-level price books, complex promotion rules, and automated receipt messaging remain future extensions.
