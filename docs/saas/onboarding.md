# Tenant Onboarding

Platform administrators create a tenant through idempotent onboarding. The workflow creates the company, primary branch, administrator, and subscription atomically. Existing tenants are not converted by onboarding; use the backfill command instead.
# Quick provisioning additions

Platform administrators now use the same transaction-backed `TenantProvisioningService` that future public signup will call. The service creates the tenant, primary outlet, owner, subscription, verification state, and audit event together. See [Tenant Provisioning](../tenant-provisioning.md).
