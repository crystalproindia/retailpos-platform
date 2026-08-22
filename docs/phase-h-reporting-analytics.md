# Phase H Reporting and Analytics

## Ownership and authorization

`/reports` is the tenant-scoped Owner Command Center. `ExecutiveReportingService`
composes a lean executive read model from `RetailReportingService`, which owns the
query path for summaries, detail screens, and CSV exports. It delegates outlet
scope to `OutletAccessService`: Administrators may select **All Outlets**;
managers are limited to their active assignment/current outlet; Staff users do not
  have the reporting capability by default. Every selected outlet, warehouse, product,
category, customer, supplier, and cashier ID is resolved server-side against the
acting company before a query runs.

Report rows are capped at 500. Warehouse choices are limited to the selected,
authorised outlet scope. Historic rows attached to archived outlets remain visible
to an Administrator using All Outlets, while operational users remain limited to
their active accessible outlets. Legacy CRM payments with no `branch_id` inherit
their scope and displayed outlet from their linked CRM invoice.

## Supported reports

- **Sales**: POS sales, only `completed` by default, with gross sales, discounts,
  tax, net sales, paid amount, invoice count, and invoice-level rows. An explicit
  status filter is operational and may include a non-financial status such as
  `voided`; the UI labels that selection accordingly.
- **Purchases**: authorised purchase invoices, tax, paid and outstanding amounts,
  gross purchase value, approved-return deductions, and purchase rows.
- **Inventory**: current stock-on-hand, low-stock count, and current-cost stock
  valuation. It is intentionally not a historical FIFO or weighted-average report.
- **Stock movements**: authorised stock-ledger rows, inbound/outbound quantities,
  movement count, product, outlet, warehouse, direction, and reference context.
  Transfers are visible at both endpoints and do not change consolidated stock.
- **GST and tax**: CRM invoice taxable value plus CGST, SGST, IGST, cess,
  place-of-supply completeness, and detail rows. This is a preparation aid, not a
  filing submission.
- **Payments and outstanding**: recorded/cleared CRM invoice payments, payment
  rows, outstanding receivables, and Current/1-30/31-60/61-90/91+ aging.
- **Purchase returns**: approved return item quantity and minor-unit value only.
- **Outlet and cashier performance**: completed POS sales count, net sales,
  discounts, and average order value for the authorised scope.
- **Profitability**: combined POS and product-linked CRM revenue using immutable
  sale-time cost snapshots. Free-text and unreliable historical items remain
  revenue-only and lower the explicitly displayed cost-coverage percentage.

## Owner Command Center

The command center presents eight executive KPIs, previous-period comparisons,
revenue versus gross-profit and sales-versus-purchase charts, GST position,
receivable/payable rankings, product intelligence, outlet comparison, salesperson
attribution, and deterministic management signals. It does not use an AI model.
The chart granularity is daily up to 31 days, weekly up to 120 days, and monthly
beyond that. Database work is aggregated to one point per company-local activity
day; ranked lists are capped before rendering.

Gross profit, product margin, and sale-time cost are visible only with
`reports.profitability.view`. Users without that permission receive operational
sales information and explicit unavailable profitability states. Current stock
value has no previous-period change because the stock balance is a current snapshot,
not a historical valuation ledger.

The report screen uses mobile summary cards below the desktop breakpoint and a
bounded horizontal table above it. Empty detail sources show an explicit
no-records state. The supporting feature tests cover the rendered report screen;
the release check also performs a real browser walkthrough at desktop, tablet, and
phone widths.

## Filters and export parity

All reports accept date range, outlet, and warehouse where supported. The detail
screen exposes report-specific filters for product, category, customer, supplier,
cashier, payment method, transaction status, sale channel, discount usage, stock
status, movement type, and tax classification where the underlying source supports
them. The service applies a supported filter before calculating both the summary
and detail rows, so it cannot create an export-only or screen-only result.

CSV generation receives the same report result as the screen. It includes generated
timestamp, company timezone, outlet scope, warehouse scope, and date range before
the row header. Monetary values are converted from integer minor units only for
display/export; counts and quantities are never converted to currency. Values that
start with a spreadsheet formula marker are quoted defensively.

## Financial and data boundaries

All monetary values are calculated in integer minor units. The reporting converter
preserves negative values, including negative stock valuation, instead of turning
them into positive values through decimal parsing. Decimal inventory quantities are
kept at thousandth precision before a current-cost valuation is rounded to a minor
unit.

Cancelled/voided invoices and sales, cancelled/draft purchases, unapproved purchase
returns, and failed/reversed CRM payments are excluded by default. A selected status
changes the source status deliberately; it does not silently alter data outside that
selected source.

## Honest limitations

- Gross profit and margin are unavailable for free-text CRM lines and historical
  items without a reliable snapshot. Current product cost is never used to invent
  historical profit.
- POS returns use the Phase Q line ledger and original cost snapshots. Partial CRM
  return/credit-note COGS reversal remains unavailable until a line-level CRM return
  ledger exists.
- Inventory value is current standard-cost valuation. Historical FIFO or weighted-
  average stock valuation and period-over-period inventory movement are not claimed.
- Slow-moving products currently mean the lowest positive sold quantity in the
  selected period. Dead-stock analysis requires a separately approved inactivity
  policy and historical inventory-age model.
- Product, category, customer, supplier, and cashier menus are currently bounded
  to 100 active company records to keep the Blade filter form predictable. A
  server-backed typeahead is the next extension for larger catalogues; the report
  query and authorization boundary are already independent of that presentation.

## Production asset boundary

After `npm run build`, synchronise
`retailpos-platform/public/build/` to
`/home/u237933956/domains/app.retailpos.biz/public_html/build/`.
The live application does not serve Vite assets from the repository public folder.
Do not enable Google Calendar or Google Meet as part of a reporting deployment.

## Workforce compatibility

The Phase I workforce profile reuses the cashier data returned by `RetailReportingService` for completed POS sales and its current authorized outlet scope. The report now includes the source `cashier_id` alongside the existing display name and minor-unit values, allowing a linked employee profile to identify its own row without duplicating a sales calculation. Workforce screens must continue to label this as operational context rather than an overall employee-performance score.
