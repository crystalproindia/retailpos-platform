# SaaS Production Runbook

Deploy migrations before enabling enforcement. Run `php artisan saas:backfill-subscriptions --dry-run`, review output, then run without `--dry-run`. Confirm each existing company has one active grandfathered subscription before setting `SAAS_ENTITLEMENT_ENFORCEMENT=true`.

Register one scheduler runner and a queue worker. Scheduled commands: `saas:process-trials` 08:00, `saas:process-renewals` 08:10, `saas:process-expirations` 08:20, and `saas:recalculate-usage` 08:30. Each uses overlap protection; lifecycle commands use idempotency keys.

Rollback is code-first: disable enforcement, keep migrations and records intact, correct the plan/subscription state, clear caches, and rerun dry-run commands. Do not delete tenant data to resolve a downgrade conflict.
