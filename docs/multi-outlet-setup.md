# Multi-Outlet Setup and Operations

## Discovery and model

RetailPOS already has a tenant-scoped `Branch` model. It is used by users, POS sales, purchases, promotions, warehouses, stock levels, stock movements, and adjustments. This phase reuses that model as the customer-facing **Outlet**; no competing outlet, store, warehouse, or stock system was created.

Products, categories, customers, suppliers, tax rates, and invoice designs remain company scoped. `Warehouse`, `StockLevel`, and `StockMovement` remain the stock authority. A warehouse is created only when a newly created outlet has no warehouse relationship, avoiding duplicate warehouses or stock backfills.

## Data and default outlet migration

The additive outlet migration adds safe display/invoice metadata to `branches` and creates `branch_user_assignments`. For each existing company without a branch, it creates one active `MAIN` / `Main Outlet` using available company details. It makes exactly one existing branch primary, fills only missing user `branch_id` values, and never changes invoices, payments, products, suppliers, customers, stock levels, movement history, or timestamps on historical business records.

The backfill is deterministic and rerunnable: it checks for an existing branch before creation. It does not attempt to split historic company-wide inventory across outlets. The migration is forward-only in production; rolling it back drops the assignment table and new metadata but cannot safely undo a newly created main outlet or user default without an operator decision.

## Access and context

`OutletAccessService` resolves an active current outlet server-side from an authorised session selection, assignment default, existing user branch, or primary outlet. Administrators have company-wide access; managers and operational users require active assignment records, with the legacy user branch as a single-outlet fallback. A browser-submitted outlet is always validated against company and active assignment before it is saved in the session.

Outlet management is tenant scoped. Outlet codes are unique per company, stable after creation, and limited to safe uppercase letters, digits, and hyphens. The service prevents archiving the default or only active outlet. Archive retains history and removes the outlet from new operational context selection. GSTIN validation is format-only and never claims government verification.

The existing `branches` usage limit provides the outlet boundary. Free 365 retains its one active outlet allowance. When the branch limit is reached, the management service returns the friendly message: `Additional outlets require an eligible plan.` No browser value can increase a plan limit.

## Stock transfers

`stock_transfers` and `stock_transfer_items` are tenant-scoped operational records. A draft transfer changes no stock. Dispatch locks source levels, validates available stock unless that product allows negative stock, records an outbound `StockMovement`, and moves the transfer to `in_transit`. Receipt locks destination levels, records an inbound movement, and moves the transfer to `received`. A repeated receipt returns the completed transfer without adding stock again.

Both outlets must be distinct, active, in the same company, and authorised for the acting user. Transfer numbers are generated inside the company transaction. Cancellation, approval chains, partial receipt discrepancy handling, inter-company transfers, and purchase/return outlet assignment are intentionally deferred rather than guessed.

## Invoices, tax, reports, and setup

POS already stores a branch and remains outlet-aware. CRM sales invoices, purchases, returns, consolidated dashboard/report queries, and outlet-specific CRM invoice numbering remain company-scoped in this foundation so historic financial behaviour, payment repair, GST totals, Invoice Designs, and numbering are not altered without a complete document-level migration. Existing invoice and payment calculations are unchanged.

The Store Setup completion and Company Profile expose optional outlet setup. The existing six-step wizard, Free 365 signup, provisioning transaction, invoice design, reminders, delivery, and product CSV limitation remain unchanged. The header switcher appears only when a user has more than one authorised active outlet.

## Flags and operations

- `MULTI_OUTLET_ENABLED=true`
- `MULTI_OUTLET_SETUP_ENABLED=true`
- `MULTI_OUTLET_TRANSFERS_ENABLED=true`
- `MULTI_OUTLET_REPORTING_ENABLED=true`

The flags are documented in `.env.example`. The main module navigation uses the main flag. Saved records are retained when navigation is disabled. Before production, run migrations forward, clear/rebuild caches, verify every tenant has one primary branch, and review plan branch limits. Large tenants should ensure indexes are monitored for stock-transfer and branch-report workloads.

## Audit and limitations

Outlet create/update/archive/default/activation/assignment and transfer create/dispatch/receipt actions are recorded in audit logs using tenant-safe IDs. No GSTIN, full address, customer rows, or product rows are included in audit payloads.

This phase deliberately does not introduce inter-company transfers, warehouse bin-to-bin transfers, partial transfer discrepancy settlement, outlet-level CRM invoice numbering, a new invoice template engine, or a new reporting engine.
