# Suspension

After grace expiry, the scheduled expiration process suspends the subscription. With rollout enforcement enabled, suspended users are blocked from protected tenant operations but can still reach subscription pages and log out. Platform administrators always retain legitimate platform access.

Emergency recovery: leave the enforcement flag disabled, renew/reactivate through SaaS administration, verify the tenant snapshot, then clear application caches.
