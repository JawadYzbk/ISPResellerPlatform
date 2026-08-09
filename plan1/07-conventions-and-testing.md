# 07 — Conventions, Testing, Definition of Done

## 1. Code conventions

### Controllers
Thin. Validate via a FormRequest, authorise via a Policy, call **one** Action, return a response. No queries, no conditionals on domain state, no `DB::transaction()`.

```php
final class RecordPaymentController
{
    public function __construct(private readonly RecordPayment $recordPayment) {}

    public function __invoke(RecordPaymentRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('recordPayment', $customer);
        $payment = $this->recordPayment->handle(PaymentData::from($request));
        return back()->with('flash.payment', PaymentData::from($payment));
    }
}
```

### Actions
`final readonly class`, one public method named `handle`, dependencies injected. Own the transaction boundary. Fire domain events rather than calling other Actions where possible — keep the dependency graph shallow.

```php
final readonly class SuspendService
{
    public function handle(Service $service, SuspensionReason $reason, ?User $actor = null): Service
    {
        return DB::transaction(function () use ($service, $reason, $actor) {
            $service->forceFill([
                'status' => ServiceStatus::Suspended,
                'suspension_reason' => $reason,
                'network_state' => NetworkState::PendingSync,
            ])->save();

            ServiceEvent::record($service, ServiceEventType::Suspended, $reason, $actor);
            event(new ServiceSuspended($service, $reason));

            return $service;
        });
    }
}
```

Note: the Action changes business state and emits an event. The **listener** dispatches the network job. Actions do not queue infrastructure work directly — that keeps them testable and keeps the network layer swappable.

### Models
Relations, casts, scopes, accessors. **No business logic.** No `static::create()` wrappers containing rules. Enums cast on every state column. `$hidden` on every secret.

### Jobs
`ShouldQueue`, `TenantAware`, explicit `$tries`, `$backoff`, `$timeout`, and `uniqueId()` where duplicate dispatch is possible. Jobs are thin wrappers that resolve and call an Action or a driver.

### Naming
- Actions: imperative verb — `RecordPayment`, `SuspendService`, `ProvisionService`.
- Events: past tense — `PaymentRecorded`, `ServiceSuspended`.
- Jobs: imperative + noun — `ExecuteNetworkCommand`, `RollupDailyUsage`.
- Enums: singular noun — `ServiceStatus`, `PaymentMethod`.
- Migrations: `create_services_table`, `add_network_state_to_services_table`.
- Routes: kebab-case plural resources, verbs only for non-CRUD actions (`/services/{uuid}/disconnect-session`).

### Enums
Backed by strings, with a `label()` method returning a translation key, and a `color()` for the UI. Never compare against raw strings anywhere in the codebase.

### Migrations
- One concern per migration.
- Every FK has an index and an explicit `onDelete` behaviour (`restrict` for financial links, `cascade` only for owned children).
- Destructive changes are three deploys: add nullable → backfill → constrain/drop.
- Never write data-transforming logic inside a migration that could take minutes on production volume — use a dedicated command run separately.

### Frontend
- Function components, hooks, no class components.
- Props typed against generated DTO types; no `any`, no `as unknown as`.
- One component per file, colocated with its subcomponents when they're not reused.
- Server state comes from Inertia props. Client state is `useState`/`useReducer`. **No Redux, no client-side data-fetching library** — Inertia is the state manager.
- Money is never formatted ad-hoc; always `<MoneyDisplay>` / `formatMoney()`.
- Dates are never formatted with the browser timezone for business dates; always the tenant timezone helper.
- Logical Tailwind properties only (`ms-`, `me-`, `ps-`, `pe-`, `start-`, `end-`) — enforce with an ESLint rule.

---

## 2. Testing strategy

### The pyramid for this product

| Layer | Tool | Coverage target | What belongs here |
|---|---|---|---|
| Architecture | Pest arch plugin | 100% of rules | conventions, isolation traits, no floats for money |
| Unit | Pest | 100% of `Domain/` | money arithmetic, FX, billing periods, proration, allocation strategies |
| Feature (HTTP) | Pest + Laravel | every route | auth, permissions, tenant isolation, validation, happy + failure paths |
| Integration (device) | Pest, tagged, nightly | driver surface | real RouterOS CHR + FreeRADIUS |
| E2E | Playwright | ~12 critical journeys | the core loop, in both LTR and RTL |

