# Plans and Entitlements

Features and limits are configured on a plan, then copied into every subscription snapshot. A `null` limit means unlimited. Tenant overrides can temporarily replace a feature or limit and always carry a reason, effective dates, creator, and audit record.

Feature enforcement is controlled by `SAAS_ENTITLEMENT_ENFORCEMENT=false` by default. Enable it only after grandfathering and production review. When enabled, direct module URLs are checked server-side; menu visibility is not relied upon for security.
# Free 365 additions

The Free 365 plan uses stable feature entitlement keys. `EntitlementService` canonicalizes legacy feature keys and is the shared source for middleware, sidebar visibility, Global Menu Search, controllers, jobs, and usage checks when `SAAS_ENTITLEMENT_ENFORCEMENT` is enabled. Permissions and entitlements are both required; one never substitutes for the other. See [Free 365](../saas-free365.md) and [Tenant Provisioning](../tenant-provisioning.md).
