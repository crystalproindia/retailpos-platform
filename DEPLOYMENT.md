# RetailPOS Platform Deployment Guide

## Scope and safety

This guide prepares the Laravel Command Center at `https://app.retailpos.biz` as a staging/demo deployment. The public Next.js website remains a separate application at `https://retailpos.biz`.

Do not begin real customer billing until the smoke tests in this document pass. Do not commit `.env`, credentials, generated application keys, or production backups. Never run `php artisan migrate:fresh` on a live database.

## Before starting

- Confirm SSL is active for `app.retailpos.biz`.
- Back up the database and `storage` before every deployment and before every migration.
- Confirm the target server supports PHP 8.3 or newer with the extensions required by Laravel and the configured database driver.
- Obtain the Hostinger database host, database name, username, and password from hPanel. Keep these only in the server `.env` file.
- Build assets locally when Node is unavailable on the server. `public/build` is a deployment artifact and is intentionally not committed.

## Document root: required security boundary

Only Laravel's `public` directory may be browser-accessible. Never point the subdomain at the project root and never copy the full project into a public web root.

Preferred Hostinger configuration:

```text
Subdomain: app.retailpos.biz
Document root: /home/<hostinger-user>/retailpos-platform/public
```

If hPanel permits changing the subdomain document root, use the preferred configuration. Keep `app`, `bootstrap`, `config`, `database`, `resources`, `routes`, `storage`, `vendor`, `composer.json`, and `.env` outside browser access.

If the document root cannot be changed, place the Laravel project outside `public_html` where Hostinger permits it, then configure the subdomain directory to serve only the contents of the project's `public` directory. Do not use copy-based or rewritten front-controller workarounds that expose the Laravel root. If neither layout is possible, pause and ask Hostinger support to configure the subdomain document root before deploying.

## Production environment

1. Copy the committed `.env.production.example` to a server-only `.env` file. Do not upload or commit the real file.
2. Populate `APP_KEY` only for a brand-new install by running `php artisan key:generate --force` after the `.env` file exists. Do this once; retain the same key on future deploys.
3. Set `APP_URL=https://app.retailpos.biz`, `APP_ENV=production`, and `APP_DEBUG=false`.
4. Set the MySQL/MariaDB `DB_*` values from hPanel. Use `DB_PORT=3306` unless Hostinger supplies another value.
5. Keep `SESSION_DRIVER=database`, `CACHE_STORE=database`, and `QUEUE_CONNECTION=database`; this application already includes their base queue migration. Set `SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax`.
6. Start with `MAIL_MAILER=log`. Configure a real mail provider only in a future, separately reviewed phase.
7. Use `FILESYSTEM_DISK=public` only for intended public media. Private application files remain under non-public storage paths.

## Hostinger database setup

In hPanel, create one MySQL database and its dedicated user, grant that user access only to that database, and record the five values required by the server `.env`:

```text
DB_DATABASE=<Hostinger database name>
DB_USERNAME=<Hostinger database user>
DB_PASSWORD=<strong generated password>
DB_HOST=<Hostinger database host>
DB_PORT=3306
```

Before the first migration, export a backup from hPanel. For each later deployment, export the database again before applying migrations. The production command is:

```bash
php artisan migrate --force
```

Do not use `migrate:fresh`, `db:wipe`, or any destructive reset. For a new staging/demo database only, run `php artisan db:seed --force` after confirming the project's seeders are appropriate for the intended demo data. Do not seed an existing deployment without reviewing the current data and seeder behavior. Create or update the first administrator with an approved one-time operational process, use a strong unique password, verify login, and remove or change demo credentials before any customer-facing use.

## SSH and Git deployment

Use this method when SSH, Git, Composer, and optionally Node are available on the server. Run commands from the Laravel project root, not from the public document root.

```bash
cd /home/<hostinger-user>/retailpos-platform
git pull --ff-only origin main
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

For a new install only, create the server `.env` before the commands above, then run `php artisan key:generate --force` once. For an existing deployment, preserve the existing `.env` and `APP_KEY`.

If Node is not available on Hostinger, run the following locally from the same commit, then upload `public/build` with the application release:

```bash
npm ci
npm run build
```

If Composer is unavailable, prefer enabling SSH/Composer or using Hostinger's supported deployment tooling. A last-resort manual fallback may upload a locally generated `vendor` directory built with the same PHP platform requirements, but it must be refreshed whenever `composer.lock` changes and is less reliable than server-side Composer.

## Manual upload fallback

1. Build `public/build` locally with `npm ci` and `npm run build`.
2. Prepare the complete Laravel release outside `public_html`, excluding local `.env`, `.git`, test artifacts, and local caches. Include `vendor` only when server-side Composer is unavailable.
3. Upload the release to a private directory such as `/home/<hostinger-user>/retailpos-platform`.
4. Create the server `.env` from `.env.production.example` using hPanel's File Manager or SSH. Keep it outside browser-accessible paths.
5. Point `app.retailpos.biz` only at `/home/<hostinger-user>/retailpos-platform/public`.
6. Run the migration, storage-link, and cache commands from the SSH deployment section. If SSH is unavailable, arrange these through Hostinger's supported terminal/command facility; do not skip them silently.
7. Complete the smoke tests below before marking staging ready.

## File permissions and storage

The web server must write to `storage` and `bootstrap/cache`. Prefer ownership and permissions that match the hosting account and web-server user; `775` for writable directories and `755` elsewhere are common starting points, but use Hostinger's required ownership model. Do not default to `777`; use it only as temporary provider-guided troubleshooting and revert it immediately.

Run:

```bash
php artisan storage:link
```

This creates the intended `public/storage` link to `storage/app/public`. Confirm only intended public media is available through it.

## Queue and scheduler

The project includes database queue tables and scheduled notification/operations tasks. After migrations, use:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

On Hostinger shared hosting, where a persistent worker may not be supported, create a hPanel Cron Job that runs every minute:

```cron
* * * * * cd /home/<hostinger-user>/retailpos-platform && php artisan queue:work --stop-when-empty --sleep=1 --tries=3 --timeout=90 >> /dev/null 2>&1
```

For the scheduler, create a separate every-minute hPanel Cron Job:

```cron
* * * * * cd /home/<hostinger-user>/retailpos-platform && php artisan schedule:run >> /dev/null 2>&1
```

Replace the sample path and, if required by Hostinger, `php` with its configured PHP CLI path. Do not add jobs during this deployment phase. A future VPS may replace the cron-based queue fallback with a supervised worker.

## Cache and post-deploy commands

Run these after dependencies, assets, environment, and migrations are in place:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
php artisan schedule:list
php artisan route:list --name=pos
```

