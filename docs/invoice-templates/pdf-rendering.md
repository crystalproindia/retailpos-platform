# PDF Rendering

CRM invoice PDFs use the existing Dompdf integration. Item rows are exposed in 50-row chunks so long documents remain readable and each chunk has its own table header. Layout CSS keeps rows and totals sections together where practical.

The payment QR is generated locally with endroid/qr-code 6.0.9 as an inline PNG data URI. No QR API, CDN, or uploaded image is used. It is absent for invalid or missing sources, zero balances, paid invoices, cancelled invoices, and void invoices.

Sales render paths are the browser print, PDF download, and secure public PDF routes. The existing email delivery foundation sends secure invoice links; it does not currently create PDF attachments. SaaS subscription billing uses its own PDF renderer and is intentionally outside this feature.
