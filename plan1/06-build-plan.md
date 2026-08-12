# 06 — Build Plan

This is the agent's task queue. Work top to bottom. One ticket = one branch = one reviewable change set. Do not start a phase before the previous phase's tickets all meet their acceptance criteria.

Estimates assume a competent agent with human review at phase boundaries. `[H]` marks a ticket where a human decision or credential is required before it can complete.

---

## Phase 0 — Foundation (≈3 days)

### ISP-001 · Project scaffolding
Laravel 13 + Inertia v3 + React 19 + TS + Tailwind 4 + shadcn/ui (official Laravel React starter kit). Docker Compose (app, PostgreSQL 17, Redis 7, Mailpit, MinIO). Pint, Larastan (level 6), Pest 4, ESLint, Prettier, `tsc --noEmit`. GitHub Actions running all of it.
**AC:** `docker compose up` gives a working app at localhost; all quality gates pass on an empty project; `README.md` documents setup in under 10 commands.

### ISP-002 · Base conventions
Backed enums scaffold, `Money` value object over `brick/money` with an Eloquent cast, `BelongsToTenant` trait + `TenantScope` + `Tenancy` singleton, `TenantAware` job middleware, base `Action` convention, RFC 9457 exception renderer, structured JSON logging with request/tenant context, log scrubbing config.
**AC:** unit tests for `Money` (arithmetic, allocation, 0/2/3-decimal currencies, rounding); a test proving a `TenantAware` job restores tenant context; a test proving credentials are scrubbed from logs.

### ISP-003 · Architecture tests
Pest architecture suite: controllers have no business logic (no `DB::`, no model writes beyond delegating to an Action); every `App\Models` class except an allowlist uses `BelongsToTenant`; no `float` in money-named columns; no `env()` outside config; Actions are final and have one public method.
**AC:** suite passes and fails correctly when a violation is introduced (prove it with a temporary bad commit in the PR description).

---

## Phase 1 — Tenancy, identity, access (≈5 days)

### ISP-010 · Tenants and settings
`tenants`, `branches`, `zones`, `currencies`, `document_sequences` migrations + models + factories. `TenantSettings` typed DTO. Super-admin tenant CRUD.
**AC:** creating a tenant seeds default settings, a default branch, a default zone, and sequence rows.

### ISP-011 · Users, roles, capabilities
`spatie/laravel-permission` in teams mode keyed on `tenant_id`. Full **capability catalog** seeder (`01 §1a`, enumerated, not ad-hoc). The 13 seed roles are templates; policies authorize on membership + partner scope + ownership + branch/zone + status + approval limit + permission. User CRUD, invitation flow, zone assignment, re-authentication gate for sensitive capabilities.
**AC:** a test matrix asserts each capability can/cannot reach each route group (allow **and** deny); adding a permission string not in the seeder fails a test; a sensitive-capability action without a fresh re-auth is rejected.

### ISP-012 · Authentication
Sanctum session auth, login, logout, password reset, 2FA (TOTP + recovery codes) with enforcement for privileged roles, login rate limiting, session device list.
**AC:** 2FA-required roles cannot reach any authenticated route without completing 2FA; brute-force attempts lock out per IP and per account.

### ISP-013 · Tenant isolation hardening
`IdentifyTenant` middleware, scoped validation rule helpers, route-model binding scoped to tenant, super-admin `withoutScope` with audit logging.
**AC:** an isolation test suite that, for every tenant-scoped model, attempts cross-tenant read/write via route binding, `find()`, relations, and validation `exists`, and gets **404** every time. This suite is a permanent regression gate.

### ISP-014 · App shell
`AppLayout` with sidebar, topbar, breadcrumbs, notifications, flash handling, theme (light/dark), locale switcher, RTL support, `<CommandPalette>` skeleton, error pages (403/404/419/500) rendered through Inertia's v3 custom error page support.
**AC:** RTL renders correctly in Arabic; no physical-direction Tailwind classes in shared components (lint rule); history encryption enabled on authenticated routes.

### ISP-015 · Audit log
`spatie/laravel-activitylog` with `tenant_id`, actor, IP, and diffs. A reusable `<Timeline>` data source.
**AC:** every model change logged with before/after; the log itself is not editable through any route.

---

## Phase 2 — Subscribers, catalog, services (≈7 days)

