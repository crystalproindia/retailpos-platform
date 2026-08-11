# Phase R - Advanced Inventory and Warehouse

## Purpose

Phase R extends the existing tenant inventory foundation into an explicit multi-location operating system. It does not create a second inventory ledger. Products, warehouses, bins, stock levels, immutable stock movements, POS sales and returns, purchase receipts and returns, permissions, audits, reporting, and Phase O advisory forecasts remain the source architecture.

The primary workflow is **Move Stock**: select From and To locations, scan or search products, enter quantities, review, and submit. The interface uses the shared Command Center layout and remains usable on desktop, tablet, and mobile.

## Readiness Audit

### Reused

- Tenant-owned products with SKU, barcode, variants, category, unit, costs, prices, tax, lifecycle, and negative-stock rules.
- `warehouses`, `stock_locations`, `stock_levels`, and append-only `stock_movements`.
- Branch/outlet access plus workforce warehouse assignments.
- Existing transfer, adjustment, barcode-template, reorder, purchase, POS, return, reporting, audit, and domain-event foundations.
- Phase O forecasting remains advisory and does not mutate inventory.

### Extended

- Products now opt into pack size, batch, serial, and expiry tracking.
- Stock levels distinguish damaged quantities from saleable availability.
- Registers select a selling warehouse and optional bin. Existing registers are backfilled to an existing branch warehouse or a deterministic active branch warehouse created by the migration.
- Existing transfers now carry explicit source/destination bins, operational quantities, approval/packing metadata, expected arrival, discrepancy data, and an idempotency key.
- Existing reorder rules are location/bin specific.
- Existing barcode batches can reference an inventory batch for batch/expiry labels.

### Added

- Transfer receipts, receipt items, discrepancies, and serial reservations.
- Physical and cycle counts with snapshots and approval/posting lifecycle.
- Batch and serial traceability.
- Global stock lookup, product inventory detail, inventory reports, and browser-rendered barcodes.

### Risks Addressed

- No company-wide balance is treated as an operational location; company totals are aggregations.
- Every new mutation validates company and authorized warehouse scope on the server.
- POS never searches another warehouse after a register is selected. Legacy registerless sales remain compatible only when one unambiguous stock row exists.
- Dispatch and receipt use transactions, row locks, state checks, and idempotency protection.
- Count snapshots cannot overwrite stock changed after counting began.
- Corrections create new movements; historical movements are not edited.

## Location Model

`Warehouse` is the operational stock container. A warehouse linked to a branch represents a retail/store stock location; an unlinked warehouse represents central or standalone warehouse stock. Optional `StockLocation` records represent bins, aisles, racks, or shelves inside it.

`InventoryLocationAccessService` is the shared authorization boundary. Administrators can use company locations. Other users receive only warehouses linked to accessible outlets plus active workforce warehouse assignments. Product search, lookup, transfers, counts, adjustments, traceability, reports, and register setup use this path.

`StockLevel` remains authoritative for on-hand, reserved, damaged, and available quantities. `StockMovement` remains the authoritative ledger. In-transit quantity is explicitly held on transfer items and represented by dispatch/receipt state transitions in the ledger; it is not available at either endpoint.

## Transfer Lifecycle

Supported routes are store to store, warehouse to store, store to warehouse, and warehouse to warehouse.

Lifecycle:

`draft -> requested/pending_approval -> approved -> packing -> dispatched/in_transit -> partially_received/discrepancy -> received`

Terminal alternatives are `rejected` and `cancelled`. Approval and packing may be simplified by company settings. Reduced approval quantities require an explanatory note. Rejection and cancellation require reasons and release reserved serial numbers where applicable.

### Dispatch Accounting

Dispatch re-locks source balances and validates packed/approved quantities. In one transaction it:

1. Reduces source on-hand and saleable availability.
2. Records `transfer_dispatch`, moving the quantity from `available` to `in_transit`.
3. Updates item dispatched and in-transit quantities.
4. Moves selected serials to `in_transit` and reduces the source batch allocation.
5. Records an audit event.

Destination stock does not increase at dispatch. Repeating dispatch after the transition is an idempotent no-op.

### Receipt Accounting

Each receiving event has a unique receipt/idempotency key and can record usable, damaged, and short quantities. Usable stock becomes destination on-hand and available. Damaged stock becomes destination on-hand and damaged, so it is excluded from saleable availability. Unreceived quantity remains in transit. A later receipt can complete it.

Batch identity is copied to a matching destination batch. Selected serials move to the destination only when received. A transfer becomes `received` only when no in-transit quantity or open discrepancy remains.

### Discrepancies

Supported types are short received, damaged in transit, wrong item, excess received, missing package, and other. Reporting a manual discrepancy never changes stock. Resolutions are manager-authorized and audited: confirm loss, return to source stock, add destination damaged stock where valid, or acknowledge a separate manager adjustment. Open discrepancies remain visible on the transfer and reports.

## Controlled Adjustments

Adjustment types include opening correction, damage, wastage, expiry, theft/loss, found stock, system correction, and other. Draft creation records the current and proposed quantity. Approval re-locks the balance, enforces the product negative-stock rule, updates the level, creates a ledger movement, and records audit/domain events. The existing permission boundary controls creation and approval; no direct balance edit is exposed.

## Physical and Cycle Counts

