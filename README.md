# NexaISP

NexaISP is a tenant-aware operations platform for internet providers and resellers. It brings subscriber CRM, service lifecycle, billing, collections, field work, support, inventory, and network operations into one focused workspace.

## What is included

- Shared-schema multi-tenancy with tenant-scoped Eloquent models and permission teams.
- A platform control plane for a `super_admin` or legacy `platform_operator`.
- One-step tenant provisioning: workspace, owner account, currencies, sequences, templates, and operational defaults.
- Tenant branding with logo upload, staff-shell branding, portal branding, and PDF logo support.
- Role-based staff access, invitations, two-factor authentication, recent-authentication gates, and audit events.
- Customer, service, plan, invoice, payment, cash shift, ticket, work-order, inventory, supplier, partner, router, POP, RADIUS, and WhatsApp workflows.
- Inertia 3, React 19, TypeScript, Tailwind CSS 4, PostgreSQL, Redis, Reverb, and Pest 4.

## Tenant model

NexaISP uses one database with an explicit `tenant_id` context. Tenant-owned models are globally scoped, and Spatie Permission teams keep roles and permissions inside the active tenant.

```text
super_admin / platform_operator
        └── platform control plane
              ├── Tenant A ── tenant_owner ── staff roles
              ├── Tenant B ── tenant_owner ── staff roles
              └── Tenant C ── tenant_owner ── staff roles
```

Platform administrators can list, create, suspend, and archive workspaces. They do not enter tenant customer or financial data; tenant access remains an explicit, separately implemented break-glass workflow.

## Create a tenant

1. Sign in as `superadmin@example.com` or `platform@example.com`.
2. Open **Platform → Tenants** at `/admin/tenants`.
3. Enter the workspace name, unique slug, locale, timezone, base currency, and collection currency.
4. Enter the first tenant owner's name, email, and temporary password.
5. Submit **Create workspace**.

The transaction creates the tenant and owner, assigns the `tenant_owner` role, provisions currencies/sequences/templates, and records a platform audit event. The owner should rotate the temporary password, configure branding and integrations, and complete **Settings → Pilot readiness** before handoff.

### Roles

| Scope | Role | Purpose |
| --- | --- | --- |
| Platform | `super_admin` | Full platform authorization and tenant control plane access. |
| Platform | `platform_operator` | Backward-compatible platform operator role. |
| Tenant | `tenant_owner` | Full access inside one tenant workspace. |
| Tenant | `operations_manager`, `billing_manager`, `cashier`, `collector`, `support_agent`, `technician`, `network_administrator`, `reseller_owner`, `reseller_staff`, `auditor` | Capability-limited staff roles. |

Authorization checks use capabilities (`$user->can('customers.view')`) rather than hard-coded role checks. `super_admin` is intercepted by Laravel's `Gate::before` hook and can authorize every capability while remaining outside tenant context.

## Branding

The default platform identity is **NexaISP** with the connected-node mark in [`public/brand/nexa-isp.svg`](public/brand/nexa-isp.svg). Change `APP_NAME` in `.env` for the platform name; the Inertia shell, auth pages, document title, and favicon read it from Laravel configuration.

Each tenant can upload a JPG, PNG, or WebP logo (maximum 2 MB) from **Settings → Workspace settings**. The same tenant logo is used in the staff shell, customer portal, and generated payment receipts. Logo files are stored on the configured filesystem under a tenant-specific path.

## Local development

Requirements: PHP 8.3+, Composer 2, Node 22+, npm 10+, and SQLite for the fastest local loop. Docker is recommended for PostgreSQL, Redis, Mailpit, and MinIO.

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm ci
composer run dev
```

Open [http://localhost:8000](http://localhost:8000).

Development accounts use the password `password` after seeding:

| Account | Role |
| --- | --- |
| `superadmin@example.com` | Super admin |
| `platform@example.com` | Platform operator |
| `admin@example.com` | Northline tenant owner |
| `operations.manager@example.com` | Operations manager |
| `billing.manager@example.com` | Billing manager |
| `cashier@example.com` | Cashier |
| `collector@example.com` | Collector |
| `support.agent@example.com` | Support agent |
| `technician@example.com` | Technician |
| `network.admin@example.com` | Network administrator |
| `reseller.owner@example.com` | Reseller owner |
| `reseller.staff@example.com` | Reseller staff |
| `auditor@example.com` | Auditor |

Never use these credentials outside local development.

### Optional WhatsApp bridge

```powershell
$env:PUPPETEER_EXECUTABLE_PATH = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
npm run dev:whatsapp
```

Enable the local bridge values in `.env` first. The bridge is optional and should stay private.

## Quality checks

```powershell
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --no-progress
npm run format:check
npm run typecheck
npm run build
```

For browser acceptance, install Chromium once with `npx playwright install chromium`, then run `npm run e2e`.

## Architecture

The application is a modular monolith:

- `app/Actions` owns one transactional operation per class.
- `app/Models` contains tenant-aware persistence and relationships.
- `app/Support/Tenancy.php` owns the request/job tenant context.
- `resources/js/pages` contains Inertia React screens.
- `database/migrations` contains explicit, indexed schema changes.
- `tests/Feature` and `tests/Unit` contain the Pest test suite.

Money is stored as integer minor units with ISO currencies. Sensitive provider credentials and service secrets use encrypted casts, hidden serialization, authorization, recent authentication, and audit boundaries.

## Repository map

| Path | Purpose |
| --- | --- |
| `app/Actions` | Domain operations |
| `app/Models` | Eloquent models |
| `app/Support` | Tenancy, provisioning, integrations |
| `resources/js` | Inertia pages and UI |
| `database/migrations` | Database schema |
| `tests` | Pest tests and browser acceptance |
| `docs` | Runbooks and architecture notes |

## Deployment notes

Use the production procedure in [`docs/runbooks/deployment.md`](docs/runbooks/deployment.md). Set a public `APP_URL`, keep PostgreSQL/Redis/MinIO private, enable production two-factor enforcement, and run the platform preflight before accepting tenant traffic.
