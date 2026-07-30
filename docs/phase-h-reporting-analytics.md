# Phase H Reporting and Analytics

The Reports hub at /reports is tenant scoped. It uses RetailReportingService and
OutletAccessService, so filters, totals, and CSV exports share one outlet
authorization boundary. A company administrator may choose All Outlets; other users
are limited to their current or explicitly assigned outlet.

Supported current reports cover completed POS sales, CRM invoice receivables, approved
purchase invoices, cleared or recorded CRM invoice payments, approved purchase returns,
current stock valuation, and invoice GST totals. Date ranges use the company timezone
and are limited to 366 days. CSV exports use the same report result and protect
spreadsheet formula-like values.

Current stock valuation is quantity on hand multiplied by each product current cost
price. It is not historical FIFO or weighted-average valuation. Gross profit, sales
returns and refunds, and historical stock valuation remain explicitly unavailable
until reliable source ledgers and invoice-level cost snapshots exist. GST output is a
preparation aid and must be reviewed before filing.

For production, build assets locally and synchronize public/build/ to
/home/u237933956/domains/app.retailpos.biz/public_html/build/. The live domain does
not serve Vite assets from the repository public directory. Do not enable Google
Calendar or Google Meet.
