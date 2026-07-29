# SaaS Testing

Run `php artisan test --filter=Saas` for lifecycle, plan snapshot, platform authorization, white-label, reseller, usage snapshot, and onboarding coverage. Run the full suite before release.

Production-style checks include route listing, permission dry-run, subscription backfill dry-run, usage recalculation dry-run, lifecycle dry-runs, view/config/route cache creation, and `git diff --check`.
