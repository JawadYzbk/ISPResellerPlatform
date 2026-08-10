# Backup and restore runbook

This is the release-level restore procedure for the current Docker topology. It is intentionally explicit about the database and object-storage boundaries; production off-site retention, encryption keys and restore rehearsal still belong in the deployment environment.

## Backup

1. Record the release commit, database migration status and current `APP_KEY` secret version.
2. Pause queue workers and scheduled commands so no write-heavy job is running during the snapshot.
3. Create a PostgreSQL custom-format dump from the application network:

   ```powershell
   docker compose exec -T postgres pg_dump -U isp_manager -Fc isp_manager > backup-YYYYMMDD-HHmm.dump
   ```

4. Copy private uploaded media from the configured storage disk to the encrypted backup target. For the local Docker setup, the named `minio-data` volume must be snapshotted with the same release timestamp.
5. Encrypt the dump and record its checksum. Upload it to the off-site retention target only through the deployment secret manager's approved backup job.
6. Verify the dump is readable with `pg_restore --list` and retain the checksum beside the backup metadata, never inside the application database.

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

## Failure handling

If the database restore succeeds but media is incomplete, keep the restored application offline and restore the object snapshot before allowing technician uploads or work-order completion. If the dump checksum fails, discard it and select the previous verified backup; never attempt a partial SQL repair on a corrupted archive.
