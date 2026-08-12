# ISP Manager

ISP Manager is the operational backbone for small internet providers and resellers: subscriber CRM, service lifecycle, billing, field collection, support and network enforcement in one tenant-aware platform.

The build handoff in [`plan1/`](plan1/00-START-HERE.md) remains the product source of truth. The application is being delivered as vertical slices from that plan, with finance and router side effects isolated behind explicit boundaries.

## Current checkpoint

The current foundation is live:

- Laravel 13 + Inertia 3 + React 19 + TypeScript + Tailwind 4
- Shared-schema tenant context with global model scoping
- Tenant, branch, zone, customer, plan and service foundations
- Encrypted service credentials and hidden serialization
- Staff login, password reset, 2FA/re-authentication, session devices, dashboard, customer/service/report screens with tenant-safe zone/status/expiry directory filters
- Capability-gated tenant settings for locale, timezone, currencies, display, notification quiet hours and automation defaults
- Capability-gated effective-dated FX rate administration using exact numerator/denominator ratios
- Owner-facing pilot readiness checklist covering tenant setup, currencies, FX freshness, branding, payments, notifications and WhatsApp
- Operator directory with role-limited one-time invitations and a public invitation acceptance flow
- Staff invoice and payment queues with allocation-backed balances, audited reversals, router health registry and network command operations screen
- Atomic subscriber registration that can create the customer, pending service, installation work order and audit event together, plus an invoice-first renewal desk with duplicate-open-invoice protection
- Dedicated service diagnostics, live RADIUS session operations and incident workspaces with tenant-safe deep links and disconnect controls
- Tenant-safe invoice detail and payment receipt views with posted allocation trails and browser print layouts
- Staff ticket conversation/status workflow, customer support history, work-order calendar/completion surface with readings, signatures and bulk materials, serialized inventory trace/assignment, and plan/add-on/promotion catalog workflow
- Supplier workspace with tenant-safe supplier contracts, operational bills/payments, supplier payable aging/settlement queue, credential batch costs, linked contract imports, reconciliation reports, secret redaction, assignment and audited reveal boundary
- Capability catalog, invitations, tenant isolation, audit events and API tokens
- Legacy `admin` accounts are reconciled to a full tenant role by the capability seeder; local web 2FA remains opt-in through `SECURITY_ENFORCE_TWO_FACTOR`, while production preflight requires it to be enabled
- Historical FX conversion, double-entry ledger, invoices, payments, cash-shift reconciliation and billing runs
- Configurable Stripe customer-portal checkout with signed, idempotent payment settlement through the existing ledger path
- Standalone PHP Whish Pay collector flow with persisted attempts, provider-verified callbacks, and QR collection links for USD/LBP/AED
- Multi-currency collection with inverse-rate lookup, payment FX snapshots, approved override reasons, references and overpayment credit
- Immediate or next-renewal service plan changes with prorated ledger credit/charge entries, pause/resume lifecycle controls, and queued RouterOS/RADIUS rate synchronization
- Payment-driven one-to-twelve-period renewal with signed previews, scheduled overdue suspension and post-commit network command outbox
- Message templates, provider delivery jobs, tickets, work orders and serialized inventory
- Idempotent English, Arabic and French notification templates provisioned for WhatsApp, SMS and email
- Idempotent customer welcome, payment receipt, expiry, suspension and reactivation notifications with channel selection, opt-out handling, provider fallback and scoped outage broadcasts
- Tenant-scoped WhatsApp Web.js accounts with independent pairing sessions, job assignment, controlled disconnect/re-pair, and explicit deletion without removing message history
- FreeRADIUS sync, live-session accounting and daily usage rollups
- Encrypted router RADIUS shared secrets, validated UDP CoA/Disconnect packets and radius driver wiring
- Router/POP inventory and onboarding, encrypted connection tests and repeated-failure incidents
- Tenant-safe POP administration, upstream-link records, and capability-gated IP pool/address inventory
- Personal customer saved views for repeatable zone, status, and expiry queues
- Browser-assisted customer GPS capture with validated coordinates, Leaflet/OpenStreetMap map views and external handoff
- Tenant-private customer document upload and download with recent-authentication, classification and retention controls
- Append-only credit notes with ledger-backed invoice balance reduction
- Scheduled RouterOS subscriber reconciliation with report-only defaults and explicit device-side healing
- Supplier credential workflows with permission, re-authentication and audit controls
- Cursor-paginated customer API, idempotent payment API, collector batches, customer portal OTP/session flow, service plan/renewal APIs, OpenAPI slice and finance reporting
- Role-scoped Sanctum abilities, app version/maintenance config, technician diagnostics, van inventory, assigned work-order evidence/signature/readings/material APIs
- Collector offline bootstrap/delta sync with signed cursors and per-payment created/replayed/rejected results
- Responsive mobile field fallback navigation plus the session-authenticated `/field` collector desk with tenant/user-scoped AES-GCM encrypted IndexedDB/localStorage customer snapshot, currency catalog, payment queue persistence, signed refresh, and confirmed device-data clearing; OS-backed native encrypted storage and an offline authenticated app shell remain separate client integrations
- Customer, plan, service and serialized-equipment CSV imports with preview, partial success and guarded rollback
- Staff import workspace with CSV/XLSX preview, row-level reports, tenant-scoped history and controlled rollback
- Basic reseller hierarchy and journal-linked wallet funding/debiting with credit limits
- Operations report with service/network/work-order/incident status, low-stock signals and CSV export
- Finance report with collection rate, allocation-based aging, plan/zone/POP revenue, supplier payable aging, upstream cost and margin, collector performance, retention, ARPU and top-usage breakdowns
- Server-generated A4 invoice and receipt PDFs with tenant-scoped billing authorization
- Owner dashboard finance metrics, six-month service-status trend, deferred loading fallbacks, and manager attention panels with actionable deep links
- Capability-aware global command palette for customers, services, billing records, tickets, equipment and incidents
- Transactional APP_KEY rotation command with an operator runbook for encrypted credentials
- Coherent Lebanon-oriented demo tenant seed with USD base currency, LBP collection, a demo FX rate, one LBP settlement against a USD invoice, 200 customers/services, billing history, routers/POPs, tickets, work orders and serialized stock
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
composer run dev
```

`composer run dev` starts the PHP server, queue listener, scheduler, log tail and Vite together.

Open [http://localhost:8000](http://localhost:8000). Demo account: `admin@example.com` / `password`.

The demo seeder also creates one account for each tenant staff role so the role-specific views can be inspected locally. Every account below uses the development-only password `password`:

| Role | Account |
| --- | --- |
| Tenant owner | `admin@example.com` |
| Operations manager | `operations.manager@example.com` |
| Billing manager | `billing.manager@example.com` |
| Cashier | `cashier@example.com` |
| Collector | `collector@example.com` |
| Support agent | `support.agent@example.com` |
| Technician | `technician@example.com` |
| Network administrator | `network.admin@example.com` |
| Reseller owner | `reseller.owner@example.com` |
| Reseller staff | `reseller.staff@example.com` |
| Auditor | `auditor@example.com` |

The tenant owner can use **Settings → Pilot readiness** or `php artisan platform:tenant-readiness northline --json` before a pilot handoff. The readiness report treats missing provider credentials and branding as explicit warnings/failures, and flags collection rates older than `FX_RATE_MAX_AGE_HOURS` (72 hours by default).

### Docker

```powershell
docker compose up --build
```

Podman can build the development image directly from the repository root because the root `Containerfile` is the canonical development image definition:

```powershell
podman build -f Containerfile -t isp-reseller:dev .
podman compose up --build
```

When using Podman Desktop's **Build** action, choose `D:\Development\ISPResellerPlatform` as the context and `Containerfile` as the build file. An empty build-file field produces a multi-stage parser error before the application is even read. Production uses the explicit multi-stage files in `docker/php/Dockerfile.production` and `docker/nginx/Dockerfile`; do not use the development `Containerfile` for that release image.

The app is at [http://localhost:8000](http://localhost:8000), Vite serves the development assets at `http://127.0.0.1:5173`, Mailpit is at [http://localhost:8025](http://localhost:8025), and the MinIO console is at [http://localhost:9001](http://localhost:9001). Keep the `frontend` service running while using the local app; it owns the Vite dev server and HMR endpoint.

