# Multi-Outlet Setup and Operations

## Discovery and model

RetailPOS already has a tenant-scoped `Branch` model. It is used by users, POS sales, purchases, promotions, warehouses, stock levels, stock movements, and adjustments. This phase reuses that model as the customer-facing **Outlet**; no competing outlet, store, warehouse, or stock system was created.

Products, categories, customers, suppliers, tax rates, and invoice designs remain company scoped. `Warehouse`, `StockLevel`, and `StockMovement` remain the stock authority. A warehouse is created only when a newly created outlet has no warehouse relationship, avoiding duplicate warehouses or stock backfills.

## Workforce assignment compatibility

Phase I adds employee-level outlet, warehouse, and register assignment records without changing the existing outlet authority. When an employee receives a linked application account, `WorkforceService` synchronizes its active employee outlets to the existing `branch_user_assignments` table. `OutletAccessService` therefore continues to decide POS, report, CRM, inventory, and purchase access exactly as before. Workforce changes never rewrite historical branch ownership on transactions.

## Data and default outlet migration

The additive outlet migration adds safe display/invoice metadata to `branches` and creates `branch_user_assignments`. For each existing company without a branch, it creates one active `MAIN` / `Main Outlet` using available company details. It makes exactly one existing branch primary, fills only missing user `branch_id` values, and never changes invoices, payments, products, suppliers, customers, stock levels, movement history, or timestamps on historical business records.

The backfill is deterministic and rerunnable: it checks for an existing branch before creation. It does not attempt to split historic company-wide inventory across outlets. The migration is forward-only in production; rolling it back drops the assignment table and new metadata but cannot safely undo a newly created main outlet or user default without an operator decision.

## Historical migration verification

Run the complete historical-data preservation check from the repository root:

```bash
bash scripts/verify-phase-g-history.sh
```

The command creates a temporary directory, an isolated SQLite database, and a detached temporary worktree at `e5f1810` (`fix(invoices): repair payment foundation regressions`). It runs every migration available at that commit, creates the historical fixture through the supported pre-Phase-G factories, models, and CRM invoice service, writes a machine-readable before snapshot, then reads and applies only the two Phase G migrations directly from the pinned `e9f46ba` commit to the same database and writes an after snapshot. The caller may therefore run the committed harness from a later descendant without weakening the historical boundary.

The deterministic fixture has two companies/tenants with distinct identifiers, four users spanning administrator, manager, and staff roles, two existing branches for tenant one, three existing warehouses, categories, products, retail customers, CRM invoice customers, suppliers, tax rates, units, stock levels, and stock-movement ledger history. Tenant two deliberately has no historical branch, which exercises creation of its required `MAIN` outlet and assignment of users that previously had no branch. Each tenant also has a draft invoice, an issued unpaid invoice, a partially paid invoice, a fully paid invoice, cleared/recorded payments, fixed and percentage discounts, GST, positive and negative rounding adjustments, stable invoice numbers, and an invoice-template selection. Payments are created through the historical invoice service; their stored receipt values receive deterministic tenant prefixes because `e5f1810` generates receipt ordinals per tenant while its database unique key is global.

`Branch` is the supported outlet equivalent at the historical commit. CRM invoices use `crm_customers`, while the retail customer fixture uses `customers`; both ID sets are included. Roles are the supported enum stored on `users`, rather than a separate roles table.

The snapshots include tenant/company, user, branch, warehouse, category, product, customer, supplier, stock-level, stock-ledger, invoice, payment, and invoice-template identities and counts. They also include per-location inventory, consolidated and outlet-scoped stock totals, invoice statuses and all financial totals, payment statuses and amounts, and per-tenant invoice/payment totals. The after snapshot adds default outlets, outlet/branch identity, outlet-to-warehouse relationships, outlet-user assignments, operational outlet links, and unassigned historical stock.

