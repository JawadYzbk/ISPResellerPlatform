# ISP Manager

ISP Manager is the operational backbone for small internet providers and resellers: subscriber CRM, service lifecycle, billing, field collection, support and network enforcement in one tenant-aware platform.

The build handoff in [`plan1/`](plan1/00-START-HERE.md) remains the product source of truth. The application is being delivered as vertical slices from that plan, with finance and router side effects isolated behind explicit boundaries.

## Current checkpoint

The current foundation is live:

- Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4
- Shared-schema tenant context with global model scoping
- Tenant, branch, zone, customer, plan and service foundations
- Encrypted service credentials and hidden serialization
- Staff login, 2FA/re-authentication, session devices, dashboard, customer/service/report screens with tenant-safe zone/status/expiry directory filters
- Capability-gated tenant settings for locale, timezone, currencies, display, notification quiet hours and automation defaults
- Capability-gated effective-dated FX rate administration using exact numerator/denominator ratios
- Operator directory with role-limited one-time invitations and a public invitation acceptance flow
- Staff invoice and payment queues with allocation-backed balances, audited reversals, router health registry and network command operations screen
- Tenant-safe invoice detail and payment receipt views with posted allocation trails and browser print layouts
- Staff ticket conversation/status workflow, customer support history, work-order completion surface, serialized inventory trace/assignment, and plan catalog/create workflow
- Supplier credential inventory view with secret redaction, tenant-safe assignment and audited reveal boundary
- Capability catalog, invitations, tenant isolation, audit events and API tokens
- Historical FX conversion, double-entry ledger, invoices, payments, cash-shift reconciliation and billing runs
- Payment-driven renewal, scheduled overdue suspension and post-commit network command outbox
- Message templates, provider delivery jobs, tickets, work orders and serialized inventory
- Idempotent customer welcome, payment receipt, expiry, suspension and reactivation notifications with channel selection, opt-out handling, provider fallback and scoped outage broadcasts
- FreeRADIUS sync, live-session accounting and daily usage rollups
- Encrypted router RADIUS shared secrets, validated UDP CoA/Disconnect packets and radius driver wiring
- Router/POP inventory and onboarding, encrypted connection tests and repeated-failure incidents
- Tenant-safe POP administration, upstream-link records, and capability-gated IP pool/address inventory
- Personal customer saved views for repeatable zone, status, and expiry queues
- Browser-assisted customer GPS capture with validated coordinates and OpenStreetMap handoff
- Append-only credit notes with ledger-backed invoice balance reduction
- Scheduled RouterOS subscriber reconciliation with report-only defaults and explicit device-side healing
- Supplier credential workflows with permission, re-authentication and audit controls
- Cursor-paginated customer API, idempotent payment API, collector batches, customer portal OTP/session flow, OpenAPI slice and finance reporting
- Role-scoped Sanctum abilities, app version/maintenance config, technician diagnostics, van inventory and assigned work-order evidence media APIs
- Collector offline bootstrap/delta sync with signed cursors and per-payment created/replayed/rejected results
- Customer, plan, service and serialized-equipment CSV imports with preview, partial success and guarded rollback
- Staff import workspace with CSV/XLSX preview, row-level reports, tenant-scoped history and controlled rollback
- Basic reseller hierarchy and journal-linked wallet funding/debiting with credit limits
- Operations report with service/network/work-order/incident status, low-stock signals and CSV export
- Finance report with collection rate, allocation-based aging, plan/zone/POP revenue, upstream cost and margin, collector performance, retention, ARPU and top-usage breakdowns
- Deferred owner dashboard metrics and manager attention panels with server-rendered loading fallbacks
- Transactional APP_KEY rotation command with an operator runbook for encrypted credentials
- Coherent demo tenant seed with 200 customers/services, billing history, routers/POPs, tickets, work orders and serialized stock
- Money value object backed by `brick/money`
- Pest tests for money arithmetic, authentication and tenant isolation
- Docker Compose services for PHP, PostgreSQL 17, Redis 7, Mailpit and MinIO

## Local setup

Requirements: PHP 8.3+, Composer 2, Node 22+, npm 10+, and SQLite for the fastest local loop. Docker is recommended when you want the production-shaped PostgreSQL/Redis topology.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm ci
npm run dev
```

In another terminal:

```powershell
php artisan serve
```

Open [http://localhost:8000](http://localhost:8000). Demo account: `admin@example.com` / `password`.

### Docker

```powershell
docker compose up --build
```

The app is at [http://localhost:8000](http://localhost:8000), Mailpit at [http://localhost:8025](http://localhost:8025), and the MinIO console at [http://localhost:9001](http://localhost:9001).

## Quality gates

```powershell
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
php artisan test
npm run typecheck
npm run build
```

## Architecture

The code is a modular monolith. Controllers validate and delegate to one Action. Actions own domain transactions. Tenant-owned models use `BelongsToTenant`, which adds a global scope and stamps writes from the explicit `Tenancy` context. Provisioning side effects use the outbox and queue; an explicit operator-triggered router connection test is the one bounded synchronous diagnostic and has a five-second timeout.

Money is integer minor units plus ISO currency. Payments and journal entries will be append-only. Service commercial state and network state are separate fields so a paid customer can remain visibly drifted while a retry is pending.

Secrets belong in encrypted casts, hidden model attributes and redacted logs. Any future reveal endpoint must be separately authorized, re-authenticated and audited.

## Repository map

| Path | Purpose |
|---|---|
| `app/Actions` | Thin orchestration units with one public `handle()` method |
| `app/Models` | Tenant-aware persistence models and relationships |
| `app/Support` | Tenancy and money primitives |
| `resources/js` | Inertia pages, layouts and domain UI components |
| `database/migrations` | Explicit schema changes with tenant indexes |
| `docs/` | ADRs, glossary, threat model and implementation status |
| `plan1/` | Product and architecture handoff pack |

## Delivery discipline

Commits are intentionally small and Conventional Commit formatted. Ticket-sized work should update the relevant handoff or ADR in the same commit when a decision changes. Do not add a paid dependency, alter ledger semantics or expose a router control plane without an explicit product decision.
