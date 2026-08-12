# 03 — Architecture

## 1. Stack and versions (verified current as of August 2026)

### Backend
| Component | Version | Notes |
|---|---|---|
| PHP | **8.3+** | Laravel 13's minimum. 8.4 is fine. |
| Laravel | **13.x** | Released 17 Mar 2026 with no breaking changes from 12; bug fixes to Q3 2027, security to Q1 2028. Laravel 12's bug-fix window closes 13 Aug 2026 — do not start on 12. |
| PostgreSQL | **17+** | Primary datastore (merge decision, `00 §5`). Chosen for `jsonb` custom fields, partial unique indexes ("one active X"), trigram/full-text search, `CHECK` constraints on the journal, and native time-partitioning of `radacct`/observations/audit. FreeRADIUS ships a stock PostgreSQL schema. |
| Redis | 7.x | cache, queues, locks, rate limiting |
| FreeRADIUS | 3.2.x | Phase 8 only |
| Node | 22 LTS | build only |

### Frontend
| Component | Version | Notes |
|---|---|---|
| Inertia.js | **v3** | Released Mar 2026. Axios removed (built-in XHR client), ESM-only, ships `@inertiajs/vite` for automatic page resolution + SSR, adds `useHttp`, optimistic updates, layout props, instant visits. |
| React | 19.x | |
| TypeScript | 5.x, `strict: true` | |
| Vite | **7+** | Inertia v3 requires Vite 7; Vite 6 is unsupported. |
| Tailwind CSS | 4.x | |
| shadcn/ui | latest | Radix primitives, copied into the repo — not a dependency to fight |
| TanStack Table | v8 | for the data grid; server-driven, not client-side filtering |
| Recharts | latest | dashboards |
| react-hook-form + zod | latest | complex forms; `useForm` from Inertia for simple ones |

### Inertia v3 specifics the agent must get right
- **Use `Inertia::optional()`, not `Inertia::lazy()`** — `lazy()` and `LazyProp` were removed in v3.
- Republish the Inertia config after install: `php artisan vendor:publish --provider="Inertia\ServiceProvider" --force` — the config file was restructured in v3.
- Install `@inertiajs/vite` and let it handle page resolution, code splitting and SSR setup. `createInertiaApp()` can be called with zero arguments.
- SSR runs in dev without a separate Node process. Enable SSR for the **public/marketing and customer portal** routes only; the staff app doesn't need it.
- Use `Inertia::defer()` + `<Deferred>` for slow dashboard panels (revenue charts, session lists) so the page shell paints immediately.
- Use `useHttp` for non-navigational requests (typeahead search, "test router connection", live session refresh) instead of pulling in a separate HTTP client.
- Use `.optimistic()` for toggles (activate/deactivate flags, ticket read state) — not for money or network actions, where you want the truth.
- Enable **history encryption** (`Inertia::encryptHistory()`) on all authenticated routes: this app has customer PII and staff share machines.

---

## 2. Composer packages

**Core**
- `laravel/framework:^13`
- `inertiajs/inertia-laravel:^3`
- `laravel/sanctum` — web session guard + mobile bearer tokens with abilities
- `laravel/horizon` — queue dashboard and supervision
- `laravel/scout` (optional, Phase 9) — only if DB fulltext search proves insufficient

**Domain**
- `brick/money` — money arithmetic and allocation. **Do not hand-roll.**
- `spatie/laravel-permission` — roles/permissions in **teams mode**, `team_id = tenant_id`
- `spatie/laravel-activitylog` — audit trail
- `spatie/laravel-medialibrary` — documents, photos, signatures
- `spatie/laravel-query-builder` — safe, declarative filtering/sorting/includes for index endpoints (web and API share it)
- `spatie/laravel-data` — typed DTOs, doubles as the Inertia prop and API resource layer
- `spatie/laravel-backup` — scheduled DB + media backups
- `spatie/laravel-schedule-monitor` — alerts when a scheduled job silently stops running. Critical here: a suspension job that quietly dies costs real money.
- `propaganistas/laravel-phone` — phone normalisation and validation per country
- `barryvdh/laravel-dompdf` or `spatie/laravel-pdf` (Browsershot) — invoices/receipts. Prefer `laravel-dompdf` for a headless server without Chromium; use Browsershot if you need real CSS/RTL fidelity for Arabic invoices. **Test Arabic rendering before committing to one.**
- `dedoc/scramble` — generates OpenAPI 3.1 from the code, no annotations to rot
- `laravel/pulse` (optional) — app performance monitoring

