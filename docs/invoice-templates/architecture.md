# Invoice Template Architecture

CRM invoice presentation is tenant-scoped through the invoice_template_settings table. InvoiceTemplateService supplies one render model from immutable CRM invoice records; it does not create, update, or recalculate financial data.

InvoicePdfService maps the selected template key to one of five top-level Blade files under resources/views/invoice-templates. Sales PDF download, browser print, and public invoice PDF all use that service. Receipt and SaaS subscription documents deliberately retain their existing renderers.

InvoiceBalancePresentationService provides display-only current and prior balance values. InvoicePaymentQrService creates an inline local PNG from a validated UPI or HTTPS payment source when an invoice remains payable.

Settings changes are recorded through the existing AuditLogger without storing QR payloads, credentials, or rendered document content.
