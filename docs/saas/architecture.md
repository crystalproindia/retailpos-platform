# SaaS Architecture

RetailPOS uses `Company` as the tenant boundary. Plans are versioned, subscriptions keep immutable feature and limit snapshots, and entitlement checks read the current snapshot plus time-bounded tenant overrides. Platform administration requires both the administrator role and `is_platform_admin`; it is not a tenant role.

Core tables are `saas_plans`, `saas_plan_versions`, `saas_plan_features`, `saas_plan_limits`, `saas_subscriptions`, `saas_subscription_events`, `saas_tenant_overrides`, `saas_usage_snapshots`, `saas_tenant_onboardings`, `saas_resellers`, and `saas_reseller_tenant_assignments`.
