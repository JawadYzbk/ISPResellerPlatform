# Deployment runbook

Use this procedure for a production-shaped deployment. Keep the release artifact, migration output, health results, and operator approval together for the change record.

## Before the window

- Confirm the target release commit and a tested rollback artifact.
- Confirm database, Redis, queue, mail, object storage, Sentry, backup, payment, notification, router, and Reverb secrets are present in the secret store.
- Confirm a recent backup and a successful isolated restore rehearsal. A successful backup listing alone is not a restore test.
- Announce the window to operations and support. Pause imports and planned network maintenance during schema changes.

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
```

Start or restart the long-running processes from the deployment supervisor:

```powershell
php artisan queue:work --tries=3
php artisan schedule:work
php artisan reverb:start
```

Use the supervisor’s managed equivalents in production. Do not run duplicate queue workers or Reverb servers accidentally.

## Verification

1. Check `php artisan migrate:status`.
2. Check `/up` and `/api/v1/health`.
3. Run `php artisan ledger:check-invariants`.
4. Confirm a queue job is processed and the worker heartbeat becomes fresh.
5. Confirm the login, customer lookup, payment receipt, portal sign-in, technician work-order read, and a non-mutating router health path.
6. Confirm Sentry receives a synthetic test event only in the approved environment and that the event contains no request payload, headers, cookies, or user identity unless the privacy review explicitly allows it.

## Rollback and failure handling

If verification fails, stop new traffic or queue intake as appropriate, retain logs and failed-job IDs, and switch application processes to the last known-good artifact. Do not reverse a migration or edit ledger rows as an emergency shortcut. If a forward-compatible fix is required, ship a new migration and release.

After rollback, rerun health, queue, ledger-invariant, and customer-ownership checks. Record whether any queued network or notification commands were emitted during the failed window and reconcile them by idempotency key.

See [backup and restore](backup-restore.md), [observability](observability.md), and [app-key rotation](app-key-rotation.md) for the related procedures.
