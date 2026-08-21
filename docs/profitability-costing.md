# Profitability Costing

RetailPOS profitability is tax-exclusive and uses immutable sale-time snapshots. It is a gross-profit management report, not a company net-profit or accounting ledger.

## Sources and coverage

- Completed POS sale items capture the current product standard cost at checkout.
- Product-linked CRM invoice items capture the current product standard cost while the invoice is a draft. The existing invoice lifecycle makes issued documents immutable.
- Free-text CRM invoice items remain valid sales lines, but have `unavailable` cost status. Their revenue is shown separately from known-cost profitability; missing cost is never treated as zero.
- CRM cancellations are excluded. CRM partial returns and credit-note COGS reversals are not supported until a line-level CRM return ledger exists.
- POS returns reverse the original sale item's immutable product, category, brand, outlet, salesperson, revenue, discount, and cost attribution.

The POS and CRM document systems have no authoritative conversion/reference link. They are reported as separate source types and are not merged or inferred from matching text.

## Historical POS backfill

Run a non-writing assessment first:

```bash
php artisan reports:backfill-pos-profitability --dry-run
```

The command accepts `--company=ID`, `--after-id=ID`, and `--chunk=200` to scope or resume a bounded run. A historical POS line is reconstructed only when exactly one sale stock-movement record matches the same company, sale, product, and quantity. It writes `reconstructed` provenance with the movement's recorded unit cost. Ambiguous or missing evidence is marked `unavailable` and remains excluded from COGS and margin.

The command is idempotent: rows with an existing cost status are not reconsidered, and the update itself is guarded by `cost_snapshot_status IS NULL`.
