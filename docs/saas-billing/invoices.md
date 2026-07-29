# Subscription Invoices

Subscription invoices are immutable snapshots of the tenant, subscription, plan, pricing, entitlement, tax, billing period, and line values at issue time. The recurring-period unique key prevents duplicate scheduled invoices. Draft invoices may be issued or voided; issued values are never edited.

Manual and gateway payments allocate to one subscription invoice in V1. Full payment renews through `SubscriptionService`; partial payment leaves the existing subscription period unchanged.
