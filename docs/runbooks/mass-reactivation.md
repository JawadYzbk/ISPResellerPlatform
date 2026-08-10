# Mass reactivation after an erroneous suspension

Use this procedure when a billing, scheduler, or operator error suspended a group of services that should remain active. The incident owner must approve the affected service list and the customer communication before any resume request is sent.

## Contain the incident

1. Declare an incident and record the tenant, incident window, suspected cause, incident owner, approver, and deployment version.
2. Stop the cause before restoring services. If the hourly overdue-suspension job is responsible, pause that scheduled execution through the process supervisor or maintenance control. Do not edit service rows or disable unrelated billing jobs.
3. If one router is affected, pause enforcement for that router as described in [incident response](incident-response.md). Do not mass-suspend or mass-reactivate a router while its state is unknown.
4. Preserve the original service events, suspension reason, billing records, and failed-job records. Never repair this incident with a direct bulk `UPDATE` or by deleting queue/audit history.

## Build and approve the target set

1. Capture a read-only snapshot of suspended services for the tenant. The API supports `GET /api/v1/services?filter[status]=suspended` with cursor pagination; retain each service public ID, customer public ID, suspension reason, router, and current network state.
2. Compare that list with the billing run, scheduler logs, service events, and the incident window. Exclude services that were legitimately suspended, terminated, manually held, or already restored.
3. Produce an explicit newline-delimited list of approved service public IDs. Two operators must review the count and sample records before execution.
4. Recheck the list immediately before each batch. A service must still be suspended and must still belong to the incident population.

## Restore in bounded batches

There is intentionally no bulk state-mutation endpoint. Resume each approved service through the idempotent, audited service workflow:

```text
POST /api/v1/services/{service_public_id}/resume
Authorization: Bearer <operator-token>
Idempotency-Key: mass-reactivation-<incident-id>-<service_public_id>
```

Run small batches with a pause between batches. The endpoint returns `202` and queues the network `activate` command; an `active` service with a pending network state is not yet proof that the router has applied the change. For services suspended for a reason other than `auto_overdue`, the operator also needs the `services.force_resume` capability.

After every batch:

- confirm the response IDs match the approved input list;
- inspect command success/failure and retry only through the normal network retry workflow;
- check router reachability and stale desired-state guards;
- stop if an unexpected service, duplicate command, or growing failure rate appears.

## Verify and communicate

1. Re-run the read-only suspended-service snapshot and reconcile it with the approved list. Investigate every missing or extra service.
2. Confirm service events, network commands, audit entries, customer notices, and router state are consistent. Do not change invoices, payments, allocations, or journal rows to make counts match.
3. Run `php artisan ledger:check-invariants` and record the result in the incident timeline.
4. Send the approved customer message through the configured notification workflow. Do not include internal incident details or credentials.
5. Keep the original evidence, request IDs, command IDs, approvals, customer-impact count, and follow-up owner with the incident record. Close only after the scheduler/worker is healthy and the containment control is removed.

### Customer communication template

> We identified an account-status error that temporarily affected your service. Your service has been restored, and we are monitoring the network action. No payment or account-balance action is required from you. Please contact support if your connection does not return shortly.
