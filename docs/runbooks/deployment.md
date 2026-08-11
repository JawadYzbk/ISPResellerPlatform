# Deployment runbook

Use this procedure for a production-shaped deployment. Keep the release artifact, migration output, health results, and operator approval together for the change record.

## Before the window

- Confirm the target release commit and a tested rollback artifact.
- Confirm database, Redis, queue, mail, object storage, Sentry, backup, monitoring alert, payment, notification, router, and Reverb secrets are present in the secret store. If using Web.js notifications, also provision the bridge token, callback secret, and persistent session volume.
- Confirm a recent backup and a successful isolated restore rehearsal. A successful backup listing alone is not a restore test.
- Announce the window to operations and support. Pause imports and planned network maintenance during schema changes.

## Production-shaped Docker topology

The repository includes `docker-compose.production.yml` for a self-hosted single-VPS deployment. It separates Nginx, PHP-FPM, queue workers, the scheduler, Reverb, PostgreSQL 17, Redis, and private MinIO object storage. Set deployment secrets and the public URL in the environment before starting it; the placeholder values are intentionally rejected by the production preflight.

```powershell
docker compose -f docker-compose.production.yml build
docker compose -f docker-compose.production.yml up -d postgres redis minio minio-init
docker compose -f docker-compose.production.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.production.yml run --rm app php artisan db:seed --class=CapabilitySeeder --force
docker compose -f docker-compose.production.yml run --rm app php artisan optimize:clear
docker compose -f docker-compose.production.yml run --rm app php artisan config:cache
docker compose -f docker-compose.production.yml run --rm app php artisan route:cache
docker compose -f docker-compose.production.yml run --rm app php artisan event:cache
docker compose -f docker-compose.production.yml up -d app worker scheduler reverb web
docker compose -f docker-compose.production.yml exec app php artisan platform:preflight --production
```

The WhatsApp Web.js bridge is deliberately behind the `whatsapp` Compose profile and the application defaults to the Cloud API path. To opt in, set `WHATSAPP_PROVIDER=web`, `WHATSAPP_WEB_ENABLED=true`, `WHATSAPP_WEB_TOKEN`, and `WHATSAPP_WEBHOOK_SECRET`, then start it with `--profile whatsapp`. Pair the account through the private bridge `/qr` endpoint, verify `/status` reports `ready`, and only then send a controlled test notification. Keep the session volume private and backed up; the bridge uses an unofficial WhatsApp Web client and may be disconnected or blocked by WhatsApp.

Frankfurter synchronization is disabled by default. For a tenant whose base currency is USD and collection currency is LBP, set `FRANKFURTER_ENABLED=true`, confirm the approved quote policy, and run `php artisan fx:sync-frankfurter` once before enabling the scheduler. Imported quotes are append-only effective-dated ratios. Manual rates remain available for treasury or street-rate policy, and payment receipts preserve the selected rate, source, effective date, and rounding mode.

For online customer payments, set `PAYMENT_GATEWAY=stripe`, `STRIPE_SECRET`, `STRIPE_PUBLISHABLE_KEY`, and `STRIPE_WEBHOOK_SECRET`. Register `POST https://<public-host>/api/v1/webhooks/payments/stripe` in Stripe and subscribe only to `payment_intent.succeeded`; the application verifies Stripe's raw request body and `Stripe-Signature` header before settling the invoice. The portal uses the publishable key and PaymentIntent client secret only; never place `STRIPE_SECRET` in frontend configuration. Test one successful payment and one rejected signature in the approved Stripe environment before enabling customer checkout. See the [Stripe PaymentIntent](https://docs.stripe.com/api/payment_intents/create) and [webhook verification](https://docs.stripe.com/webhooks) documentation for provider-side setup.

For collector Whish Pay collection, set `WHISH_ENABLED=true`, `WHISH_ENVIRONMENT=sandbox` or `production`, `WHISH_CHANNEL`, `WHISH_SECRET`, and `WHISH_WEBSITE_URL`. Configure the four callback/redirect URLs when the provider merchant account requires fixed URLs; otherwise the application derives them from the public `APP_URL`. Collectors use `POST /api/v1/collector/payments/whish` with an idempotency key and receive the Whish collect URL plus an SVG QR data URI. Register both `GET /api/v1/webhooks/payments/whish/success` and `/failure` with Whish. The callback parameters are treated as lookup hints only: the server re-queries Whish and requires a successful status before posting through `RecordPayment`. If Whish returns amount or currency, each value must match the pre-created attempt; if the provider omits either optional field, the stored attempt and requested callback currency remain authoritative. Keep the merchant secret server-side and validate the current official Whish merchant contract, sandbox credentials, callback fields, and production endpoint before enabling live collection.

The shared `bootstrap/cache` volume makes the cache commands visible to all PHP services. Do not expose PostgreSQL, Redis, or MinIO directly to the public internet. Put TLS termination and the public DNS name in front of the `web` service, and set `APP_URL`, `REVERB_HOST`, and `REVERB_ALLOWED_ORIGINS` to that public origin.

## Application release

Run from the release directory with the intended PHP, Composer, Node, and npm versions:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan platform:preflight --production
```

`platform:preflight --production` must pass before traffic is admitted. It checks the application key, database connectivity, migration state, production environment, debug mode, public HTTPS URL, secure session cookies, asynchronous queue, persistent cache, tenant capability assignments, Sentry privacy configuration, encrypted off-site backups, private object storage, monitoring alert routing, Reverb credentials when realtime is enabled, and the Web.js bridge configuration when that provider is selected. It does not replace the scheduler, queue-worker, backup-restore, or external alert checks below.

Start or restart the long-running processes from the deployment supervisor:

```powershell
php artisan queue:work --tries=3
php artisan schedule:work
php artisan reverb:start
```

Use the supervisor’s managed equivalents in production. Do not run duplicate queue workers or Reverb servers accidentally.

## Verification

On 2026-08-11, release commit `93bc185` was rebuilt in an isolated `isp-manager-acceptance` project and completed a fresh PostgreSQL 17 migration and demo seed, production config/route/event caching, `platform:preflight --production`, Nginx `/login` and `/docs/api` probes, `/up`, `/api/v1/health`, and the WhatsApp bridge health probe (`status: qr`). Synthetic containers, volumes, network and environment file were removed after the run. This verifies current image and topology wiring; it does not claim live provider credentials, WhatsApp pairing, or RouterOS acceptance.

1. Check `php artisan platform:preflight --production` and `php artisan migrate:status`.
2. Check `/up` and `/api/v1/health`.
3. Run `php artisan ledger:check-invariants`.
4. Confirm a queue job is processed and the worker heartbeat becomes fresh.
5. Confirm the login, customer lookup, payment receipt, portal sign-in, technician work-order read, and a non-mutating router health path.
6. Confirm Sentry receives a synthetic test event only in the approved environment and that the event contains no request payload, headers, cookies, or user identity unless the privacy review explicitly allows it.

## Rollback and failure handling

If verification fails, stop new traffic or queue intake as appropriate, retain logs and failed-job IDs, and switch application processes to the last known-good artifact. Do not reverse a migration or edit ledger rows as an emergency shortcut. If a forward-compatible fix is required, ship a new migration and release.

After rollback, rerun health, queue, ledger-invariant, and customer-ownership checks. Record whether any queued network or notification commands were emitted during the failed window and reconcile them by idempotency key.

See [backup and restore](backup-restore.md), [observability](observability.md), and [app-key rotation](app-key-rotation.md) for the related procedures.
