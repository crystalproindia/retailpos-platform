# Invoice Design Settings

Open Sales -> Invoices -> Invoice designs to select A4, A5, Thermal 80mm, or Thermal 58mm first. The screen then shows only compatible designs. Choose a design, set an accent colour, copy label, GST presentation, paper-appropriate orientation, and optional payment QR source.

Thermal formats deliberately hide page orientation because browser thermal printers use the receipt width. A4 supports portrait and design-supported landscape. A5 defaults to portrait. The live preview renders an authorized recent invoice with unsaved design controls so administrators can review the document before saving. Where the current user has no accessible invoice yet, it renders an in-memory sample document; it never creates an invoice, payment, customer, or stock record.

The payment field accepts a UPI ID, UPI payment URI, or approved HTTPS checkout URL. It is not for credentials or private tokens. A QR is embedded only when a trusted balance remains.

The page exposes presentation switches for company content, bill/ship information, bank details, terms, signature, seal, amount words, balances, HSN/SAC, SKU, discounts, and payment status. GST breakup, GST summary, and HSN/SAC remain enforced for GST-ready output.

The Preview latest invoice action is tenant-scoped and opens an inline PDF only for an invoice the current user can already view.
