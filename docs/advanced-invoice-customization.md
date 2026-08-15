# Advanced Invoice Customization

## Scope

This release adds tenant-scoped prefixes for sales invoices, quotations, and
proformas; server-authoritative GST and No-GST document modes; private image
signature storage; authorized-signatory snapshots; and a 44-design invoice
registry across A4, A5, Thermal 80mm, and Thermal 58mm output.

The 44 designs are intentionally recomposed through corporate, retail,
minimal, professional, creative, industry, A5, and thermal families. Shared
rendering primitives retain GST and totals consistency, while each variant has
its own masthead, metadata treatment, table rhythm, totals emphasis, and
footer composition. Thermal 58mm omits the image-signature block to preserve
readability on the narrow roll; this limitation is exposed in registry
metadata.

It preserves existing document numbers, GST records, payments, purchasing,
stock, and Phase S behaviour. A prefix change applies only to the next number
issued for that tenant and document type. It does not rewrite history.

## Safety Boundaries

- No-GST eligibility and tax totals are determined on the server.
- Signature uploads are private tenant-scoped PNG, JPEG, or WEBP images. They
  are visual signature images, not cryptographic digital signatures.
- The advanced migration is additive, uses short explicit MySQL identifiers,
  and tolerates its own partially applied foreign keys and unique index.
- Production rollback should use a forward remediation after data is created;
  the migration `down` path deliberately preserves document-setting snapshots.
- Google Calendar and Google Meet are not required or activated by this
  release.

## Deployment Note

Build frontend assets from the exact application commit being deployed, then
synchronize `retailpos-platform/public/build/` to both live locations:

```text
/home/u237933956/domains/retailpos.biz/retailpos-platform/public/build/
/home/u237933956/domains/app.retailpos.biz/public_html/build/
```

Run the forward migration before serving this release, rebuild Laravel caches,
restart the queue worker, and perform the invoice, quotation, proforma,
document-numbering, and invoice-design smoke checks described in the release
runbook. Do not roll back populated production data merely to undo this phase.