**Network**
- `evilfreelancer/routeros-api-php` — legacy binary API (8728/8729), useful fallback
- RouterOS **REST API** (`/rest`, RouterOS ≥ 7.1) via Laravel's `Http` client — this is the primary path. `www-ssl` service must be enabled on the router; never use plain `www`/HTTP in production, credentials go over Basic Auth.
- No RADIUS PHP client needed — the app writes to the RADIUS SQL tables directly and sends CoA/Disconnect packets via a small UDP writer (or shells out to `radclient`). Write our own thin `CoaClient`; the packet format is trivial and the dependency isn't worth it.

**Dev**
- `pestphp/pest:^4` + `pest-plugin-laravel`
- `larastan/larastan` — level 6 initially, raise to 8 by Phase 5
- `laravel/pint`
- `barryvdh/laravel-ide-helper`
- `nunomaduro/collision`

**Deliberately not used**
- Filament / Nova — the UI is Inertia+React by requirement; an admin panel package would fork the design system.
- `stancl/tenancy` — its single-database mode is a good pattern to copy, but the full package's connection-switching machinery is overhead for a single-DB design, and cross-tenant super-admin queries fight it. Revisit only if DB-per-tenant becomes a requirement (it supports Sanctum and single-DB scoping if so).

---

## 3. Folder structure

```
app/
├── Actions/                        one public method, one job, named as a verb
│   ├── Billing/{RecordPayment,GenerateInvoice,AllocatePayment,IssueCreditNote,...}.php
│   ├── Customers/{RegisterCustomer,AnonymizeCustomer,...}.php
│   ├── Services/{CreateService,ActivateService,SuspendService,ResumeService,ChangePlan,RenewService,TerminateService}.php
│   ├── Network/{ProvisionService,EnforceServiceState,DisconnectSession,SyncRouter,TestRouterConnection}.php
│   ├── Inventory/{AssignDevice,ReturnDevice,TransferStock}.php
│   └── Support/{OpenTicket,AssignWorkOrder,CompleteWorkOrder}.php
├── Data/                           spatie/laravel-data DTOs (props + API resources + request payloads)
│   ├── Customer/{CustomerData,CustomerSummaryData}.php
│   ├── Service/{ServiceData,ServiceStateData}.php
│   └── ...
├── Domain/
│   ├── Money/{Money.php,MoneyCast.php,FxConverter.php}
│   ├── Billing/{Ledger.php,BillingPeriod.php,Proration.php,DocumentNumberer.php}
│   └── Network/
│       ├── Contracts/NetworkDriver.php
│       ├── Drivers/{MikrotikApiDriver.php,RadiusDriver.php,NullDriver.php,FakeDriver.php}
│       ├── DriverManager.php
│       ├── Dto/{ProvisionRequest.php,RateLimit.php,SessionInfo.php,DeviceHealth.php}
│       └── Exceptions/{DriverConnectionException.php,DriverAuthException.php,...}
├── Enums/                          backed enums: ServiceStatus, NetworkState, PaymentMethod, ...
├── Events/                         ServiceActivated, PaymentRecorded, ServiceSuspended, QuotaExceeded, ...
├── Http/
│   ├── Controllers/
│   │   ├── Web/                    Inertia controllers (thin)
│   │   ├── Api/V1/                 mobile + portal API controllers (thin)
│   │   └── Portal/                 customer self-service (Inertia)
│   ├── Middleware/{IdentifyTenant,EnsureTenantAccess,SetLocale,IdempotencyKey}.php
│   ├── Requests/                   FormRequests
│   └── Resources/                  only where Data DTOs don't fit
├── Jobs/
│   ├── Network/{ExecuteNetworkCommand,PollRouterSessions,CheckRouterHealth,ReconcileServiceState}.php
│   ├── Billing/{RunBillingCycle,SuspendOverdueServices,GenerateInvoices,SendExpiryReminders}.php
│   └── Usage/{RollupDailyUsage,EnforceQuota,PruneMetrics}.php
├── Models/
├── Observers/
├── Policies/
├── Providers/
├── Services/                       stateful/integration services (SmsGateway, WhatsAppGateway, PaymentGateway)
└── Support/{Tenancy/,Concerns/}
resources/
├── js/
│   ├── app.tsx                     createInertiaApp() — near-empty thanks to @inertiajs/vite
│   ├── ssr.tsx
│   ├── layouts/{AppLayout,AuthLayout,PortalLayout,FieldLayout}.tsx
│   ├── pages/                      mirrors the route tree — see 05
│   ├── components/{ui/, domain/, charts/, forms/}
│   ├── hooks/
│   ├── lib/{money.ts,date.ts,api.ts,permissions.ts,rtl.ts}
│   └── types/{generated.d.ts (from Data DTOs), inertia.d.ts}
├── lang/{en,ar,fr}/
└── views/{app.blade.php, pdf/{invoice,receipt}.blade.php}
routes/{web.php, api.php, portal.php, console.php, channels.php}
database/{migrations/, factories/, seeders/}
tests/{Feature/, Unit/, Architecture/}
docker/
```

