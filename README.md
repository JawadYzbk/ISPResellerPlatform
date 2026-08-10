# ISP Manager

ISP Manager is the operational backbone for small internet providers and resellers: subscriber CRM, service lifecycle, billing, field collection, support and network enforcement in one tenant-aware platform.

The build handoff in [`plan1/`](plan1/00-START-HERE.md) remains the product source of truth. The application is being delivered as vertical slices from that plan, with finance and router side effects isolated behind explicit boundaries.

## Current checkpoint

The current foundation is live:

- Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4
- Shared-schema tenant context with global model scoping
- Tenant, branch, zone, customer, plan and service foundations
- Encrypted service credentials and hidden serialization
- Staff login, 2FA/re-authentication, session devices, dashboard, customer/service/report screens
- Capability catalog, invitations, tenant isolation, audit events and API tokens
- Historical FX conversion, double-entry ledger, invoices, payments, cash shifts and billing runs
- Payment-driven renewal, scheduled overdue suspension and post-commit network command outbox
- Message templates, provider delivery jobs, tickets, work orders and serialized inventory
- FreeRADIUS sync, live-session accounting and daily usage rollups
- Encrypted router RADIUS shared secrets, validated UDP CoA/Disconnect packets and radius driver wiring
- Router/POP inventory, encrypted connection tests and repeated-failure incidents
- Supplier credential workflows with permission, re-authentication and audit controls
- Cursor-paginated customer API, idempotent payment API, collector batches, customer portal OTP/session flow, OpenAPI slice and finance reporting
- Role-scoped Sanctum abilities, app version/maintenance config, technician diagnostics, van inventory and private media upload APIs
- Collector offline bootstrap/delta sync with signed cursors and per-payment created/replayed/rejected results
- Customer, plan, service and serialized-equipment CSV imports with preview, partial success and guarded rollback
- Basic reseller hierarchy and journal-linked wallet funding/debiting with credit limits
- Operations report with service/network/work-order/incident status, low-stock signals and CSV export
- Finance report with collection rate, allocation-based aging, plan/zone revenue, ARPU and top-usage breakdowns
- Transactional APP_KEY rotation command with an operator runbook for encrypted credentials
- Demo tenant seed with customers, plans and services
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
