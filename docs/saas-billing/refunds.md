# Refunds

Refunds are append-only records. An administrator requests a refund against a confirmed payment, then approves it. Manual refunds are recorded directly; provider refunds use the provider-neutral adapter. A refund never silently deletes payment history or renews access.
