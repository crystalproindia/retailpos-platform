# Tenant Provisioning

`TenantProvisioningService` is the single atomic account-creation workflow for platform administrators today and public signup later. The platform route is `GET /saas/tenants/create` and `POST /saas/tenants` behind platform-admin and `saas.tenants.create` gates.

## Transaction

One database transaction creates the company tenant, primary outlet, tenant administrator, selected active plan subscription, onboarding record, verification-pending state, and audit record. An idempotency UUID returns the completed onboarding record on a safe retry. Duplicate owner mobile/email values are rejected before a second tenant is made.

Company name is optional and becomes `Your Store Name`. It is a non-blocking onboarding reminder only; an authorised tenant administrator can update it at Settings > Company Profile, together with industry, legal name, address, GSTIN, contact mobile, and contact email. This never changes company identity or tenant keys.

## Verification

Provisioned users begin `pending`. Verification records hold only a hashed six-digit code, expiry, attempt count, resend cooldown, and one-time consumption time. A successful email or mobile verification satisfies the requirement; both are never required. Plaintext passwords and OTPs are deliberately absent from onboarding payloads, audit metadata, and delivery records.

Email verification uses the configured email foundation. Mobile verification remains intentionally provider-neutral: no SMS provider or plaintext OTP logging is introduced by this release.

## Industry registry

`saas_industries` holds stable industry keys, labels, icon identifiers, order, enabled state, and optional short descriptions. It stores no SVG or executable content. Disabled industries cannot be selected. The registry is reused by provisioning and Company Profile and is ready for public onboarding personalisation.

## Production checklist

1. Deploy the application code and run `php artisan migrate --force`.
2. Run `php artisan config:clear`, `php artisan route:clear`, and `php artisan view:clear` from the Laravel project directory.
3. Confirm `php artisan route:list --path=saas` includes `saas.tenants.create` and `saas.tenants.store`.
4. Confirm outbound email policy before using the email verification option.
5. Keep `SAAS_ENTITLEMENT_ENFORCEMENT=false` until package snapshots and support procedures are approved, then enable it deliberately.