### ISP-020 · Customers
Migration/model/factory per `02 §4`, phone normalisation via `propaganistas/laravel-phone` with `phone_normalized` for partial search, customer code generation from `document_sequences`, CRUD, contacts, documents (media library), GPS with `<MapPicker>`.
**AC:** duplicate phone within a tenant is rejected and surfaces the existing customer; the same phone in another tenant is allowed; searching `70123` finds `+961 70 123 456`.

### ISP-021 · Customer index and show
`<DataGrid>` built here (server-driven filter/sort/cursor, URL-synced, saved views, CSV export, bulk selection). Customer Show screen per `05 §5` minus the tabs that don't exist yet.
**AC:** 10,000 seeded customers, filtered + sorted, returns in < 300ms with correct indexes; grid state survives a page refresh via the URL.

### ISP-022 · Plans and pricing
`plans`, `plan_prices`, `addons`, `promotions`. CRUD with a live preview of the rate-limit string that will be pushed to the router.
**AC:** multi-currency prices with `effective_from`; changing a price does not alter historical invoices (test).

### ISP-023 · Services
`services`, `service_events`. Create service from customer screen. Credential generation (username policy configurable: phone-based, code-based, or manual; password random with configurable strength). Status state machine with guarded transitions.
**AC:** invalid transitions throw (e.g. `terminated → active` requires an explicit reactivation action); every transition writes a `service_event`; `(tenant_id, username)` uniqueness enforced under concurrency.

### ISP-024 · Expiry arithmetic
`BillingPeriod` domain class: monthly with month-end clamping anchored to the original day, weekly, custom days; `renewFrom(max(now, expires_at))` with the `grace_extends_period` setting; all in tenant timezone.
**AC:** table-driven tests covering Jan 31 → Feb → Mar, leap years, DST transitions, early renewal, late renewal with and without grace extension.

---

## Phase 3 — Money (≈8 days) — *the highest-risk phase; slow down here*

### ISP-030 · FX
`exchange_rates` CRUD with history, `FxConverter` resolving the rate effective at a given instant, rate-change audit.
**AC:** converting a historical amount uses the historical rate; a new rate today never changes yesterday's numbers (test).

### ISP-030A · Lebanon multi-currency rate ingestion
Add an opt-in Frankfurter rate provider for tenant-configured currency pairs, including USD/LBP collection for the Lebanon use case. Store every imported quote as an effective-dated exact ratio with provider/date metadata; keep manual operator rates available when a market or treasury rate is preferred. Conversion must expose explicit rounding policies (`half_up`, `floor`, `ceil`) and payment recording must persist the exact rate, source, effective date and rounding policy used so historical receipts never re-derive from a newer quote.
**AC:** a scheduled/import command upserts a Frankfurter quote without floating-point storage; USD/LBP is available when Frankfurter returns it; unavailable or stale quotes fail safely without deleting the last known rate; floor conversion is deterministic for positive and negative amounts; a payment receipt contains the saved rate/source/date/rounding snapshot.

### ISP-031 · Double-entry journal + ledger projection
`ledger_accounts` (seeded chart of accounts per tenant), `journal_entries` + `journal_lines` (balanced per currency, `CHECK` one-of debit/credit), append-only with an observer that throws on update/delete plus a PostgreSQL trigger. `ledger_entries` per-customer statement projection derived in the same transaction; running `balance_after` under a customer row lock; `customers.balance_amount` cache maintained in the same transaction. `AssertLedgerIntegrity` command asserting per-entry balance, receivable == customer balance == projection, and wallet cache == wallet lines.
**AC:** 100 concurrent entries for one customer produce a correct running balance and balanced journal (parallel test); every posted entry balances per currency; attempting an update/delete throws at both the model and DB layer; integrity assertion passes on the seeded dataset.

### ISP-032 · Invoices
`invoices`, `invoice_lines`, `taxes`, `credit_notes`. Gapless numbering via `document_sequences` with `FOR UPDATE`. Draft → issue → void lifecycle. Proration calculator. PDF rendering (A4) with tenant branding, in all three locales including RTL.
**AC:** 20 parallel invoice creations for one tenant yield 20 unique sequential numbers with no gaps; Arabic invoice PDF renders correct RTL text and numerals `[H — confirm PDF engine choice after seeing Arabic output]`.

