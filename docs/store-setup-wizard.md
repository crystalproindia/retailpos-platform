# Intelligent Store Setup Wizard

## Entry and permissions

The authenticated route is `GET /getting-started/store-setup` (`onboarding.store-setup.show`). It redirects only newly provisioned tenants with an incomplete, unskipped setup record. Established tenants are never forced through it, but an administrator can open it later from Company Profile or the Free 365 checklist. `store.setup.manage` is administrator-only; sales and cashier users cannot change store configuration.

## Flow and persistence

`store_setup_wizards` has one tenant-scoped record containing the current step, stable industry/subtype keys, structured answers, server-generated recommendations, version markers, idempotency key, safe timestamps, and actors. The six steps cover selling subtype, product volume, GST, scanner preference, printer preference, and product-entry choice. Every saved step is validated server-side and resumes safely; Skip marks the wizard as intentionally deferred without blocking the dashboard.

## Recommendations and apply

`StoreSetupRecommendationService` is deterministic, not generative AI. It uses the existing SaaS industry key, controlled subtype configuration, product volume, tax, scanner, printer, and entitlement data. It produces small starter-category suggestions, tax-rate recommendations, a valid existing Invoice Designs key, barcode guidance, available core modules, and locked upgrade suggestions.

`StoreSetupWizardService` regenerates the plan on the server during apply. It accepts only current-plan category names, creates categories case-insensitively without deleting tenant data, avoids duplicate tax rates, does not overwrite an existing GSTIN or a user-customised invoice design, and creates a barcode label template only when no company template exists. The application transaction is idempotent and records safe audit events.

The review page lets an administrator deselect starter categories and choose whether to apply tax, invoice-template, and barcode defaults. Browser-submitted modules, templates, tax rules, or recommendation payloads are never trusted. Entitlement checks retain core recommendations only when the tenant owns them; upgrade-only suggestions stay locked and cannot be enabled by the wizard.

## Completion and measurement

Completing the plan stores the applied configuration version, completion timestamp, and confirming administrator, then shows the next safe action. The existing Free 365 checklist is extended with a Store setup item and a Resume setup action; it is not replaced. The service records safe first-party audit/funnel events for viewing, starting, saving a step, resuming, skipping, completing, and downloading the product template. GSTIN values and product-row data are excluded from these event payloads.

## Product import boundary

This repository has no existing product-import lifecycle. The wizard therefore provides a formula-safe CSV template with only supported Product fields and directs the user to existing manual product creation. It deliberately does not claim spreadsheet upload, row-preview, confirmation, or queued large-file import support. A future import module must own secure upload retention, CSV/XLSX parsing, preview, duplicate SKU/barcode policy, queueing, and row-level results.

## Tax, printer, and deployment limits

GSTIN receives structural format validation only; no government verification is claimed. A GSTIN is required in the wizard when registration is selected. Existing formal invoice calculations are not modified. Browser/operating-system printer access is intentionally outside the wizard; Invoice Designs and browser print controls remain the supported integration boundary.

The scanner test is a transient form field only and is never saved as product data. The wizard does not create or modify product barcodes. Template recommendations use existing Invoice Designs identifiers: thermal receipts use the receipt design, A4 printing uses the structured GST grid, and digital-only stores use the digital receipt design. A previously customised design is retained.

## Security and operations

All routes require authentication, CSRF protection, `store.setup.manage`, and matching tenant ownership. The apply transaction locks the tenant and setup record, and a completed record is safe to revisit without duplicating applied categories or tax rates. The feature does not accept file uploads, so unsupported spreadsheet types, file sizes, macros, and parsing never enter the application from this flow. There is no external analytics, printer, scanner-driver, or GST-verification dependency.

The review stage is required before apply. The server regenerates the recommendation plan and accepts only its category names; arbitrary module identifiers and invoice-template identifiers submitted by a browser are ignored. Cross-tenant service access is rejected, and premium recommendations remain display-only upgrade suggestions.

## Verification record

Phase C verification used a fresh local SQLite database after the previous development file failed SQLite integrity and schema reads with `database disk image is malformed`. The original file was copied, byte-for-byte, to the ignored `database/backups/` directory before replacement. A fresh `php artisan migrate --seed --force` completed successfully. PHPUnit remains configured for its isolated in-memory SQLite database; generated application caches must be cleared before tests so local session/config caches do not override the PHPUnit environment.

An isolated worktree at `4d5916f` reproduced the two `InvoicePaymentsFoundationTest` failures exactly: `test_pending_payment_does_not_reduce_balance_until_it_is_cleared` raises `Payments can only be recorded against an issued invoice`, and `test_draft_can_be_updated_but_issued_invoice_cannot_be_silently_changed` does not receive its expected validation exception. Neither the tested service nor test changed in Phase C, and no Phase C route or service is on their execution path. They remain a pre-existing invoice-payment defect, not a Store Setup regression.

Local browser verification completed a Free 365 signup, authenticated handoff to the wizard, the six-step journey, invalid/valid GSTIN handling, scanner sample exclusion, thermal template recommendation, CSV-template download, review deselection, application, completion, defer/resume, and responsive widths from 360 to 768 pixels without horizontal overflow. Public signup was enabled only as a process-local verification flag; no deployment setting changed.

- `STORE_SETUP_WIZARD_ENABLED=true`
- `STORE_SETUP_PRODUCT_IMPORT_ENABLED=true`
- `STORE_SETUP_SCANNER_TEST_ENABLED=true`
- `STORE_SETUP_RECOMMENDATIONS_ENABLED=true`

Disable the main flag to stop entry and redirects without deleting saved answers. Additive migrations should be deployed forward, then application caches cleared and rebuilt. No automatic rollback of applied tenant configuration is attempted.

## Production prerequisites

Before deployment, run migrations, clear generated caches, rebuild configuration/routes/views, and ensure a valid persistent database is configured. Enable public signup only after the email OTP delivery configuration and Terms/Privacy URLs have been reviewed. Keep `STORE_SETUP_WIZARD_ENABLED` enabled only when tenant administrators should be offered the flow. The wizard does not replace operating-system printer setup, official GST verification, or a secure spreadsheet-import lifecycle.

After completion, authorised administrators may optionally open Multi-Outlet Setup to add another retail location. This does not add a seventh wizard step, alter saved answers, or force a single-outlet tenant into outlet configuration. See [Multi-Outlet Setup](multi-outlet-setup.md).
