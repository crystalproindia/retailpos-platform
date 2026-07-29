# Public Free 365 Signup

## Public URL

The marketing website should send prospective retailers to `https://app.retailpos.biz/start-free` with the CTA label **Start Free POS**. Suggested supporting copy: “No credit card required. Get GST billing, products, customers and basic inventory free for 365 days.”

## Feature flags

Public registration is disabled by default. Set `SAAS_PUBLIC_SIGNUP_ENABLED=true` only after SMTP, Terms, Privacy, and operational monitoring are ready. Email OTP is controlled by `SAAS_PUBLIC_SIGNUP_EMAIL_OTP_ENABLED`. Mobile OTP remains unavailable until both `SAAS_PUBLIC_SIGNUP_MOBILE_OTP_ENABLED=true` and a real `MobileOtpSender` implementation for `SAAS_MOBILE_OTP_PROVIDER` are configured. A provider name alone never exposes the mobile option.

## Flow

1. The visitor selects an enabled industry from `saas_industries` and chooses an available verification method.
2. A short-lived `saas_public_signup_sessions` record stores a hashed public token, normalized contact, selected industry, OTP hash, expiry, cooldown, safe metadata, and idempotency key.
3. After a one-time OTP verification, the visitor supplies a name, password, optional store name, and Terms/Privacy consent.
4. `PublicFree365SignupService` resolves the `free-365` plan server-side and calls `TenantProvisioningService`. The browser never submits a plan ID.

The provisioning transaction creates the company, primary outlet, administrator, Free 365 subscription, verification-completed state, and audit history. A retry reuses the signup idempotency key and cannot create a second tenant.

## Abuse and privacy controls

The flow applies IP throttles, contact throttles, OTP attempt limits, resend cooldowns, honeypot checks, session expiry, duplicate verified-contact checks, and safe audit fields. OTP values and passwords are never written to audit records, delivery records, or application payloads. CAPTCHA is deliberately an integration boundary for suspicious traffic rather than a requirement for every signup.

Consent records include accepted timestamp, Terms version, Privacy version, and signup source. Configure legal URLs with `SAAS_TERMS_URL` and `SAAS_PRIVACY_URL` before enabling public registration.

## Login and onboarding

Existing email login remains unchanged. New users may sign in with their normalized E.164 mobile number or email plus password. Mobile password reset needs a real mobile provider before it can be offered.

New public Free 365 tenants see a non-blocking, tenant-scoped setup checklist: store name, company/GST details, product, customer, first invoice, and sales review. The GST detail reminder is a readiness guard; this phase does not alter existing invoice financial rules.

When enabled, the authenticated Store Setup Wizard directs a new tenant administrator to six resumable starter-configuration questions before the normal dashboard. It is skippable and uses only server-side deterministic recommendations. See [Store Setup Wizard](store-setup-wizard.md).

## Deployment and rollback

Run the database migration before enabling the feature flag. To stop new signups immediately, set `SAAS_PUBLIC_SIGNUP_ENABLED=false` and clear configuration cache; existing tenants continue to work. Existing pending signup sessions naturally expire and do not provision tenants.