### ISP-033 · Payments
`payments`, `payment_allocations`, receipt numbering, idempotency (unique key per tenant + middleware), multi-currency with FX snapshot and override + reason, allocation strategies (explicit / oldest-first / renew-active-service), over-payment → credit, reversal via a paired reversal entry (never a delete), thermal-friendly and A4 receipts.
**AC:** replaying the same `idempotency_key` returns the original payment and creates no second row; a reversal restores the balance exactly; a payment in LBP against a USD invoice settles correctly at the recorded rate.

### ISP-033B · Gateway-backed online payments
Add a configurable Stripe PaymentIntent adapter, customer-portal checkout, and signed provider webhook settlement. The provider adapter must use provider idempotency keys and store invoice/customer public identifiers as metadata; the webhook must verify the raw signed body, accept only successful payment-intent events, validate tenant/customer/invoice ownership and currency, and post through the existing `RecordPayment` action so allocation, FX snapshots, receipts and renewal side effects remain identical to staff collection.
**AC:** a configured tenant can create a portal checkout session; the portal never receives the Stripe secret; a signed success callback creates exactly one `gateway` payment and duplicate provider events create no second payment; invalid signatures, malformed events, mismatched invoices and currencies fail closed; the null driver remains the safe default.

### ISP-033A · Whish Pay Lebanon gateway
Implemented as a standalone PHP package under `packages/whish-pay-php` (PSR-4, no Node runtime and no browser-side merchant secret), based on the referenced community client’s server-side contract. The Laravel adapter provides sandbox/production configuration, channel and secret handling, timeout/error mapping, exact currency-unit conversion, and a persisted Whish external ID/payment-attempt record created before the provider request. The collector flow creates a Whish collect URL and SVG QR data URI. Success/failure callbacks parse only provider identifiers, re-query Whish for authoritative status, verify amount and currency against the stored attempt, and post through `RecordPayment` with a gateway/external ID idempotency key. LBP is stored as a whole-unit currency and the existing payment ledger retains its saved rate, source, effective date and rounding policy.
**AC:** repository gates cover QR creation, idempotency, status verification, failure/mismatch handling and duplicate callbacks. Live sandbox credentials, official endpoint/payload verification and production merchant acceptance remain deployment gates before enabling production.

### ISP-034 · Renewal on payment
`RecordPayment` action extends the service, writes ledger + events, and dispatches reactivation when the service was `auto_overdue`. Preview endpoint returning the resulting expiry before confirming.
**AC:** paying a suspended (`auto_overdue`) service extends expiry and queues reactivation; paying a `manual`-suspended service extends expiry but does **not** reactivate.

### ISP-035 · Cash shifts
`cash_shifts` open/close, declared vs system totals per currency, variance flagging, reconciliation screen, per-collector daily report.
**AC:** closing a shift with a mismatch flags a variance and requires a note; payments cannot be recorded to a closed shift (`423`).

### ISP-036 · Billing runs
`GenerateInvoices` (postpaid) and prepaid renewal reminders. Chunked, locked, idempotent, tenant-aware, resumable.
**AC:** running the job twice on the same day produces no duplicate invoices; a run over 5,000 customers completes within the budget and is safe to interrupt.

---

## Phase 4 — Network control (≈8 days) — *the differentiator*

### ISP-040 · Devices
`pops`, `routers`, `ip_pools`, `ip_addresses`, `upstream_links`. Encrypted credential storage, write-only credential fields in the UI, connection test.
**AC:** a serialisation test proves credentials never appear in any Inertia prop or API response; "test connection" returns RouterOS version/identity or a precise, categorised error.

### ISP-041 · Driver interface + fakes + mode resolution
`NetworkDriver` contract, DTOs, `DriverManager` resolving by `service.provisioning_mode` (`03 §5.0`), `NullDriver`, `ManualDriver` (records intent for human confirmation), `FakeDriver` (programmable failures, timeouts, drift), and the repository-side `CredentialDriver` for upstream inventory reservation/activation/release.
**AC:** the manager resolves the correct driver for each mode; `upstream_credential` activation allocates an available tenant-scoped credential atomically and suspension/termination releases it according to lifecycle policy; `FakeDriver` can simulate every failure mode listed in `03 §5.5`; a `manual` service's activate produces a pending command awaiting confirmation, not a device call; all subsequent network tests use `FakeDriver`.

