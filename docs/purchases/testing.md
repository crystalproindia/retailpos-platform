# Purchase Finance Testing

The purchase feature suite covers the existing supplier, PO, GRN, stock posting, return, and settings flows plus GRN-to-invoice matching, server-side GST totals, excess quantity prevention, invoice approval, allocation, and payment reversal.

Run `php artisan test tests/Feature/PurchaseFoundationTest.php` before the complete test suite. Run a production-like migration on an existing database copy as part of release validation; `migrate:fresh` alone is not sufficient.
