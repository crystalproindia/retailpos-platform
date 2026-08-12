# Invoice Template Architecture

CRM invoice presentation is tenant-scoped through the invoice_template_settings table. InvoiceTemplateService supplies one render model from immutable CRM invoice records; it does not create, update, or recalculate financial data.

`InvoiceTemplateRegistry` is the single catalogue for every selectable design. Each entry declares its stable key, paper format, style, GST detail profile, supported orientation, preview/print view, and layout variant. Existing tenant keys remain valid and resolve to A4 defaults, while new selections persist `paper_format` and `gst_presentation` alongside the existing template, colour, copy-label, orientation, QR, and section options.

The renderer uses separate A4 corporate, retail, and minimal layouts, an A5-specific compact layout, and a receipt-first thermal layout. The thermal layouts are composed for browser printing at 80mm and 58mm rather than shrinking an A4 page. Native USB or ESC/POS driver control is intentionally out of scope; browser-print-compatible receipts are the current supported path.

InvoicePdfService asks the registry for the selected view and applies a paper-specific Dompdf page: A4, A5, 80mm thermal, or 58mm thermal. Sales PDF download, browser print, public invoice PDF, and email attachments all use that service. Receipt and SaaS subscription documents deliberately retain their existing renderers.

InvoiceBalancePresentationService provides display-only current and prior balance values. InvoicePaymentQrService creates an inline local PNG from a validated UPI or HTTPS payment source when an invoice remains payable.

Settings changes are recorded through the existing AuditLogger without storing QR payloads, credentials, or rendered document content.
