# Railway Deployment Guide

This project is best deployed to Railway as separate services that share the same codebase:

- App service
- MySQL service
- Worker service
- Optional cron service

## Why this setup

The web app, API, and Blade frontend all live in one Laravel app. The database is separate. Queue processing should run in a separate worker. A cron service is optional for now because the project currently does not define active Laravel scheduled tasks.

## Files added for Railway

- `railway/init-app.sh`
- `railway/run-worker.sh`
- `railway/run-cron.sh`

## App service settings

- Builder: `RAILPACK`
- Build command: `npm run build`
- Start command: leave blank so Railway can auto-detect Laravel and run `php-fpm` with Caddy
- Pre-deploy command: `chmod +x ./railway/init-app.sh && sh ./railway/init-app.sh`
- Healthcheck path: `/up`

## Worker service settings

- Builder: `RAILPACK`
- Build command: `npm run build`
- Start command: `chmod +x ./railway/run-worker.sh && sh ./railway/run-worker.sh`

## Optional cron service settings

- Builder: `RAILPACK`
- Build command: `npm run build`
- Start command: `chmod +x ./railway/run-cron.sh && sh ./railway/run-cron.sh`
- Cron schedule: optional

## Required app variables

Set these on the app service and copy the same values to the worker and cron services unless noted otherwise.

```env
APP_NAME=HealthLink
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-app-domain.up.railway.app
APP_KEY=base64:GENERATE_THIS_LOCALLY

DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
NIXPACKS_PHP_ROOT_DIR=/app/public

LOG_CHANNEL=stderr
LOG_LEVEL=info

MAIL_MAILER=smtp
MAIL_SCHEME=tls
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_FROM_ADDRESS=no-reply@your-domain.com
MAIL_FROM_NAME="HealthLink"

BHW_MOBILE_APK_URL=
```

## Volume

The project currently writes uploaded APKs and mobile visit photos to Laravel's local disk. Create a Railway Volume from the project canvas, then connect it to the app service and mount it at:

`/app/storage/app`

This keeps files inside `storage/app` persistent across deploys.

### How to create the volume in Railway

Railway does not show a volume section inside a service until the volume already exists.

1. Open the project canvas.
2. Press `Cmd/Ctrl + K` and search for `New Volume`, or right-click the canvas.
3. Create the volume.
4. When prompted, connect it to `healthlink-app`.
5. Set the mount path to `/app/storage/app`.

## Notes

- Only the app service should get a public domain.
- The worker service does not need a public domain.
- The cron service can be skipped until you actually add scheduled tasks.
- If you later add a `Dockerfile`, Railway will prefer it over Railpack.