### ISP-042 · `MikrotikApiDriver`
Full implementation per `03 §5.2` against RouterOS v7 REST. Comment-tagging with `svc:<uuid>`, string-typed response casting, timeouts, categorised exceptions, per-plan PPP profile sync, static/DHCP handling via address-list + simple queue.
**AC:** integration suite passes against a RouterOS CHR container covering provision → enable → rate change → disable → disconnect → deprovision, plus behaviour when the router is unreachable, credentials are wrong, TLS fails, and the object was deleted on the device. `[H — needs a CHR image/licence for CI]`

### ISP-043 · Command pipeline + outbox
`network_commands` + `outbox_events` tables, after-commit relay dispatching `ExecuteNetworkCommand` (never before commit), per-service ordering and exponential backoff with jitter, **stale-command guard** on `desired_state_version`, `EnforceServiceState` action, `network_state` tracking, failure alerting, retry-from-UI, provisioning-operations screen.
**AC:** external work is dispatched only after commit (test); a stale command with an older `desired_state_version` is refused; two commands dispatched for one service execute in order; a failing command retries 3 times then marks `abandoned`, raises an alert, shows a drift badge, and leaves the posted payment/commercial status untouched; a UI retry succeeds without duplicating side effects.

### ISP-044 · Auto-suspend / auto-resume
`SuspendOverdueServices` scheduled per tenant at the configured hour, chunked, locked. Suspension precedence rules. Reactivation path from `RecordPayment`. `spatie/laravel-schedule-monitor` alerting.
**AC:** 5,000 overdue services suspend in under 2 minutes; the job is safe to run twice; payment → network command acknowledged in under 60s end to end in the integration environment; a missed scheduled run raises an alert.

### ISP-045 · Reconciliation
`ReconcileServiceState` hourly: diff device inventory against DB, report drift, auto-heal only device-ward. Drift dashboard.
**AC:** a service manually disabled on the device is detected within the hour, flagged, and re-enabled to match our intent; a service that exists on the device but not in the DB is reported and never auto-deleted.

---

## Phase 5 — Communications (≈4 days)

### ISP-050 · Template engine
`message_templates` per key/channel/locale with variable substitution, preview with sample data, per-tenant overrides of defaults.
**AC:** rendering a template with a missing variable fails loudly in preview and falls back safely in production.

### ISP-051 · Channels
Driver-based gateway layer: WhatsApp Business Cloud API, a pluggable SMS gateway, email, FCM push. `messages` outbound log with delivery status via provider callbacks. Per-provider rate limiting and cost tracking. `[H — needs Meta Business account + approved templates; ~2 weeks lead time. Start this procurement at the beginning of Phase 2, not here.]`
**AC:** send/queue/deliver/fail states tracked; a provider outage degrades to the next configured channel rather than losing the message; sends are idempotent per `(customer, template, period)`.

### ISP-051A · WhatsApp Web.js bridge
Add an opt-in Node.js integration service using `whatsapp-web.js` with persistent `LocalAuth` session storage, QR/status endpoints, authenticated message submission, idempotent delivery keys, and signed delivery callbacks into the Laravel message webhook. Route the existing `whatsapp` channel through Cloud API or Web.js by configuration, keep the bridge on the private service network, and document the unofficial-client/QR-session operational and account-blocking risks. The production topology must include a supervised bridge with a persistent auth volume, but remain disabled until an operator explicitly enables and pairs it.
**AC:** the bridge reports `qr`, `authenticated`, `ready`, `disconnected` and failure states; a queued message is sent once per idempotency key and its provider message ID is stored; signed sent/delivered/failed callbacks update the outbound log idempotently; an unavailable bridge falls back to the configured next channel; no QR/session secret is exposed through public application routes.

### ISP-052 · Automations
Expiry reminders (configurable day offsets, tenant-local send hour), payment receipts, suspension and reactivation notices, welcome messages, outage broadcasts by zone/POP.
**AC:** a customer never receives the same reminder twice for one cycle; opt-out is respected; quiet hours enforced.

---

## Phase 6 — Operations (≈6 days)

### ISP-060 · Tickets — lifecycle, categories, SLA clock by priority, internal vs public replies, canned responses, assignment, satisfaction rating, link to service and incident.
**AC:** SLA breach is computed in tenant timezone and business hours; a resolved ticket auto-closes after the configured window.

### ISP-061 · Work orders — types, checklist templates per type, scheduling with a technician calendar and drag-to-reschedule, readings, photos, signature, materials consumption, completion driving service activation.
**AC:** completing an installation work order activates the service and provisions the network in one atomic user action; a failed work order reschedules without losing captured data.