**Overall line coverage target: 80%.** But coverage is the wrong metric here — the right metric is that every item in `02 §15 Global Invariants` has a named test. List them in `tests/Feature/Invariants/` with matching filenames.

### Tests that must exist (non-negotiable)

1. `TenantIsolationTest` — parameterised over every tenant-scoped model, every access vector, expecting 404.
2. `LedgerIntegrityTest` — concurrent writes, running balance correctness, immutability, cache == sum.
3. `IdempotencyTest` — payments and network commands, including same-key-different-body → 409.
4. `InvoiceNumberingTest` — parallel processes, no gaps, no duplicates.
5. `BillingPeriodTest` — table-driven date arithmetic including month-end, leap year, DST.
6. `FxSnapshotTest` — historical amounts immune to new rates.
7. `NetworkCommandOrderingTest` — per-service serialisation.
8. `NetworkFailureTest` — every driver failure mode produces the right state, retries, and alert.
9. `SuspensionPrecedenceTest` — payment clears `auto_overdue` only.
10. `CredentialLeakTest` — snapshot every API resource and Inertia prop shape; assert no secret keys present (router/RADIUS/PPP passwords, national ID, upstream-credential plaintext).
11. `PermissionMatrixTest` — capability × route, both allow and deny; a token ability never grants access the user/tenant lacks.
12. `OfflineSyncTest` — batch push with replays, partial failures, out-of-order arrival.
13. `JournalBalanceTest` — every posted entry balances per currency; exactly one of debit/credit per line; nightly re-assertion over the seed.
14. `WalletConcurrencyTest` — two concurrent debits cannot overspend past the credit limit; wallet cache == sum of wallet journal lines.
15. `CredentialAssignmentTest` — one available upstream credential is reserved/assigned exactly once under concurrency; reveal requires capability + re-auth + audit; import never logs plaintext.
16. `ProvisioningVersionTest` — a stale command (older `desired_state_version`) is refused and cannot reactivate a since-suspended service.
17. `OutboxTest` — external work is only dispatched after commit; a failed external call never rolls back the posted payment/desired state; relay is replayable.
18. `ModuleBoundaryTest` (arch) — no model calls a router/gateway/message API; no cross-module raw SQL into another module's private tables; adapters sit behind interfaces.

### Test data
Factories for every model with realistic states (`->suspended()`, `->overdue()`, `->withUsage()`, `->drifted()`). A `DemoTenantSeeder` producing a full, coherent tenant: 200 customers, 3 zones, 2 POPs, 2 routers, 4 plans, 6 months of invoices and payments, open tickets, scheduled work orders, and stock. **Every phase must be demoable against this seed**, not just green in CI.

### What not to test
Framework behaviour, third-party libraries, getters/setters, or the exact HTML of a component. Test behaviour and contracts, not implementation.

---

## 3. Definition of Done

A ticket is done when **all** of the following are true:

- [ ] Acceptance criteria in `06` are demonstrably met (state how, in the commit message or PR body).
- [ ] Migrations run clean on an empty database **and** on the demo seed; rollback works.
- [ ] `vendor/bin/pint --test` clean.
- [ ] `vendor/bin/phpstan analyse` clean at the configured level.
- [ ] `vendor/bin/pest` green, including the architecture suite.
- [ ] `npx tsc --noEmit` clean; ESLint clean.
- [ ] New behaviour has tests; any invariant it touches has a named invariant test.
- [ ] No secret, credential, or PII in logs, props, API responses, or fixtures.
- [ ] Tenant scoping applied and tested for any new table.
- [ ] Any spec deviation is reflected in the relevant spec file **in the same commit**, and logged in `00 §5`.
- [ ] The feature is reachable and usable in the UI (or documented as API-only), and works in RTL.
- [ ] Demo seed updated if the feature introduces new state worth demonstrating.

---

## 4. Git and CI

- Branches: `feat/isp-034-renewal-on-payment`, `fix/…`, `chore/…`.
- Conventional commits. The ticket ID appears in the branch name and the commit body.
- Every PR: description states the ticket, what changed, how the AC was verified, and any spec edits.
- CI on every push: Pint → PHPStan → Pest (with coverage) → tsc → ESLint → build. Nightly: device integration suite + Playwright + `npm audit` + `composer audit`.
- Migrations are checked for backward compatibility by running the previous release's test suite against the new schema.

