# GST and Indian Compliance Foundation

## Scope and safety boundary

This foundation adds tenant-scoped GST settings, structural GSTIN validation, explicit place-of-supply treatment, immutable GST document fields, tax-note records, review periods, and export history. It supports accountant review; it does not verify GST registrations, file returns, submit e-invoices, submit e-way bills, or provide legal advice.

## GST settings and tax treatment

Each company has one `gst_settings` profile containing legal identity, registration type, GSTIN, registered address, state, invoice series, financial year, applicability flags, and accountant-review metadata. A GSTIN is only checked for format. GST calculation requires a supplier state and place-of-supply state; it returns CGST/SGST for intra-state transactions or IGST for inter-state transactions. Missing state data is a validation error rather than a legal assumption.

## Document integrity

CRM invoices retain their existing records and have additive GST snapshot/readiness fields. POS sales remain separate from CRM invoices. Existing completed documents are never recalculated or rewritten. Credit and debit note data is represented by `gst_document_notes` and item snapshots, linked to the source document without modifying it.

## Reports, exports, and filing

`gst_return_periods` supports open, review, accountant-approval, and lock workflows. `gst_export_logs` records who generated a review export and its validation summary. Every report or export must be labelled **Review before filing**. Users must complete reconciliation and accountant approval before handing data to the official portal.

## Provider boundary

Future e-invoice and e-way bill adapters must be provider-neutral, idempotent, encrypted at rest for credentials, and permission-controlled. This release has no provider credentials, no raw provider responses, no fake IRN, no fake QR data, and no live government submission.

## Deployment and rollback

Apply `2026_07_20_020000_create_gst_compliance_foundation` before using GST settings. The migration is additive. Roll back only before operational GST settings, notes, periods, or export history exist; thereafter preserve auditability and correct records through authorised compliance workflows.

## Limitations

Future phases cover accountant-reviewed report screens/exports, credit/debit-note issue workflows, e-invoice/e-way bill payload adapters, official portal handoff, and formal GST return filing. This foundation does not claim GST filing, e-invoice submission, e-way bill submission, legal compliance certification, or accountant approval.
