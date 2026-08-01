# Phase I: Workforce, Access, and Performance

## Scope

Phase I adds a tenant-scoped workforce foundation without replacing Laravel authentication, the existing `UserRole` enum, outlet authorization, audit logging, or Phase H reporting. Google Calendar and Google Meet remain disabled and are not used by this module.

## Employee and user separation

`WorkforceEmployee` is a workplace profile and may exist without a login. A `User` is an authentication account and may link to at most one employee through nullable `users.workforce_employee_id`. This preserves existing accounts: the migration does not infer employee profiles from historical users, transactions, email addresses, or names. Administrators can link an employee later through the workforce UI.

Employee profiles contain only operational contact, job, assignment, and manager-only notes. The module deliberately excludes payroll, salary, leave, attendance, government identity, banking, biometric, health, religion, caste, and political information.

## Access and roles

The current system roles (`administrator`, `manager`, `sales`, `staff`) remain the authentication and route-role boundary. Tenant custom roles live in `workforce_roles` and have a compatible non-administrator base role plus explicit `workforce_role_permissions`. `AppServiceProvider` evaluates those explicit capabilities through the existing Gate configuration; a role name never grants access.

Company Administrators manage employees, accounts, custom roles, and assignments. Managers can view the employees in their authorized outlets and may submit reviews or recognition only for direct reports. Employees can access only `/workforce/me` when their account is linked to a profile; manager notes and tenant-wide controls are excluded.

The service rejects cross-tenant employees, users, roles, outlets, warehouses, and registers. Archived/inactive resources cannot be newly assigned. The existing `OutletAccessService` remains the source of truth for outlet scope and current-outlet selection.

## Account lifecycle and invitations

User accounts use `pending_invitation`, `active`, `suspended`, and `disabled` states. Login accepts only active accounts. `EnsureWorkforceAccountIsActive` also checks each authenticated request, logs out an account changed to suspended or disabled, invalidates its session, and regenerates the CSRF token.

An invitation creates a pending account with no usable password. The raw activation token is sent only through the existing email-delivery service; the database stores a SHA-256 hash, the link expires after 72 hours, and it is single-use. Resending revokes earlier pending invitations. Expired, cancelled, altered, or accepted links fail safely. Passwords and raw tokens are never placed in audit properties or email delivery payloads.

The account lifecycle cannot disable or suspend the last active Company Administrator. The protected administrator must first create or activate another administrator.

## Assignments

`workforce_employee_outlet_assignments`, `workforce_employee_warehouse_assignments`, and `workforce_employee_register_assignments` store active employee assignments. A linked user receives corresponding `branch_user_assignments`, so existing POS and reporting outlet checks keep working. Assignment updates do not rewrite historical transaction ownership.

## Operational context, reviews, and recognition

The employee profile reuses the Phase H cashier report for completed POS sales in the last 30 days. Values remain integer-minor-unit safe until presentation and are clearly labelled as operational context, not an employee-quality or disciplinary score. No combined score, hidden ranking, automated penalty, attendance metric, or customer-complaint score is introduced.

Manager reviews preserve a period, cycle, ratings, comments, and draft/submitted state. Recognition is separate from reviews. Both are audited. Missing operational records appear as unavailable rather than zero.

## Task compatibility

Phase J adds task cards to workforce dashboards through the shared authorized
task repository. Only work tasks in the user's permitted outlet scope contribute
to manager workload. Personal tasks remain private to their owner and never
appear in workforce metrics, employee profiles, reviews, recognition, exports,
or management screens. Workforce profiles can create an ordinary task through
the shared `/tasks` experience; this does not create an employee-performance
score or a hidden personnel record.

## Migration and production operations

`2026_07_31_020000_create_workforce_foundation.php` is additive and forward-only for production. It creates new workforce tables and nullable user links; it does not mutate historical sales, invoices, payments, stock, or existing user roles. If a release remediation is required, add a new forward migration. Do not roll back populated workforce tables in production.

After deploying code and running `php artisan migrate --force`, rebuild assets and synchronize:

```text
retailpos-platform/public/build/
→ /home/u237933956/domains/app.retailpos.biz/public_html/build/
```

Then clear and rebuild Laravel caches. Do not deploy a `public/build` directory built from a different application commit.

## Current limitations and future modules

- Role duplication, role deactivation/replacement, and bulk workforce actions remain future controlled enhancements.
- Reviews do not yet support acknowledgement, finalization/reopening, or employee comments.
- Workforce metrics currently cover reliable completed POS sales only; payment collection, returns, discounts, and register variance will be added only after a shared authorized Phase H row provider is available for each source.
- Phase K adds attendance, shifts, leave and scheduling as a separate authorized time-management foundation. It does not alter Phase I employee identities, account links, roles, assignments, reviews, recognition, or performance boundaries. Payroll, compensation and discipline remain out of scope.