Verification fails with the exact JSON path and before/after values if a protected identity, count, invoice number/status/amount, payment value/status, template choice, consolidated stock value, or stock-ledger entry changes. It also rejects duplicate/missing default outlets, cross-tenant outlet links, retry-created outlets, assignments, warehouse/stock links, or ledger entries. The safe data-backfill boundary from the Phase G migration is applied a second time and its complete outlet/idempotency projection must remain byte-for-byte equivalent. SQLite `PRAGMA integrity_check` must return `ok` after both the migration and retry.

No repository `.env` file is loaded: the worktree contains only tracked files, and the command exports a testing-only SQLite configuration before Laravel starts. It never references MySQL or production data. The database, JSON snapshots, copied migrations, and detached worktree all live under a safely generated temporary directory and are removed by a trap on success, failure, or interruption, so none can be committed.

Successful output ends with exact entity counts followed by:

```text
protected historical fields: preserved
tenant boundaries: preserved
default outlet rule: exactly one per tenant
safe backfill retry: idempotent
SQLite integrity_check: ok
```

If either pinned commit is unavailable, fetch the repository history. A migration or preservation failure prints the failing command or exact snapshot path; keep that output for diagnosis. Temporary evidence is intentionally cleaned even on failure, so add a local diagnostic pause to the cleanup trap only while investigating and never commit the generated artifacts.

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

### Phase H reporting addendum

The shared `/reports` hub now applies the same outlet boundary to sales, purchases,
current inventory, stock movements, GST, payments, outstanding receivables, purchase
returns, outlet performance, cashier performance, and CSV exports. Administrators
alone may use All Outlets; managers cannot use an unassigned outlet or an All Outlets
parameter. Historical rows for an archived outlet remain visible only through an
Administrator's consolidated scope. See `docs/phase-h-reporting-analytics.md` for
the report-source, filter, export, and known-data limitations.

## Final browser verification — 29 July 2026

The final walkthrough used an isolated persistent SQLite database, synthetic tenants, local file sessions, and a local-only mail log. It did not load production credentials, production MySQL, or customer data. The administrator, manager, sales/cashier, and a second-tenant user boundaries were exercised.

The browser routes actually visited were `/login`, `/dashboard`, `/settings/outlets`, `/settings/outlets/create`, `/settings/outlets/{outlet}/edit`, `/outlet-context`, `/inventory/transfers`, `/inventory/adjustments`, `/inventory/adjustments/create`, `/sales/invoices/1`, `/sales/invoices/create`, `/modules/reports`, `/start-free`, `/start-free/success`, `/getting-started/store-setup`, `/getting-started/store-setup/complete`, and `/getting-started/outlets`. The navigation command palette and mobile drawer were also exercised.

- Outlet management: passed for create, tenant-scoped duplicate-code rejection, default change, default/only-active archive protection, archive visibility, restore, administrator assignment, manager read/manage limits, cashier denial, and a cross-tenant URL returning 404. Archive confirmation is an inline disclosure rather than a native browser confirmation dialog.
- Entitlement and context: creating the second allowed outlet succeeded; the third was rejected with `Additional outlets require an eligible plan.` in an accessible error summary. Only validated outlet fields reach `OutletService`; submitted values cannot alter the server-owned plan limit. The administrator could switch between two active outlets. Assigned single-outlet users resolved Riverside without a repeated picker, and the cashier could neither see outlet navigation nor open outlet management.
- Inventory and transfers: the shared catalogue retained one product. A draft transfer left source stock at 20; dispatch moved it to 16 with one outbound movement; receipt created destination stock of 4 with one inbound movement; the authorised consolidated total remained 20. Create, dispatch, and receipt audit records were present. Invalid quantities were constrained, same-outlet selection and insufficient stock were rejected visibly, and cross-tenant access remained tenant scoped.
- Invoice and payment regression: the historical invoice rendered with subtotal 200.00, discount 10.00, tax 34.20, adjustment 0.05, total 224.25, paid 100.00, and balance 124.25. Recording the final 124.25 payment changed the invoice to Paid and balance to 0.00 without altering Invoice Designs or SaaS billing. The invoice create form has no outlet field and CRM invoices remain company scoped.
- Free 365 and setup: `/start-free` rendered with email OTP enabled through the local mail boundary. A synthetic contact was verified, exactly one tenant/default outlet was provisioned, first login reached Store Setup, all six steps saved, the review plan rendered, and Prepare My Store reached the completion screen. Add Another Outlet remained optional and the one-outlet tenant was not forced through an outlet picker.
- Navigation, responsive layout, and accessibility: permitted administrator navigation and global search exposed Outlets and Stock Transfers; cashier navigation exposed neither. No Outlet Reports entry appeared because no supported outlet reporting screen exists. The outlet page was checked at 360, 390, 430, 768, 1024, and 1280 pixels. A mobile overflow defect was corrected with bounded flex content, full-width mobile action, wrapping, and minimum touch sizes. The mobile drawer opened and closed, the search dialog moved focus into the search field and returned it to the trigger, form errors used `role="alert"` and field invalid state, statuses retained text labels, final screenshots were captured, and a fresh-tab console check returned no warnings or errors.