Counts support full location, warehouse, category, selected-product, and cycle-count use. They can be assigned to an employee with a due date.

Lifecycle:

`draft -> counting -> submitted -> review -> approved -> posted`

Each item stores system quantity, counted quantity, variance, and unit cost snapshot. Saving or approval does not mutate stock. Posting locks the live level and rejects stale snapshots. Accepted variances create `physical_count` ledger movements and only then update stock.

## Batch, Serial, and Expiry

Batches store product, warehouse/bin, batch number, manufacture/expiry dates, allocated quantities, cost, supplier reference, and receipt reference. Batch allocations cannot exceed physical stock at that location.

Serials are unique per company and product and have one current state/location. Supported states include available, reserved, sold, returned, damaged, and in transit. Transfer reservations prevent the same serial being moved twice and are released on rejection/cancellation.

Expiry reporting provides expired and 7/30/60/90-day advisory windows. It never disposes of or adjusts stock automatically.

## Reorder and Ageing

Reorder rules are unique per company, warehouse, optional bin, and product. Minimum, reorder, safety, preferred reorder, and optional maximum quantities remain advisory. Phase O may explain demand or recommend quantities, but it cannot create a purchase order, transfer stock, change rules, or approve inventory actions.

Ageing reports use last stock movement and configured buckets: 0-30, 31-60, 61-90, 91-180, 181-365, and 365+ days. Fast, normal, slow, and dead-stock-candidate classifications are informational.

## Barcode Labels

The existing label-template and print-batch workflow now uses `picqer/php-barcode-generator` to render scanner-readable SVG barcodes. Labels can show name, SKU, barcode text, price, batch, and expiry. Print batches render one label per requested copy and respect configured dimensions and columns. Output uses browser printing; no native printer SDK is included.

## Stock Views and Reports

Global stock lookup searches name, SKU, or barcode and shows authorized per-location on-hand, available, damaged, and company aggregate values. Product detail brings together location balances, batches/expiry, serials, recent transfers, adjustments, movements, reorder rules, and last purchase/sale.

The shared inventory report service supplies UI and CSV rows for stock by location, movement, valuation, transfers, in transit, discrepancies, adjustments, count variance, batches, expiry, serials, ageing, slow/dead stock, reorder, and low stock. Queries are tenant/location authorized and bounded to 500 rows. CSV output shares filters and values with the UI and neutralizes spreadsheet formulas. Monetary calculations retain integer minor-unit fields.

## Dashboard

The inventory dashboard uses authorized locations and surfaces stock value, low stock, out of stock, in transit, approval queue, arrivals, expiry, dead stock, and count discrepancies. Location filters never broaden access.

## Permissions

Phase R registers granular capabilities for stock visibility/all locations, transfer creation and each lifecycle action, adjustment creation/approval, count lifecycle actions, batch/serial access, labels, settings, and report export. Existing inventory permission aliases remain supported for backward compatibility. Route middleware is supplemented by server-side company/location checks in services.

## Audit and Concurrency

Transfer lifecycle changes, receipts, discrepancies/resolutions, adjustments, count lifecycle changes, batch changes, serial state changes, reorder changes, and inventory settings changes use the existing audit logger. Payloads contain safe identifiers and before/after operational values, not secrets or customer data.

Critical mutations run in database transactions. Stock levels, transfers, count snapshots, serials, and receipt state are locked before mutation. Server values define available quantities and totals. Repeated dispatch/receipt calls cannot duplicate movements. CSRF and POST/PUT routes protect all mutations; GET routes are read-only.

## UI and Device Contract

- Search and barcode entry are primary controls.
- Transfer work uses plain `From`, `To`, product, and quantity language.
- Status chips, timeline, summary cards, sticky actions, and touch targets expose next actions.
- Dense desktop tables switch to cards or stacked content on small screens.
- Core lookup, transfer, approval, packing, receiving, discrepancies, counts, adjustments, and labels remain operable at 390px, 768px, 1024px, and 1440px.

## Known V1 Limitations

- POS batch allocation/FEFO/FIFO enforcement is not activated. Batch foundations are backward-compatible and checkout remains on the existing product-level stock contract.
- One transfer line tracks one product and optional batch; splitting the same product across multiple batches in one transfer is a future extension.
- Browser print is the only label transport; native printer drivers are deferred.
- Serial selection during a partial receipt is explicit; no automatic serial inference occurs.
- Damaged stock is recorded at the destination location. A dedicated quarantine-location workflow is future work.
- Count schedules store assignee and due date; recurring schedule generation is not included.
- Ageing uses existing movement timing/current cost, not lot-level historical costing.
- Reports are deliberately bounded to 500 rows; larger asynchronous exports are future work.
- No autonomous AI stock action and no automatic purchase-order creation are included.

## Deployment Notes

The migration is additive and preserves existing stock and ledger records. Existing register rows receive an explicit active warehouse; when a branch has none, the migration creates one deterministic branch warehouse before assigning registers. The rollback removes Phase R tables/columns and restores the prior reorder unique key, but deliberately retains any warehouse created to backfill a register so operational location data is never deleted implicitly. Production rollback should prefer forward remediation once operational Phase R data exists.

Deployments must migrate before serving Phase R code and must synchronize the built `public/build/` assets to the live document root. Queue, scheduler, Google Calendar/Meet, payment-provider, and external AI configuration are not required by this phase.