**Rule of thumb for placement:** if it has side effects and is triggered by a user intent → `Actions/`. If it's pure computation → `Domain/`. If it talks to something external → `Services/` or `Domain/Network/Drivers/`. If it's deferred → `Jobs/`.

### 3a. Modular-monolith boundaries

Do **not** start as microservices, event sourcing, or with a generic repository layer. This is a **modular monolith**: the folders above are grouped into domain modules, and the boundaries between them are enforced by discipline + architecture tests, not by network calls.

```text
Identity · Tenancy · CRM · Catalog · Subscription · Billing ·
Partners · Suppliers · Network · Inventory · Support · FieldService ·
Communications · Reporting · Audit
```

Module rules (add each as a Pest architecture test where expressible):

- Cross-module effects happen through **application Actions and domain events**, not through model observers with hidden behavior.
- Models never call routers, gateways, or messaging APIs.
- No module reads another module's private tables via ad-hoc raw SQL — expose a public query/service contract.
- Financial posting and provisioning orchestration are explicit services with integration tests.
- Interfaces are required at **external adapter boundaries** (network drivers, payment/message gateways, secret store); do not add a premature interface for every class.
- Web Inertia controllers and `/api/v1` controllers call the **same Actions**; neither calls the other over HTTP.

---

## 4. Multi-tenancy implementation

Single database, `tenant_id` column, global scope. Concretely:

```php
// app/Support/Tenancy/BelongsToTenant.php
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());
        static::creating(function (Model $model) {
            if (! $model->tenant_id && Tenancy::check()) {
                $model->tenant_id = Tenancy::id();
            }
        });
    }
}
```

- `Tenancy` is a scoped singleton holding the current tenant. Set by `IdentifyTenant` middleware from the authenticated user's `tenant_id` (staff), the customer's `tenant_id` (portal), or the API token's tenant claim.
- **Super-admin cross-tenant access** is explicit: `Tenancy::withoutScope(fn () => ...)` inside dedicated admin controllers, never by default. Add a `TenantScope` bypass audit log entry each time it's used.
- **Queued jobs must carry the tenant.** Every job implements a `TenantAware` interface: the tenant ID is serialised into the job payload and re-established in the job's `handle()` via a middleware. A job that runs without a tenant context and touches tenant data must throw, not silently read everything.
- **Nested relations are the classic leak.** A `Comment` that belongs to a `Ticket` that belongs to a tenant is not automatically scoped. Two defences: (a) put `tenant_id` on *every* tenant table, including children, so the global scope always applies; (b) an architecture test asserting every model in `App\Models` (minus a small allowlist) uses the trait.
- **Validation rules** (`unique`, `exists`) do **not** respect global scopes reliably in all Laravel paths. Use explicit scoped rules: `Rule::unique('services','username')->where('tenant_id', Tenancy::id())`.

---

## 5. The network control layer

This is the part that distinguishes this product from a generic billing CRUD app. Get it right.

### 5.0 Provisioning modes → drivers

A service's `provisioning_mode` (`02 §7`) selects which `NetworkDriver` executes a command:

| Mode | Driver | Notes |
|---|---|---|
| `manual` | `ManualDriver` | Records the intended action as a `network_command` in `pending`; an authorized human confirms it. No device call. |
| `upstream_credential` | `CredentialDriver` | Reserves/assigns a credential from tenant inventory (`08`); "activation" = assignment, "suspension" = release/flag. Repository delivery is complete; external supplier provisioning and RouterOS/CoA acceptance remain environment gates. |
| `mikrotik` | `MikrotikApiDriver` (`§5.2`) | RouterOS v7 REST. |
| `radius` | `RadiusDriver` (`§5.3`) | Writes RADIUS tables + CoA/Disconnect. |
| `external` | `ExternalDriver` | Calls a third-party OSS/UISP/ACS webhook/API; local service stays the commercial truth. |

