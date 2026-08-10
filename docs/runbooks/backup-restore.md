# Backup and restore runbook

This is the release-level restore procedure for the current Docker topology. The application is wired to `spatie/laravel-backup`; production must provide an S3-compatible `BACKUP_DISK`, a secret `BACKUP_ARCHIVE_PASSWORD`, and an operator mailbox in `BACKUP_NOTIFY_TO`. The package creates and monitors encrypted archives; restore still requires an isolated host procedure.

## Backup

1. Record the release commit, database migration status and current `APP_KEY` secret version.
2. Confirm `BACKUP_ARCHIVE_PASSWORD` is present in the deployment secret manager and that `BACKUP_DISK` points to the off-site target. Never put either value in source control.
3. Pause queue workers and scheduled commands so no write-heavy job is running during the snapshot.
4. Run and verify the application backup:

   ```powershell
   php artisan backup:run
   php artisan backup:list
   php artisan backup:monitor
   ```

   `backup:run` includes the configured database and application files while excluding `vendor`, `node_modules` and framework cache. The backup destination is encrypted when `BACKUP_ARCHIVE_PASSWORD` is set and `BACKUP_ENCRYPTION=default`.

5. For the Docker PostgreSQL topology, retain a separately verifiable custom-format dump when the deployment policy requires direct database recovery:

   ```powershell
   docker compose exec -T postgres pg_dump -U isp_manager -Fc isp_manager > backup-YYYYMMDD-HHmm.dump
   ```

6. Copy private uploaded media from the configured storage disk to the encrypted backup target. For the local Docker setup, the named `minio-data` volume must be snapshotted with the same release timestamp.
7. Encrypt the direct dump and record its checksum. Upload it to the off-site retention target only through the deployment secret manager's approved backup job.
8. Verify the dump is readable with `pg_restore --list` and retain the checksum beside the backup metadata, never inside the application database.

## Restore rehearsal

1. Provision a clean host with the target release, an empty PostgreSQL database, Redis and object storage.
2. Restore the dump into the empty database:

   ```powershell
   pg_restore --clean --if-exists --no-owner --dbname=isp_manager backup-YYYYMMDD-HHmm.dump
   ```

3. Restore the matching media snapshot and deployment secrets. Do not use the current production `APP_KEY` unless it is the key version recorded with the backup.
4. Run `php artisan migrate:status`, `php artisan route:list --path=api/v1` and `php artisan test --testsuite=Feature` against the restored environment.
5. Verify `/api/v1/health`, a customer read, a payment history read, one private media metadata row and a queued job. Do not post a real payment during a rehearsal.
6. Record restore duration, backup age, migration output and any missing object-storage keys. The rehearsal is incomplete until a person other than the author follows the steps successfully.

## Retention and alerts

Run cleanup after retention policy review:

```powershell
php artisan backup:clean
```

The backup package sends success, failure, cleanup and health notifications to `BACKUP_NOTIFY_TO`. A successful `backup:list` is not a restore rehearsal; the monthly isolated restore remains a release gate.

## Failure handling

If the database restore succeeds but media is incomplete, keep the restored application offline and restore the object snapshot before allowing technician uploads or work-order completion. If the dump checksum fails, discard it and select the previous verified backup; never attempt a partial SQL repair on a corrupted archive.
