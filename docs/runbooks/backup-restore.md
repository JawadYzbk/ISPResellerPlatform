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

## Local rehearsal evidence

On 2026-08-10, the repository wiring was exercised with `BACKUP_DISK=local` and a temporary process-only archive password:

1. `backup:run --only-db --disable-notifications --tries=1` created and verified a 15.03 KB encrypted SQLite archive.
2. The archive was extracted into a new temporary directory with the rehearsal password.
3. The SQL dump was replayed into a clean temporary SQLite database; 54 migrations, 1 tenant, and 1 user were readable afterward.
4. The temporary archive and restored database were removed after verification.

This proves the package wiring, archive verification, encryption password path, and SQLite replay. It does not close the deployment gate for PostgreSQL, object-storage media, off-site retention, or a second-person production-shaped restore rehearsal.

On 2026-08-10, the repository Docker topology was exercised in a disposable Compose project with PostgreSQL 17, Redis 7 and MinIO:

1. The application image was rebuilt with the PostgreSQL client tools, and the S3 Flysystem adapter was installed as a locked production dependency.
2. A PostgreSQL 17 schema with one tenant and a media object in MinIO was created. `backup:run` produced a 6.33 MB encrypted archive in the MinIO `isp-backups` bucket; `backup:list` reported the S3 disk reachable and healthy with one backup.
3. The media object was verified independently in the MinIO `isp-media` bucket.
4. A direct `pg_dump` was streamed into a fresh `isp_restore` database. The restore completed with 57 migrations and one tenant readable afterward.
5. The disposable Compose project, volumes and temporary password were removed after verification.

This closes the repository Docker evidence for PostgreSQL dumping, S3-compatible archive delivery, MinIO media presence and direct-dump restore. Off-site retention, production secret-manager recovery and a second-person restore rehearsal remain release gates.

## Failure handling

If the database restore succeeds but media is incomplete, keep the restored application offline and restore the object snapshot before allowing technician uploads or work-order completion. If the dump checksum fails, discard it and select the previous verified backup; never attempt a partial SQL repair on a corrupted archive.