### Release blockers found

The Phase G production recommendation from this verification is **NOT APPROVED** for the requested complete multi-outlet scope:

1. Stock adjustments expose all company warehouses rather than restricting the form to the current authorised outlet. POS sale and return stock attribution were not certified in this walkthrough. Archived-outlet inventory history has no dedicated verified screen.
2. CRM invoices, invoice numbers/prefixes, payments, and their authorization remain company scoped. The product cannot yet create or reject an invoice specifically for an authorised/unassigned outlet.
3. `/modules/reports` is a generic module summary, not an outlet report. First/second outlet filters, an authorised All Outlets option, consolidated financial totals, manager/cashier report restrictions, cross-tenant report parameter rejection, and archived history reporting are not implemented.
4. Transfer dispatch and receipt retries are intentionally idempotent and hide completed actions; they are not explicit rejection responses. Over-receipt, partial receipt, approval, edit-after-completion, and cancellation controls are not implemented.
5. There is no repository Playwright/Dusk browser-test convention. No competing browser framework was introduced; the actual walkthrough used the supported in-app browser, and a focused feature regression covers outlet form rendering.

Before production approval, implement and test the missing outlet authorization/data boundaries for inventory adjustments, sales/returns, CRM invoices/payments, and reports; define whether retry semantics must reject or remain idempotent; then repeat the full browser matrix and historical/full-suite checks. Do not enable `MULTI_OUTLET_REPORTING` in production while the reporting surface remains absent.

For rollback, disable the Phase G navigation flags first and retain all outlet, assignment, transfer, stock movement, invoice, and payment rows. The additive data migration is forward-only: do not drop `branch_user_assignments`, outlet metadata, or a generated main outlet until an operator has reconciled user defaults and historical references. Reversing a completed transfer requires compensating audited stock movements, never row deletion.

The final serial verification passed the historical harness (2 tenants, 4 users, 3 outlets, 8 invoices, 4 payments, preserved financial/stock/ledger values, idempotent retry, and SQLite integrity `ok`), 388 application tests with 2,967 assertions and no failures, errors, or skips, all 765 registered routes, the Vite production build, cache clear, Blade view cache, route cache, configuration cache, and `git diff --check`.

## Flags and operations

- `MULTI_OUTLET_ENABLED=true`
- `MULTI_OUTLET_SETUP_ENABLED=true`
- `MULTI_OUTLET_TRANSFERS_ENABLED=true`
- `MULTI_OUTLET_REPORTING_ENABLED=true`

The flags are documented in `.env.example`. The main module navigation uses the main flag. Saved records are retained when navigation is disabled. Before production, run migrations forward, clear/rebuild caches, verify every tenant has one primary branch, and review plan branch limits. Large tenants should ensure indexes are monitored for stock-transfer and branch-report workloads.

## Audit and limitations

Outlet create/update/archive/default/activation/assignment and transfer create/dispatch/receipt actions are recorded in audit logs using tenant-safe IDs. No GSTIN, full address, customer rows, or product rows are included in audit payloads.

This phase deliberately does not introduce inter-company transfers, warehouse bin-to-bin transfers, partial transfer discrepancy settlement, outlet-level CRM invoice numbering, a new invoice template engine, or a new reporting engine.
