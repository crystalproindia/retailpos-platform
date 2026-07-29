# Production Runbook

1. Deploy the application and run `php artisan migrate --force`.
2. Clear and rebuild optimized caches after deployment.
3. Configure Razorpay **test** credentials in SaaS Billing Gateway; do not add live credentials.
4. Register the signed webhook URL and test `payment.captured`, `payment.failed`, `order.paid`, and refund events.
5. Ensure a queue worker and Laravel scheduler are running before enabling reminders.
6. Run all billing commands with `--dry-run` before their scheduled production window.

Never use migration rollback to recover billing data. Investigate failed webhook records and use reconciliation instead.