Every driver implements the same interface so the command pipeline (`§5.4`) is mode-agnostic. `ManualDriver`/`CredentialDriver` don't need a `Router`; the pipeline resolves the driver from the service's mode, not only from `router.driver`.

### 5.1 The interface

```php
interface NetworkDriver
{
    public function testConnection(Router $router): DeviceHealth;

    /** create or update the subscriber on the device */
    public function provision(Service $service, ProvisionRequest $request): DriverResult;

    /** allow traffic at the plan rate */
    public function enable(Service $service): DriverResult;

    /** block or redirect to a walled garden, keeping the account */
    public function disable(Service $service, SuspensionReason $reason): DriverResult;

    /** change rate limit without dropping the session where supported */
    public function updateRate(Service $service, RateLimit $rate): DriverResult;

    /** kill the active session */
    public function disconnect(Service $service): DriverResult;

    /** remove the subscriber entirely */
    public function deprovision(Service $service): DriverResult;

    /** @return Collection<SessionInfo> */
    public function activeSessions(Router $router): Collection;

    /** everything the device knows about our subscribers, for reconciliation */
    public function inventorySubscribers(Router $router): Collection;
}
```

`DriverResult` carries `success`, `raw` (redacted), `deviceReference` (e.g. the RouterOS `.id`), and `retryable`.

### 5.2 Driver A — `MikrotikApiDriver` (build first)

Talks to RouterOS v7 REST API at `https://<host>/rest` with HTTP Basic auth. Requires the `www-ssl` service enabled on the router.

Mapping:

| Operation | RouterOS call |
|---|---|
| provision (PPPoE) | `PUT /rest/ppp/secret` `{name, password, service:"pppoe", profile:<plan profile>, comment:"svc:<uuid>"}` |
| enable | `PATCH /rest/ppp/secret/<id>` `{disabled:"false", profile:<plan profile>}` |
| disable (block) | `PATCH /rest/ppp/secret/<id>` `{profile:<blocked profile>}` then disconnect — keeps the account so a walled-garden page can be served |
| disable (hard) | `PATCH /rest/ppp/secret/<id>` `{disabled:"true"}` then disconnect |
| updateRate | change `profile`, or for per-user overrides `PATCH /rest/queue/simple/<id>` `{max-limit:"<up>/<down>"}` |
| disconnect | `GET /rest/ppp/active?name=<user>` → `DELETE /rest/ppp/active/<id>` |
| deprovision | `DELETE /rest/ppp/secret/<id>` |
| activeSessions | `GET /rest/ppp/active` |
| health | `GET /rest/system/resource` + `/rest/system/identity` |
| static/DHCP customers | `/rest/ip/firewall/address-list` add to `blocked` list + `/rest/queue/simple` for rate |

Implementation notes:
- Set `comment: "svc:<service_uuid>"` on every object we create. This is how reconciliation finds our objects among hand-made ones. **Never match by username alone.**
- RouterOS returns all values as **strings**, including booleans (`"true"`/`"false"`) and numbers. Cast defensively.
- Persist the RouterOS `.id` on the service (`meta.routeros_id`) but always be able to re-find by comment, because `.id` changes on config restore.
- Timeouts: 5s connect, 10s read. Retries: 3 with exponential backoff (10s, 60s, 300s). After that, `abandoned` + alert.
- Plan → profile: create one PPP profile per plan on each router, named `plan-<code>`, provisioned by a `SyncRouterProfiles` job. Don't set per-user rate limits when a profile will do — it's far fewer API calls.

### 5.3 Driver B — `RadiusDriver` (Phase 8)

The app owns the RADIUS SQL tables; FreeRADIUS reads them.

