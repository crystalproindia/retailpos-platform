# Security

Billing amounts and currency are sourced from the invoice server-side. Browser callbacks require server signature verification; webhooks require raw-body HMAC verification. Gateway secrets are encrypted, masked in UI, and restricted to platform administrators. Invoices, payments, refunds, and webhook history have no billing-service hard delete path.