On a fresh Docker database, run the demo fixture once after the stack is healthy:

```powershell
docker compose exec app php artisan db:seed --force
```

The development app reconciles the capability catalog and existing tenant role assignments on every app start, so permission changes do not leave seeded staff accounts returning 403 responses.

The development app keeps Composer dependencies and framework cache in named volumes, clears the Laravel configuration cache on each app start so `.env` and PHPUnit overrides remain effective, runs the Docker PHP server with four CLI workers and OPcache timestamp validation disabled for faster bind-mounted requests, and keeps the frontend dependency install behind a lockfile hash. Restart the app after changing PHP source, `.env` or PHP configuration; set `PHP_OPCACHE_VALIDATE_TIMESTAMPS=1` when live code revalidation is more important than request speed. After a frontend build that changes an Inertia page or deferred prop shape, restart the app, worker and scheduler processes together so long-lived PHP processes cannot return an older payload contract to the new JavaScript bundle. Set `PHP_CLI_SERVER_WORKERS=1` when debugging process-specific behavior. Rebuild the app image after changing Docker PHP settings with `docker compose build app && docker compose up -d app`.

WhatsApp Web.js is opt-in and runs as a private Compose service. Set `WHATSAPP_PROVIDER=web`, `WHATSAPP_WEB_ENABLED=true`, `WHATSAPP_WEB_TOKEN`, `WHATSAPP_WEBHOOK_SECRET`, and `WHATSAPP_WEBHOOK_URL` in `.env`, then start it with `docker compose --profile whatsapp up --build`. The bridge is not published to the host; after it starts, the owner can open **Settings → WhatsApp setup**, add separate accounts for billing, collections, support or operations, and scan each account's QR code. Accounts can be disconnected for re-pairing or deleted when retired; the last configured account is intentionally protected. Keep the `whatsapp-web-auth` volume private and backed up. Use the Cloud API path instead when a dedicated Web.js account is not approved for the pilot.

For the production-shaped PHP-FPM/Nginx topology, use the release procedure in [the deployment runbook](docs/runbooks/deployment.md) with `docker-compose.production.yml`.

## Quality gates

```powershell
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress
composer test
npm run format:check
npm run typecheck
npm run build
npm run test:integrations
```

For browser acceptance, install Chromium once with `npx playwright install chromium`, then run `npm run e2e` against the local seeded application.

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