- **Authorisation:** each service gets a `radcheck` row (`Cleartext-Password := <pw>`) and a `radusergroup` row pointing at a group named after the plan. `radgroupreply` holds `Mikrotik-Rate-Limit := "<up>/<down>"`, `Framed-Pool`, `Acct-Interim-Interval := 300`, etc.
- **Suspension:** either move the user's `radusergroup` to a `blocked` group (which replies with a walled-garden pool and a throttled rate), or add `Auth-Type := Reject` to `radcheck`. Prefer the group swap — a rejected user's router keeps retrying and generates noise, while a walled-garden session lets you show a "please pay" page.
- **Live enforcement:** after any change, send **CoA** (to apply a new rate to the live session) or **Disconnect-Request** (to force re-auth). **MikroTik listens for CoA/PoD on UDP 1700**, not the RFC-default 3799 — this is a common and expensive mistake. The port is per-router (`routers.coa_port`).
- **Accounting:** FreeRADIUS writes `radacct`. Interim-Update interval of 300s gives near-live usage. The nightly `RollupDailyUsage` job aggregates into `usage_daily`; `sessions_current` is upserted from interim updates.
- **Stale sessions** are the #1 operational complaint in PPPoE+RADIUS deployments: a router reboot leaves `radacct` rows with no `acctstoptime`, which then breaks `Simultaneous-Use` checks and locks customers out. Mitigations to build in: (a) treat a session as dead if `acctupdatetime` is older than 2× the interim interval; (b) close orphaned sessions on `Accounting-On`/`Accounting-Off` from a NAS; (c) a cleanup job that closes stale rows with `Acct-Terminate-Cause = 'Stale'`.
- `nas` table is populated from `routers`.

### 5.4 Execution pipeline

```
Action (e.g. SuspendService)   ── all inside ONE DB transaction ──
  → writes service.status + service_event
  → bumps service.desired_state_version
  → sets network_state = pending_sync
  → creates network_commands row (status=pending, idempotency_key, desired_state_version)
  → writes an outbox_events row (service.suspended)
  → COMMIT
        ↓  (after commit — never before)
  relay/after-commit hook dispatches ExecuteNetworkCommand onto queue "network:{service_id}"  [ordered per service]
       → resolves driver from service.provisioning_mode (§5.0)
       → refuses if command.desired_state_version < service.desired_state_version  (stale retry guard)
       → executes, records redacted response
       → on success: network_state = enabled|disabled, network_synced_at = now
       → on failure: retry with backoff (jitter); after max attempts → network_state = error,
                     raise alert, surface a drift badge in the UI. The commercial status and the
                     posted payment are untouched — a dead router never rolls back money.
```

**Why the outbox:** committing the finance/desired-state change *before* any external call is the single rule that keeps a payment from being lost when a router, SMS provider, or gateway is unreachable. The queue job is the retry surface; the DB is the source of truth.

`ReconcileServiceState` runs hourly per router: pull `inventorySubscribers()`, diff against the DB, and (a) report drift, (b) auto-heal only in the safe direction (re-apply our intent to the device), never the reverse. A device that disagrees with the database is never allowed to change the database.

### 5.5 Testing the network layer

`FakeDriver` records calls in memory and can be told to fail, time out, or return drifted inventory. **Every network-touching feature test uses `FakeDriver`.** A small, separately-tagged integration suite runs against a real RouterOS CHR instance in Docker (`mikrotik/chr` or a licensed CHR image) in CI nightly, not on every commit.

### 5.6 Connectivity architecture (never expose router ports publicly)

How the workers reach the routers depends on deployment. **RouterOS REST/API/SSH must never be exposed to the public internet just to make the app work.** Three modes, in order of scaling safety:

- **On-network deployment** — workers connect to allowlisted routers over a management VLAN. Simplest for a single self-hosting provider.
- **Cloud over private VPN** — the app reaches routers only through a private network / site-to-site VPN. Preferred when manageable.
- **Outbound site connector (later)** — a small agent installed at each site polls/streams **signed** commands outward over mTLS, runs only allowlisted adapter operations, and returns signed results. This is the safest pattern for routers behind NAT and the path to cloud scale. Connector builds are signed and revocable.

**SSRF is a first-class threat here** because routers are configured by tenants. Validate every management endpoint: resolve and check the IP, block loopback/link-local/metadata/unapproved-private ranges unless the tenant explicitly allowlists them, block redirects, and revalidate at connect time. RouterOS REST uses HTTP Basic, so enforce HTTPS with certificate verification and a dedicated least-privilege router account. Full detail in `09`.

---

## 6. Queues

| Queue | Purpose | Workers | Notes |
|---|---|---|---|
| `network` | device commands | 4 | ordered per service via job chaining/unique locks |
| `billing` | invoice generation, suspension runs | 2 | long-running, chunked |
| `notifications` | WhatsApp/SMS/email/push | 4 | rate-limited per provider |
| `usage` | rollups, metric pruning | 2 | off-peak |
| `default` | everything else | 4 | |

Horizon supervises all. `spatie/laravel-schedule-monitor` alerts if a scheduled job doesn't check in — a silently dead suspension job is a revenue leak that takes weeks to notice.