---

## 5. Environment configuration

Document every variable in `.env.example` with a comment. Group them:

```
# Core
APP_NAME, APP_ENV, APP_KEY, APP_URL, APP_TIMEZONE=UTC
# Data
DB_*, REDIS_*
# Queue / Horizon
QUEUE_CONNECTION=redis, HORIZON_*
# Storage
FILESYSTEM_DISK=s3, AWS_* (MinIO-compatible)
# Broadcasting
BROADCAST_CONNECTION=reverb, REVERB_*
# Network
NETWORK_DEFAULT_DRIVER, NETWORK_HTTP_TIMEOUT, NETWORK_MAX_ATTEMPTS, RADIUS_DB_*
# Messaging
WHATSAPP_*, SMS_DRIVER, SMS_*, FCM_*
# Payments
GATEWAY_* (per gateway)
# Observability
SENTRY_DSN, LOG_CHANNEL=stack, LOG_LEVEL
# Backups
BACKUP_DISK, BACKUP_NOTIFY_TO
```

`APP_TIMEZONE` stays UTC. Business time is always the tenant's timezone, resolved at the point of use. This is the single most common source of off-by-one-day billing bugs — never let it become implicit.

---

## 6. Runbooks to write in Phase 10

Each is a short, numbered, tested procedure — not prose.

1. **Router unreachable** — how to identify affected services, pause enforcement for that router (so you don't mass-suspend during an outage), and replay commands after recovery.
2. **Queue stuck / backlog growing** — diagnosing, scaling workers, clearing poison jobs safely.
3. **Ledger mismatch alert** — how to investigate without mutating the ledger; how to write the correcting reversal.
4. **Restore from backup** — full, tested, timed procedure on a clean host.
5. **Mass reactivation after a billing error** — the "we suspended 400 people by mistake" procedure, including the customer communication template.
6. **Key rotation** — `APP_KEY` rotation and credential re-encryption.
7. **Onboarding a new tenant** — settings, plans, routers, import, pilot checklist.

Item 5 sounds unlikely. It is the single most common serious incident in this category of software, and having the procedure written before it happens is the difference between a bad hour and a bad week.

---

## 7. Things that will bite you (learn them here, not in production)

1. **MikroTik CoA/Disconnect listens on UDP 1700**, not the RFC-default 3799. Wrong port = silent failure with no error.
2. **RouterOS REST returns everything as strings**, including `"true"`/`"false"`. A truthy check on `"false"` passes.
3. **Stale PPPoE sessions after a NAS reboot** leave open `radacct` rows that break `Simultaneous-Use` and lock customers out. Handle `Accounting-On/Off` and stale-detection from day one.
4. **`.id` values in RouterOS change** after a configuration restore. Always be able to re-find objects by the `svc:<uuid>` comment.
5. **Timezone vs UTC on the daily suspension job.** A job at "00:05 UTC" suspends people at 3am local, or on the wrong day entirely. Always tenant-local.
6. **Month-end renewal drift.** Naive `addMonth()` on Jan 31 walks to Feb 28 then Mar 28, and the customer silently loses three days a year. Anchor to the original day.
7. **Float money.** One `0.1 + 0.2` in a totals calculation and reconciliation never balances again.
8. **Sequential IDs in URLs** let anyone enumerate your subscriber base. UUIDs in routes, always.
9. **`unique` validation ignores global scopes** in several Laravel code paths. Use explicitly scoped rules.
10. **A payment must never fail because a router is down.** If the network call is inside the payment transaction, an unreachable router means the customer's cash isn't recorded. Queue it.
11. **WhatsApp template approval takes weeks** and templates cannot be freely edited afterwards. Design the message catalogue early.
12. **Arabic PDF rendering** is a real engineering problem. Test it before choosing the PDF engine, not after building all the templates.
13. **The demo/pilot tenant will hand-edit routers.** Reconciliation isn't a nice-to-have; drift is the steady state.
14. **Collectors will lose their phones.** Token revocation, remote wipe of cached data, and shift reconciliation are security controls, not conveniences.