### ISP-062 · Inventory — warehouses (incl. per-technician van), serialised and bulk items, movements, assignment to service, return on disconnection, low-stock alerts.
**AC:** a serial can be in exactly one place at a time (test); disconnecting a service prompts for equipment recovery and shows what's outstanding.

### ISP-063 · Incidents and monitoring — `CheckRouterHealth` (ping + RouterOS resource), `device_metrics` with retention tiers, incident raising on N consecutive failures, customer-facing outage notices, NOC dashboard.
**AC:** a router taken offline raises an incident within the check interval × N and resolves automatically on recovery; metric pruning keeps table sizes bounded.

---

## Phase 7 — API and portal (≈7 days)

### ISP-070 · API foundation — `/api/v1` scaffold, Sanctum tokens with abilities, cursor pagination, `spatie/laravel-query-builder` whitelists, RFC 9457 errors, idempotency middleware, rate limits, `dedoc/scramble` docs, `/app/config` force-upgrade endpoint.
**AC:** OpenAPI spec generates cleanly and is exported in CI; a contract test asserts every documented endpoint exists and every endpoint is documented.

### ISP-071 · Customer API + portal — endpoints from `04 §3`, phone OTP auth (hashed codes, rate limits, attempt caps), portal UI per `05 §4`.
**AC:** OTP request returns 200 for unknown phones (no enumeration); portal dashboard meets the 3G performance budget; a customer can never read another customer's data (isolation suite extended).

### ISP-072 · Collector API — endpoints from `04 §4` including bootstrap/delta/push sync with per-item results.
**AC:** a batch of 100 payments, half of them replays, produces exactly the non-replayed count of new rows and returns a correct per-item result array; a malformed item does not fail the batch.

### ISP-073 · Technician API — endpoints from `04 §5`, separate media upload, work-order completion idempotency.
**AC:** completing a work order twice with the same key produces one completion and one activation.

### ISP-074 · Realtime — Laravel Reverb channels per `04 §7`, channel authorisation, push notification dispatch mirroring key events.
**AC:** a service status change reaches a subscribed client in under 2s; a client cannot subscribe to another tenant's channel.

---

## Phase 8 — RADIUS, sessions, usage (≈6 days)

### ISP-080 · RADIUS schema and sync — stock FreeRADIUS SQL tables (+ `tenant_id`/`service_id`), `nas` populated from `routers`, per-plan `radgroupreply` with `Mikrotik-Rate-Limit`, writes on every service change.
**AC:** creating/updating/suspending a service produces the correct RADIUS rows; a plan change updates the group reply and every affected service.

### ISP-081 · `RadiusDriver` + CoA — group-swap suspension, walled-garden pool, `CoaClient` sending CoA and Disconnect-Request to `routers.coa_port` (**default 1700 for MikroTik**), retries, response handling.
**AC:** integration test against FreeRADIUS + CHR: plan change applies to a live session via CoA without a disconnect; suspension disconnects and the reconnect lands in the walled garden. `[H — lab environment]`

### ISP-082 · Sessions and accounting — `sessions_current` upserts from interim updates, live sessions screen, stale-session handling (dead if `acctupdatetime` older than 2× interim interval; close on `Accounting-On/Off`; nightly cleanup with `Acct-Terminate-Cause='Stale'`).
**AC:** simulating a NAS reboot leaves no permanently-open sessions and no customer locked out by `Simultaneous-Use`.

### ISP-083 · Usage rollups and quota/FUP — `RollupDailyUsage` from `radacct` (or polled counters), `usage_daily`, cycle-scoped counters on the service, `EnforceQuota` applying the plan's FUP action, reset on renewal, customer notification at configurable thresholds.
**AC:** a service crossing its quota is throttled (or blocked) within one rollup cycle, notified once, and restored on renewal; rollups are idempotent per date.

### ISP-084 · Scale work — `radacct` monthly partitioning (or separate DB), archival of partitions older than 12 months, indexes verified with `EXPLAIN` on the hot queries. Partition `network_observations`, `notification_deliveries`, and `audit_events` by time as volume justifies.
**AC:** documented benchmark at 50k services × 90 days of accounting: live-session view < 200ms, usage chart < 500ms.

