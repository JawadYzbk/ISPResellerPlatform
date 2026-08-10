# Observability runbook

## Liveness and dependency health

- `GET /up` is the framework liveness probe.
- `GET /api/v1/health` is the deeper dependency probe. It checks the database, cache, default queue depth, scheduler heartbeat and queue-worker heartbeat.
- `php artisan platform:heartbeat` records the scheduler heartbeat and queues the worker heartbeat job. The scheduler runs it every minute through `bootstrap/app.php`.
- `php artisan ledger:check-invariants` checks balanced journal entries, customer projections and partner wallet invariants. It runs daily at 03:00.

The health endpoint is intentionally unauthenticated and must not expose credentials or customer data. A `degraded` response requires alerting; it is not a replacement for a queue, scheduler or database monitor.

## Sentry

Set `SENTRY_DSN`, `SENTRY_ENVIRONMENT` and a release identifier in the deployment secret manager. `SENTRY_SEND_DEFAULT_PII` remains `false`; the project `before_send` hook also removes user identity, request payloads, cookies and headers before an event is sent.

Use a staging DSN first and verify one controlled exception. Do not run the test command against a production DSN without an agreed maintenance window. Redact subscriber phone numbers, email addresses, tokens, credentials, authorization headers and payment details from any manually attached context.

## Alert routing

At minimum, alert on:

- health status `degraded` for more than five minutes;
- a stale scheduler or queue-worker heartbeat;
- queue depth/age growth and failed network commands;
- a failed or unhealthy backup check;
- a failed ledger invariant check;
- repeated router health incidents.

Route alerts to an external on-call destination with a five-minute response target. The repository provides the signals and health checks; the destination, escalation policy and Sentry project are deployment configuration.

## Realtime transport

The repository includes Laravel Reverb, a private tenant channel authorization rule keyed by tenant public ID, and an authenticated React client bridge. Keep local development on `BROADCAST_CONNECTION=log` and `VITE_REVERB_ENABLED=false`; enable Reverb only after setting `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, the public host/scheme, a non-wildcard `REVERB_ALLOWED_ORIGINS` list, and matching `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, and `VITE_REVERB_SCHEME` build variables.

Start the transport under a supervisor in a deployment environment:

```powershell
php artisan reverb:start
```

Verify a service status transition is visible to an authorized tenant client and unavailable to a different tenant. Staff pages reload current Inertia data when `service.status.changed` arrives; the client subscribes only when `VITE_REVERB_ENABLED=true`. Reverb installation and configuration are repository-complete; TLS termination, process supervision and runtime rollout remain deployment work.
