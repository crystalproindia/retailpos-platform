# Plans and Entitlements

Features and limits are configured on a plan, then copied into every subscription snapshot. A `null` limit means unlimited. Tenant overrides can temporarily replace a feature or limit and always carry a reason, effective dates, creator, and audit record.

Feature enforcement is controlled by `SAAS_ENTITLEMENT_ENFORCEMENT=false` by default. Enable it only after grandfathering and production review. When enabled, direct module URLs are checked server-side; menu visibility is not relied upon for security.
