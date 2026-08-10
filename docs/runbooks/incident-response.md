# Incident response runbook

Record the incident ID, tenant, affected customer/service or router, first observed time, last known good time, operator, command IDs, and customer communication status. Do not place secrets, OTPs, or full payment credentials in the incident record.

## Router down or network drift

1. Confirm whether the fault is platform-wide, POP-scoped, zone-scoped, or limited to one router.
2. Check `/api/v1/health`, router connection status, repeated-failure incidents, and pending network commands.
3. Keep customer-facing service state separate from device state while the command is pending. Do not bulk-suspend or bulk-activate customers without an approved incident decision.
4. Run the subscriber comparison in report-only mode:

   ```powershell
   php artisan routers:reconcile-subscribers
   ```

5. Use `--heal` only with an approved change record and a verified RouterOS connection. Record the resulting platform-only and router-only lists.
6. If the outage is scoped, confirm the incident notice reached only the affected customer set and that recovery closes the matching notice.

## Queue stuck or commands not progressing

1. Check worker process health, queue depth, failed jobs, Redis availability, and the worker heartbeat.
2. Inspect the oldest failed job and its tenant context before retrying.
3. Fix the dependency or configuration issue first, then retry a single representative job. Use the job’s existing idempotency boundary; do not duplicate a payment, notification, or network command manually.
4. Restart workers gracefully after deployment or code/config changes. Do not clear the queue unless the incident owner has approved data-loss impact.
5. Recheck `/api/v1/health`, `php artisan queue:failed`, and the business record after recovery.

## Ledger invariant mismatch

1. Stop manual finance corrections and preserve the original evidence.
2. Run:

   ```powershell
   php artisan ledger:check-invariants
   ```

3. Capture the tenant, violation type, journal/payment/invoice IDs, and deployment version. Compare the append-only journal, allocations, customer balance projection, and partner wallet ledger.
4. Do not update balances, journal lines, or payment rows directly. Escalate to the finance owner and engineering owner for a migration or approved adjustment workflow.
5. After remediation, rerun the invariant command and attach the clean output to the incident before resuming normal corrections.

## Restore or suspected data loss

Follow [backup and restore](backup-restore.md). Restore into an isolated environment first, never over the live database. Validate migrations, tenant counts, customer ownership, payment and journal invariants, portal ownership, queue configuration, and secret references before any controlled cutover.

## Closeout

Close only after customer communication, queued side effects, ledger state, router state, backup state, and monitoring alerts are reconciled. Include a short timeline, root cause, impact, corrective actions, and an owner/date for every follow-up.
