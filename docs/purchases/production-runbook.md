# Purchase Finance Production Runbook

Deploy forward-only migrations before serving invoice/payment routes. Clear cached configuration, routes, and views after deployment. Do not edit invoice or allocation records directly in the database.

For a failed payment, use the protected reversal action with a reason; do not delete the payment. For an invoice variance, keep the source GRN and supplier invoice references available for review. Monitor audit logs for `purchase.invoice.*` and `supplier.payment.*` events.
