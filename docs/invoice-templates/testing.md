# Invoice Template Testing

InvoiceTemplateDesignTest verifies the A4 design set, required GST settings, and QR visibility. InvoiceTemplateFormatTest verifies the four paper formats, incompatible-format normalisation, all registered views, tenant permissions, and the in-memory sample preview. InvoicePaymentQrTest covers UPI, HTTPS, invalid inputs, paid status, embedded image output, and tenant isolation.

InvoiceTemplateGstPresentationTest covers intra-state 5/12/18/28 rates, interstate 5/12/18/28 rates, cess, zero-rated, exempt, non-GST, reverse-charge, HSN grouping, adjustment display, and summary reconciliation.

InvoiceTemplateOutputPathTest verifies CRM print, download, and public PDF paths plus 50, 100, and 200 line-item renders for the legacy A4 templates. Long-render cases run in separate processes to model individual PDF requests rather than Dompdf's process-local font cache.