If the deployment behaves unexpectedly after an environment change, clear and rebuild the relevant cache deliberately:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## PWA and offline POS smoke tests

Use a real HTTPS browser session at `https://app.retailpos.biz`; do not test PWA behavior through an HTTP preview.

1. Sign in as the prepared administrator and confirm the dashboard loads.
2. Open `/pos/terminal`, search or scan a product, select a customer, complete a cash demo bill, and open/print its browser receipt.
3. On a phone, open `/pos/mobile`, verify touch navigation and the installed-app manifest prompt/metadata, then add the app to the home screen where the browser supports it.
4. In browser developer tools, confirm the POS service worker registers and `pos-manifest.webmanifest` loads without mixed-content errors.
5. While online, let the terminal initialise its IndexedDB cache. Simulate a short offline period, queue a permitted offline bill through the existing payment modal, then restore connectivity and confirm it syncs.
6. Verify `/pos/offline` as an Administrator or Manager: the batch/record, official sale reference, and any warnings/errors must be visible.
7. Check that the normal online terminal, mobile POS, held bills, receipt route, and dashboard still work after cache optimisation.

Offline mode is designed for short outages only. It is not a substitute for long-term disconnected operation, payment verification, stock-conflict automation, or multi-device reconciliation.

## Security and operational checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, and `APP_URL=https://app.retailpos.biz` are set.
- [ ] SSL is active and the browser does not report mixed content.
- [ ] Only the Laravel `public` directory is the document root.
- [ ] `.env`, `vendor`, `storage`, `app`, `routes`, `database`, and `composer.json` are not browser-accessible.
- [ ] Database credentials and `APP_KEY` are server-only and not committed.
- [ ] Secure, HTTP-only, SameSite Lax session cookies are enabled.
- [ ] `storage` and `bootstrap/cache` are writable without broad `777` permissions.
- [ ] `storage:link`, configuration, route, view, and event caches have completed.
- [ ] Login, CSRF protection, protected routes, and error pages work without exposing stack traces.
- [ ] Production administrator credentials have been changed from any demo/default credentials.
- [ ] Queue and scheduler cron entries are active and logs show no repeated errors.
- [ ] A database and storage backup exists before the deployment.
- [ ] No test, debug, or local development routes are exposed.

## Backup and rollback

Before every deployment, export the database from hPanel and archive the relevant `storage` files. Keep the Git repository as the source-code history, but do not rely on it for database or uploaded-media recovery. Before a significant migration, retain a verified export and document the application commit being deployed.

To roll back application code, deploy the prior known-good Git commit or release only after taking a fresh backup. Do not roll back database migrations casually; review the migration and restore the database backup if a schema rollback would risk data loss. Review `storage/logs/laravel.log`, the Operations Monitor, queued failures, and Hostinger error logs after every deployment.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| 403/404 or source files accessible | Verify the subdomain document root is exactly the Laravel `public` directory. |
| 500 after deploy | Check `storage/logs/laravel.log`, `.env` values, PHP version/extensions, and write access to `storage` and `bootstrap/cache`. Keep `APP_DEBUG=false`. |
| Stale routes/config | Run `php artisan optimize:clear`, then rebuild config, route, view, and event caches. |
| Missing CSS/JS | Confirm `npm run build` completed for the deployed commit and that `public/build` was uploaded. |
| Login/session failures | Confirm the database migrations ran, `SESSION_DRIVER=database`, secure cookie settings, HTTPS, and app URL are correct. |
| Queue work does not progress | Confirm `QUEUE_CONNECTION=database`, `jobs` migration exists, the cron command uses the right path/PHP binary, and inspect failed jobs. |
| Offline POS does not sync | Confirm HTTPS, service-worker registration, IndexedDB availability, a signed-in session, POS offline route access, and `/pos/offline` monitor records. |

## Current deployment limits

- This environment is staging/demo first; it is not ready for real customer billing until the smoke tests pass.
- External payment gateways, live UPI/card terminals, direct thermal-printer drivers, finance/accounting, WhatsApp/SMS, AI, and n8n are not configured.
- The queue may need the documented cron fallback on shared Hostinger hosting.
