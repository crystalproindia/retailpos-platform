# Manual Renewals

Platform administrators issue a subscription invoice, record a validated manual payment, and receive a receipt. Payment allocation uses row locks and idempotency. Once the invoice balance reaches zero, the existing subscription lifecycle service renews/reactivates the subscription and clears entitlement state.
