# APP_KEY rotation runbook

The `security:rotate-app-key` command re-encrypts application ciphertext in one database transaction. It does not edit `.env`, deployment secrets, queue workers or cached configuration. That separation is intentional: the deployment key must only change after the data transaction succeeds.

## Before the change

1. Schedule a maintenance window and stop queue workers, scheduler containers and any long-running Horizon process.
2. Confirm a current database backup and record its timestamp. Do not start a rotation without a tested restore point.
3. Save the current `APP_KEY` in the deployment secret manager as `APP_KEY_PREVIOUS`. Never put either key in source control or a ticket.
4. Confirm the application is healthy and note one non-production service credential that can be tested after the change.

## Rotate

Run from the release matching the deployed code while the application still uses the old key:

```powershell
php artisan security:rotate-app-key --new-key="base64:<generated-32-byte-key>"
```

If the old key is not the key loaded by the process, pass it through the secret manager rather than shell history:

```powershell
php artisan security:rotate-app-key --old-key="$env:APP_KEY_PREVIOUS" --new-key="$env:APP_KEY_NEXT"
```

The command reports the number of rows re-encrypted. A failure rolls the transaction back; leave `APP_KEY` unchanged, investigate the ciphertext/backup, and rerun only after the cause is understood.

After a successful command:

1. Replace `APP_KEY` in the deployment secret manager with the reported replacement value.
2. Restart PHP workers, queue workers and the scheduler so every process loads the same key.
3. Clear only the application configuration cache through the normal deployment pipeline.
4. Run `php artisan migrate:status` and the `/api/v1/health` check.
5. Exercise one service credential, one router connection test and a two-factor login in staging. Confirm that no credential value appears in logs or responses.

## Recovery

If the deployment key was not changed, keep using the previous key and no data rollback is needed. If the new key was deployed but validation fails, keep the application on the new key and reverse the ciphertext with the previous value as the destination:

```powershell
php artisan security:rotate-app-key --old-key="$env:APP_KEY_NEXT" --new-key="$env:APP_KEY_PREVIOUS"
```

Only after that command succeeds should `APP_KEY` be switched back and all workers restarted. If either key is unavailable, stop the application and restore the database and deployment secret from the same backup point.