### ISP-085 · Outbound site connector (conditional — only if the cloud/VPN model requires it) — a small agent per site that pulls signed, allowlisted adapter commands over mTLS and returns signed results, so routers behind NAT are never publicly exposed (`03 §5.6`, `09`). Signed, revocable builds.
**AC:** `[H]` a connector in a lab site executes provision/suspend/restore/disconnect end-to-end; a revoked connector cannot execute; no router management port is reachable from the public internet in the deployment.

---

## Phase 9 — Resellers, reporting, imports (≈6 days)

### ISP-090 · Partners / resellers (basic) — `partners` (adjacency tree with `path`/`depth`), `partner_wallets` (journal-derived), `wallet_transactions` referencing posted entries, top-ups with approval, renewal-from-wallet using the plan's `reseller_amount` (`02 §5`) with an atomic funds check, simple commission = retail − reseller price, credit limits + low-balance threshold, reseller-scoped UI/API via a tested hierarchy service.
**AC:** a reseller sees only their own descendants; a child cannot see a parent's cost/margin or a sibling; insufficient funds/credit blocks activation *before* any entitlement change; two concurrent debits cannot overspend; wallet statement reconciles to the journal.

> **Delivered in the current build:** full price books, versioned commission rules, settlement statements, and the current finance/operations report exports now supersede the original basic-wallet pricing path. Remaining acceptance work is external provider/lab validation and deeper operational reporting as the data model grows.

### ISP-091 · Reports — revenue (by period/zone/plan/collector), collections and collection rate, aging, churn and retention, ARPU, top usage, margin by POP (revenue vs `upstream_links` cost), collector performance, tax summary. All exportable to CSV/XLSX, all base-currency with transaction-currency detail.
**AC:** each report reconciles exactly to the ledger; a spot-check test compares a report total to a hand-computed fixture.

### ISP-092 · Dashboards & attention queue — owner dashboard (revenue, collection rate, active/suspended trend, expiring in 7 days, margin), NOC dashboard (router status, sessions, failed commands, drift, incidents), and the **manager attention queue** (`05 §5a`: expired-but-active, paid-but-provisioning-failed, unallocated payments, stale sessions, low reseller balances), all using deferred props. *(Expiring-supplier-credentials row is added when ISP-P1-01 ships.)*
**AC:** dashboard shell paints in under 500ms with panels streaming in; each attention-queue row deep-links to the actionable record.

### ISP-093 · Import/migration — `imports` table, CSV importers for customers, services, plans, balances, and equipment, with a dry-run preview, per-row validation, error report download, and rollback. Plus a MikroTik `ppp/secret` importer that pulls existing subscribers off a router.
**AC:** a 5,000-row import with 50 deliberate errors imports 4,950 rows, reports the 50 with row numbers and reasons, and can be rolled back cleanly.

---

## Phase 10 — Hardening and launch (≈5 days)

### ISP-100 · Security pass — dependency audit, headers (CSP, HSTS, X-Frame-Options), session hardening, `APP_KEY` rotation command with credential re-encryption, permission matrix re-verification, PII anonymisation action, secrets audit.
**AC:** an automated scan reports no high/critical findings; key rotation is exercised in staging end to end.

### ISP-101 · Performance pass — N+1 audit (`beyondcode/laravel-query-detector` in dev), index review with `EXPLAIN` on the ten hottest queries, cache strategy for plans/settings/permissions, frontend bundle audit against the budgets in `05 §10`.
**AC:** all budgets met with the 50k-service seed.

### ISP-102 · Backup and disaster recovery — `spatie/laravel-backup` to off-site S3-compatible storage, encrypted, monitored, plus a **documented and rehearsed restore** to a clean machine.
**AC:** a full restore from backup on a fresh host is performed and timed; the runbook is written and followed by someone who didn't write it.

### ISP-103 · Observability — Sentry with PII scrubbing, `/health` with dependency and queue-depth checks, schedule monitoring alerts, business-metric dashboard.
**AC:** killing the queue worker, the scheduler, or a router each produces a distinct, actionable alert within 5 minutes.

### ISP-104 · Documentation and handover — admin guide, operator guide, field-app guide (per role, screenshot-led), API docs for the mobile team, deployment runbook, incident runbook (router down, queue stuck, ledger mismatch, restore).
**AC:** a new operator completes the "register → install → suspend → collect → reactivate" flow using only the documentation.