### Scheduled jobs
| Schedule | Job |
|---|---|
| every minute | `ProcessDueNetworkCommands` (safety net for orphaned commands) |
| every 5 min | `PollRouterSessions` (API mode) / `RefreshCurrentSessions` (RADIUS mode) |
| every 5 min | `CheckRouterHealth` |
| hourly | `ReconcileServiceState` |
| daily, tenant-configured hour | `SuspendOverdueServices` |
| daily 00:30 | `RollupDailyUsage`, then `EnforceQuota` |
| daily 01:00 | `GenerateInvoices` (postpaid tenants) |
| daily 08:00 tenant-local | `SendExpiryReminders` |
| daily 03:00 | `AssertLedgerIntegrity`, `PruneMetrics`, `CloseStaleRadiusSessions` |
| daily 04:00 | `spatie/laravel-backup` |

All scheduled jobs are **tenant-aware and chunked**, take a lock, and are idempotent — running twice must be harmless.

---

## 7. Security

- **Auth:** Sanctum. Web uses the session guard (SPA-style, same domain, CSRF via Inertia). Mobile uses personal access tokens with abilities (`staff:collector`, `staff:technician`, `customer`), short-ish expiry + refresh on use, revocable per device from a "logged-in devices" screen.
- **2FA** mandatory for `owner`, `manager`, `accountant`, `super_admin`.
- **Authorization:** Policies on every model, `spatie/laravel-permission` for the permission strings, plus zone restriction for collectors/technicians (a collector sees only their zones). Test the negative cases, not just the positive ones.
- **Encryption at rest:** router credentials, PPPoE passwords, national IDs, 2FA secrets — Laravel `encrypted` casts. Rotating `APP_KEY` requires a re-encryption command; write it in Phase 1, not after a key leak.
- **Rate limiting:** login (5/min per IP + per account), OTP (3 per 15 min per phone), API (60/min per token, 600/min per tenant), payment endpoints (stricter).
- **Idempotency middleware** on all `POST`/`PATCH` API routes that write money or network state: reads `Idempotency-Key` header, stores response in Redis for 24h keyed by `tenant:user:key:route`, replays on repeat.
- **Audit:** `activity_log` on customers, services, payments, invoices, routers, users, plans. Log the actor, the IP, and the before/after diff.
- **PII:** the anonymisation action nulls name/phone/email/national ID and media, keeps financial records with a placeholder label. Log the request and the operator.
- **Secrets:** `.env` only; no credentials in the repo, in seeders, or in fixtures. Router credentials in the DB are encrypted; consider an external vault if the deployment grows.
- **Never log** request bodies for auth, payment, or router endpoints. Configure log scrubbing explicitly.

---

## 8. Observability

- **Structured JSON logs** with `tenant_id`, `user_id`, `request_id`, `service_id` in context.
- **Sentry** (or equivalent) for exceptions, with PII scrubbing on.
- **Health endpoint** `/up` plus a deeper `/health` reporting DB, Redis, queue depth, oldest queued network command, and last successful scheduled run.
- **Business metrics dashboard** (in-app, Phase 9): active services, suspended today, reactivated today, collection rate, failed network commands, drift count. These are the numbers that tell you the system is actually working, and they belong in the product, not just in a monitoring tool.

---

## 9. Environments and deployment

- **Local:** Laravel Sail / Docker Compose — app, PostgreSQL 17, Redis, Mailpit, MinIO (S3-compatible), and a MikroTik CHR container for driver work.
- **Staging:** mirrors production, seeded with anonymised data, points at a lab router.
- **Production:** Docker Compose or a small K8s deployment. Nginx + PHP-FPM, Horizon supervised, scheduler via a dedicated container, PostgreSQL with WAL archiving / point-in-time recovery on, Redis persistent, daily off-site backups (`spatie/laravel-backup` → S3-compatible storage), and a documented restore drill. Full topology, DR targets (RPO/RTO), and observability are in `09`.

**Deployment realities for this market:** these shops often self-host on hardware with unreliable power and connectivity. Therefore: the app must survive the database being briefly unreachable without corrupting state (transactions, retries), backups must be off-site and automatic, and there must be a documented single-command restore. Write the runbook in Phase 10 and actually test the restore.

**Zero-downtime deploys:** `php artisan down --render` with a maintenance secret, migrations that are backward-compatible (add columns nullable, backfill, then constrain — never a destructive migration in one step), and Horizon terminated/restarted after deploy.
