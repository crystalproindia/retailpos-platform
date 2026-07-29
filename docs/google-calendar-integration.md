# Google Calendar and Meet Integration

## Google Cloud setup

1. Create or select a Google Cloud project.
2. Enable the Google Calendar API.
3. Configure the OAuth consent screen for the organization and the users who will connect a company calendar.
4. Create a Web application OAuth client.
5. Add the exact Command Center callback URL as an authorized redirect URI:
   `https://app.retailpos.biz/integrations/google/callback`

Set these production environment variables. Do not store a connected account's tokens in environment files.

```dotenv
GOOGLE_CALENDAR_CLIENT_ID=
GOOGLE_CALENDAR_CLIENT_SECRET=
GOOGLE_CALENDAR_REDIRECT_URI=https://app.retailpos.biz/integrations/google/callback
```

## Command Center workflow

An Administrator opens **Integrations > Google Calendar**, connects the account, selects a calendar and timezone, then tests the connection. Tokens are encrypted by Laravel at rest. Demos remain usable when no Google connection exists; those schedules are marked `skipped_not_configured`.

Google Calendar events use the demo ID as a stable conference request identifier. Retrying or updating a synchronized demo reuses its existing event instead of creating a duplicate. Disconnect removes stored credentials; reconnect the account if refresh authorization is revoked.

## Production deployment

Deploy the application, set the variables in the hosting environment, then run:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Use the integration Test connection action after deployment. Never paste OAuth access tokens, refresh tokens, or raw Google API responses into tickets or logs.