### ISP-105 · Pilot — one real tenant, real routers, real subscribers, run in parallel with their existing process for two weeks with daily reconciliation.
**AC:** zero ledger discrepancies over the pilot; every network-command failure explained and either fixed or documented as expected.

---

## Post-v1 backlog (P1) — deferred to protect the v1 timeline

These are fully specified in `08-suppliers-credentials-wallets.md`. The current build includes the repository-side ISP-P1-01 supplier, contract, bill/payment, inventory, lifecycle, commercial batch capture and reconciliation slice; external supplier acceptance and full journal/AP settlement remain. ISP-P1-02 is implemented in the current build and is retained here as the original specification and acceptance record.

**Prerequisite already in v1:** the `services.provisioning_mode` enum already includes `upstream_credential`, and the double-entry journal already supports supplier-cost and settlement accounts — so P1 is additive, no core migration churn.

### ISP-P1-01 · Suppliers & upstream-credential inventory — per `08 §2`: `suppliers`, `supplier_contracts`, `credential_batches`, `upstream_credentials`, `credential_assignments`, `supplier_bills` and `supplier_payments` are implemented in the current repository slice. CSV/voucher import links batches to tenant/supplier contracts, reserve/assign to a service uses the real `CredentialDriver`, commercial batch capture, operational bill/payment tracking, period reconciliation, permissioned + re-auth + audited reveal, expiry warnings, and the expiring-credentials attention-queue row are covered. Full journal/AP settlement and external supplier acceptance remain follow-on gates.
**AC:** batch import never logs plaintext; one available credential is reserved/assigned exactly once under concurrency (partial unique index); reveal requires capability + re-auth and writes an audit event; a credential nearing expiry/quota surfaces in the attention queue; reconciliation exports purchased, assigned, available, expiring and revoked/invalid counts plus recorded cost by supplier/linked contract/currency; bill payments cannot exceed the recorded bill balance.

### ISP-P1-02 · Price books, versioned commissions, settlement — per `08 §3`: `price_books` + `price_book_items` (plan entitlement, buy/sell/min/max, effective dates), `commission_rules` (versioned) + `commission_entries` (no recompute of posted history), `settlements` (per partner/period/currency, opening/activity/closing/due), funding/payout/settlement postings. Supersedes the basic `reseller_amount` pricing from ISP-090.
**AC:** a renewal uses the snapshotted buy/sell terms; changing a commission rule does not recompute already-posted commissions; a reseller statement and the tenant ledger reconcile for the same period.

> **Current behavior for `upstream_credential` services:** the repository-side `CredentialDriver` reserves and activates an available tenant credential through the network command pipeline, then releases it on suspension or service termination according to its expiry/reuse policy. External supplier provisioning and RouterOS/CoA acceptance remain environment gates; supplier contracts/bills are not yet part of this slice.

---

## Timeline summary

| Phase | Focus | Est. |
|---|---|---|
| 0 | Foundation | 3d |
| 1 | Tenancy, identity, access | 5d |
| 2 | Subscribers, catalog, services | 7d |
| 3 | Money | 8d |
| 4 | Network control | 8d |
| 5 | Communications | 4d |
| 6 | Operations | 6d |
| 7 | API and portal | 7d |
| 8 | RADIUS, sessions, usage (+ optional connector) | 6d |
| 9 | Partners (basic wallets), reporting, dashboards, imports | 6d |
| 10 | Hardening and launch | 5d |
| | **Total (v1)** | **~65 working days** |
| P1 | Suppliers/credentials + advanced reseller settlement (ISP-P1-01/02) | +4d, post-v1 |

**Minimum shippable pilot = Phases 0–4 plus ISP-050/052.** That is roughly 30 working days and delivers the core loop: register → activate → auto-suspend → collect payment → auto-reactivate → notify. Everything after that is expansion. Resist the urge to ship a beautiful ticketing module before the money loop is airtight.

## Critical path and parallelisation

- **Serial:** 0 → 1 → 2 → 3 → 4. Do not parallelise these; each builds on the last, and Phase 3's ledger is load-bearing for everything after.
- **Parallelisable after Phase 4:** Phases 5, 6, and 7 are largely independent. Phase 8 depends on Phase 4's driver abstraction. Phase 9's reporting depends on Phase 3.
- **Start early, outside the code:** WhatsApp Business API approval (ISP-051) and the RouterOS CHR lab for CI (ISP-042). Both have external lead times measured in weeks and will otherwise block a finished phase.
