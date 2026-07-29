# Subscription Lifecycle

Supported states are `trialing`, `active`, `grace_period`, `past_due`, `suspended`, `expired`, and `cancelled`. Trialing can activate, enter grace, expire, cancel, or suspend. Active can enter grace, become past due, suspend, or cancel. Grace and past due can reactivate, suspend, expire, or cancel. Suspended, expired, and cancelled subscriptions can be renewed to active.

All changes use `SubscriptionService`, produce an audit entry and lifecycle event, and clear entitlement caches.
