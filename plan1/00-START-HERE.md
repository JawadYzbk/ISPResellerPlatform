# ISP Manager — Build Handoff Pack

**Product:** A management platform for small/local internet providers and resellers (WISPs, fiber resellers, building/neighbourhood distributors).
**Stack:** Laravel 13 + Inertia.js v3 + React 19 (TypeScript) + Tailwind 4 + shadcn/ui, on **PostgreSQL 17 + Redis**, with a versioned REST API for a future mobile app.
**Audience for this document:** an AI coding agent building the system end-to-end, plus the human reviewing it.

> **This pack is a merge of two source plans** (a build-handoff pack and a research-backed architecture brief), consolidated into one. Where they differed, the resolved decisions are in the Decision Log (Section 5). The research brief has been archived under `../archive/` for provenance; this pack is now the single source of truth.

---

## 1. How to use this pack

Read in this order. Do not skip.

| File | What it is | When you need it |
|---|---|---|
| `00-START-HERE.md` | This file. Assumptions, decisions, guardrails, working agreement. | Before anything. |
| `01-product-spec.md` | Personas, modules, user stories, business rules. | Before designing anything. |
| `02-domain-model.md` | Complete schema, ERD, enums, invariants. | Before writing migrations. |
| `03-architecture.md` | Versions, packages, folder layout, patterns, the network-control layer. | Before writing code. |
| `04-api-spec.md` | REST `v1` contract for the mobile apps and the portal. | Phase 7 onward; read early so models don't fight it. |
| `05-frontend-spec.md` | Inertia/React setup, page inventory, component library, UX rules. | Phase 1 onward. |
| `06-build-plan.md` | Phased ticket list with acceptance criteria. **This is your task queue.** | Every working session. |
| `07-conventions-and-testing.md` | Code standards, testing strategy, Definition of Done, CI, deployment. | Every working session. |
| `08-suppliers-credentials-wallets.md` | Suppliers, upstream-credential inventory, price books, commissions, settlement. **P1 — post-v1** (tickets ISP-P1-01/02). v1 ships only basic partner wallets (`06 ISP-090`). | When planning P1; skim early so the v1 journal/enum leave room. |
| `09-security-and-operations.md` | Threat model, OWASP mapping, SSRF/router security, network connectivity modes, deployment, observability, DR, runbooks. | Before Phase 4 (network) and Phase 10 (hardening). |

**Rule:** the specs are the source of truth. If you need to deviate (a schema change, a different package, a renamed endpoint), **edit the relevant spec file in the same commit** and note it in the Decision Log in Section 5 below. Never let code and spec drift silently.

---

## 2. What this product is, in one paragraph

A local ISP/reseller buys bandwidth upstream and resells it to homes and small businesses over PPPoE, hotspot, static IP, or plain DHCP, typically through MikroTik routers. Their day-to-day problems are: knowing who owes money, cutting off non-payers automatically, turning them back on the instant they pay, tracking cash collected by field agents, keeping routers/antennas accounted for, and answering "why is my internet slow" without guessing. This platform is the operational spine for that: subscriber CRM, plans and pricing, invoicing and cash collection with multi-currency support, automatic network enforcement, field-tech work orders, inventory, tickets, and self-service for the end customer.

**The single feature that makes or breaks this product:** payment → reconnect must happen within seconds, reliably, without a human touching a router. Everything else is CRUD around that loop.

---

## 3. Assumptions baked into this plan

These are the defaults. **Confirm or flip them before Phase 2.** Each one is cheap to change now and expensive to change in Phase 5.

| # | Assumption | If it's wrong |
|---|---|---|
| A1 | **Multi-tenant.** One deployment serves many independent providers ("tenants"). Single database, `tenant_id` column + global scope. Super-admin can see across tenants. | If it's a single company only, keep the `tenant_id` column and seed one tenant — cost is near zero, and you keep the option open. Do **not** remove it. |
| A2 | **Sub-reseller hierarchy exists.** A tenant can have sub-agents who sell to end customers, carry a prepaid wallet, and earn commission. | If not needed, build Phase 3.5 last or skip. Schema stays. |
| A3 | **MikroTik RouterOS v7 is the primary network device.** Direct REST API first, FreeRADIUS second. | If Ubiquiti/Huawei OLT/Cisco, the `NetworkDriver` interface still holds; write a new driver. |
| A4 | **Prepaid monthly is the default billing model** (subscription has an `expires_at`; payment extends it). Postpaid invoicing is supported but secondary. | Flip the tenant setting; both code paths are specified. |
| A5 | **Cash is the dominant payment method,** collected in the field by staff, often in a currency different from the pricing currency. Multi-currency and an FX rate table are core, not an add-on. | If card/online-only, the ledger still works; you just never use the FX columns. |
| A6 | **Field staff work on phones, often with bad connectivity.** The mobile API must be offline-tolerant (idempotency keys, delta sync). | Simplifies the API if untrue, but build it anyway — it costs one column. |
| A7 | **Languages: English + Arabic (RTL) + French.** UI is i18n'd and RTL-capable from day one. | Drop locales from the config array. Do not bolt RTL on later. |
| A8 | **Scale target:** up to ~50 tenants, ~50k active subscribers total, ~20 routers per tenant. | Above this, move RADIUS accounting to its own database and partition `radacct`. |
| A9 | **Deployment:** single VPS or small cluster, Docker, self-hosted. Not serverless. | Laravel Cloud/Forge changes only `07`'s deployment section. |

---

## 4. Non-negotiable guardrails

The agent must not violate these, regardless of convenience.

1. **No synchronous router calls from an HTTP request.** Every network mutation is dispatched as a queued job, recorded in `network_commands`, and retried with backoff. A dead router must never produce a 500 on a payment form.
2. **Money is never a float.** Store integer minor units + ISO-4217 currency code. Use `brick/money`. Any `decimal` or `float` column holding money is a bug.
3. **Router and PPPoE credentials are encrypted at rest** using Laravel's `encrypted` cast. They never appear in logs, API responses, Inertia props, or exception reports. Add them to `config/logging.php` scrubbing and to a `$hidden` list.
4. **Every mutation is attributed and audited.** `spatie/laravel-activitylog` on all financial and network models. "Who suspended this customer at 2am" must be answerable.
5. **Payments are append-only.** No editing or deleting a payment. Corrections are reversal entries. The ledger is immutable.
6. **Tenant isolation is enforced at the model layer**, not by remembering to add `where('tenant_id', ...)` in controllers. A test must prove a tenant cannot read another tenant's records through any route.
7. **Idempotency on all money-writing and network-writing endpoints.** A retried mobile request must not double-charge or double-disconnect.
8. **No business logic in controllers.** Controllers validate, call one Action, return a response. See `07`.
9. **Nothing ships without tests.** See the Definition of Done in `07`.

---

## 5. Decision log

Append to this table as decisions are made or changed. Format: date, decision, reason.

| Date | Decision | Reason |
|---|---|---|
| 2026-08-10 | Laravel 13 (PHP 8.3+), Inertia v3, React 19, Vite 7, Tailwind 4, shadcn/ui | Current stable as of Aug 2026; Inertia v3 removed Axios, ships its own Vite plugin and `useHttp`. Laravel 13 shipped Mar 2026 with no breaking changes from 12. Use the official Laravel React starter kit rather than hand-assembling the frontend. |
| 2026-08-10 | Single-database multi-tenancy with `tenant_id` + global scope, hand-rolled (pattern borrowed from `stancl/tenancy`'s single-DB mode) | Cross-tenant super-admin reporting is a product requirement; DB-per-tenant makes that painful. Keeps migrations and backups simple at this scale. |
| 2026-08-10 | Tenant isolation is **defense-in-depth**: global scope **and** explicit tenant context in query objects/actions **and** policy checks — never rely on the global scope alone | Global scopes silently miss nested relations and several `unique`/`exists` validation paths. The isolation test suite must probe every access vector. |
| 2026-08-10 (merge) | **PostgreSQL 17** as the primary datastore (was MySQL 8.4 in one source) | Better fit for the double-entry journal, JSONB custom fields, partial unique indexes, trigram/full-text search, and time-partitioning of `radacct`/audit/observations. FreeRADIUS's stock schema runs on PostgreSQL. |
| 2026-08-10 (merge) | **Double-entry journal ledger** (`ledger_accounts` + `journal_entries` + `journal_lines`), with the per-customer `balance_amount` kept as a *derived cache/projection* reconciled nightly (was a single-sided customer-balance ledger in one source) | The double-entry model is required to represent reseller wallets, supplier cost allocation, commissions, and settlement without bolt-ons. Every balance-affecting action posts a balanced entry; issued documents and posted entries are never hard-deleted. See `02 §9` and `08`. |
| 2026-08-10 (merge) | **Capability-based authorization** (explicit permission catalog) with seed roles as templates; policies authorize on tenant membership + partner scope + ownership + branch/zone + status + approval limit + permission | Role-name checks don't survive real ISP org structures (13 personas, reseller hierarchy, break-glass platform access). Permission strings are enumerated in a seeder, never invented at call sites. See `01 §1a`. |
| 2026-08-10 (merge) | **Five provisioning modes** on a service: `manual`, `upstream_credential`, `mikrotik`, `radius`, `external` — behind one adapter layer | Some resellers control routers; others just resell upstream accounts/vouchers; some hand off to a third-party OSS. A safe `manual`/`upstream_credential` MVP precedes router automation. See `02 §7`, `03 §5`, `08`. |
| 2026-08-10 (merge) | **Commercial status and provisioning status are separate fields** on a service, each an explicit state machine | A paid subscription may be `commercial_status=active` while `provisioning_status=failed`. The UI shows both; a device never mutates the database. Mirrors the `status` vs `network_state` split. |
| 2026-08-10 (merge) | **Public IDs are UUIDv7/ULID** (time-ordered), internal keys may stay bigint | Non-sequential public IDs prevent subscriber enumeration; time-ordering keeps index locality that random UUIDv4 loses. |
| 2026-08-10 (merge) | **Outbox pattern** for all external side effects; commit finance/desired-state before any network/message/gateway call | A dead router, SMS provider, or gateway must never roll back a recorded payment. See `03 §5.4`, `03 §6`. |
| 2026-08-10 | Sanctum for both web session auth and mobile bearer tokens | One auth stack, token abilities give per-app scoping, no OAuth server to run. Passport only if the product later becomes an OAuth2 server for third parties. |
| 2026-08-10 | `NetworkDriver` interface with MikroTik-REST first, FreeRADIUS second | Direct API is far easier to get right for a shop with 1–5 routers; RADIUS is the scaling path. Both are needed, in that order. |
| 2026-08-10 | Prepaid-expiry as default billing model | Matches how small local ISPs actually operate; postpaid invoicing is layered on the same ledger. |

---

## 6. Working agreement for the agent

- **Work ticket by ticket** from `06-build-plan.md`. One ticket = one branch = one PR-sized commit set. Do not batch phases.
- **Start every ticket** by re-reading its acceptance criteria and the relevant section of `02` and `07`.
- **End every ticket** with: migrations run clean on a fresh DB, `pint` clean, `phpstan` clean at the configured level, `pest` green, `tsc --noEmit` clean, and the ticket's acceptance criteria demonstrably met.
- **Ask before**: adding a paid/commercial dependency, changing the money or ledger model, changing tenant isolation, changing the API contract in `04` after Phase 7 has started.
- **Do not** scaffold speculative features not in the spec. Do not add an admin panel package (Filament/Nova) — the UI is Inertia+React by requirement.
- **Seed realistic data.** A demo tenant with 200 customers across 3 zones, 4 plans, 2 routers, 6 months of invoices/payments, and a spread of statuses. Every phase should be demoable, not just testable.

---

## 7. Open questions to resolve with the product owner

Answer these before Phase 3. They are the only items in this pack where guessing is expensive.

1. **Tenancy:** SaaS for many providers, or one provider only? (A1)
2. **Billing model:** prepaid expiry, postpaid invoicing, or both from day one? (A4)
3. **Currencies:** which pricing currency, which collection currencies, who sets the FX rate and how often?
4. **Network:** how many routers, what models, RouterOS version, and is there an existing RADIUS server?
5. **Payments:** cash only, or which online gateways / mobile-money providers must be integrated?
6. **Notifications:** WhatsApp Business API (needs a Meta business account and template approval, ~2 weeks lead time), or SMS gateway, or both?
7. **Migration:** is there existing data (spreadsheets, another billing system, MikroTik User Manager) to import? This is usually a whole phase on its own.
8. **Regulatory:** does the target market require a licence, invoice numbering rules, tax registration, or subscriber data retention? This affects invoice numbering and the audit schema.
9. **Upstream credentials:** do resellers buy individual upstream accounts/vouchers, bulk bandwidth, or both? What fields arrive from a supplier (username, password, quota, profile, expiry, serial, PIN, voucher code)? This shapes the `08` credential-inventory module.
10. **Renewal semantics:** rolling duration from payment vs fixed calendar anchor; does late renewal backdate to previous expiry or start from payment date; is there a grace/reduced-speed/full-suspend ladder; can service be paused with days preserved? These are the only rules where guessing is expensive — confirm before Phase 3.
11. **Reseller commercial model:** prepaid wallet, credit limit, postpaid settlement, commission, or a mixture? Can a child reseller see upstream wholesale cost or only its own price?
12. **Cloud vs on-network:** will Laravel workers reach routers over a management VLAN/VPN, or is an outbound site connector needed (routers behind NAT)? Router management ports must never be public. See `09`.

---

## 8. Recommended ADRs

Write each as `docs/architecture/ADR-XXX.md` as the corresponding decision is implemented. These capture the load-bearing choices so a future maintainer understands *why*, not just *what*.

1. `ADR-001` Modular monolith and module boundaries (`03 §3a`)
2. `ADR-002` Shared-schema multi-tenancy and its enforcement layers (`03 §4`)
3. `ADR-003` Customer vs service vs subscription model (`02 §7`)
4. `ADR-004` Versioned product/pricing snapshots (`02 §5`)
5. `ADR-005` Integer money and double-entry journal posting model (`02 §9`)
6. `ADR-006` Renewal semantics and idempotency (`02 §5.1`, `06 ISP-024/034`)
7. `ADR-007` Outbox and provisioning desired-state model (`03 §5.4`)
8. `ADR-008` Network adapter interface and safe connectivity model (`03 §5`, `09`)
9. `ADR-009` Web session + Sanctum mobile authentication (`03 §7`)
10. `ADR-010` Audit, retention, and secret handling (`09`)
11. `ADR-011` Localization / timezone / multi-currency strategy (`05 §8`, `07 §5`)
12. `ADR-012` API versioning and OpenAPI compatibility policy (`04 §1`)

---

## 9. AI implementation agent handoff prompt

Copy the block below into the coding agent together with this pack. It restates the guardrails as operating rules.

```text
You are the lead implementation agent for the ISP Manager platform.

Your source of truth is this pack (00–09). Read it completely before changing code.
Laravel 13 + official React/Inertia 3 starter kit, TypeScript strict, Tailwind 4,
shadcn/ui, PostgreSQL 17, Redis, Horizon, Sanctum, a versioned /api/v1, future mobile.

OPERATING MODE
1. Work ticket by ticket from 06-build-plan.md. One ticket = one branch = one PR-sized change.
2. Before a ticket: re-read its acceptance criteria and the relevant sections of 02 and 07.
3. Implement vertical slices (migration/constraint → model → action → policy → validation →
   API resource/controller → Inertia UI → audit → tests → docs), not all models first.
4. Keep controllers thin: validate, authorize, call ONE Action, return. Transactions live in
   Actions. No external calls in models/observers/controllers.
5. The database is authoritative for finance and desired service state. Routers are systems of
   execution/observation and may never mutate the database.
6. Maintain docs/product-decisions.md, docs/domain-glossary.md, docs/architecture/ADR-*.md,
   docs/security/threat-model.md, docs/runbooks/, openapi/isp-platform-v1.yaml, and
   docs/implementation-status.md.

NON-NEGOTIABLE INVARIANTS (see 00 §4 and 02 §15)
- Every tenant-owned query/write is tenant-scoped AND policy-authorized.
- Customer and service are separate; one customer can have many services.
- Plan versions and issued financial documents preserve snapshots/history.
- Money uses integer minor units + ISO currency; never float.
- Financial entries are balanced and immutable; corrections use reversal/credit/refund.
- Wallet, sequence, stock, credential, and renewal conflicts use DB transactions, row locks,
  and unique constraints.
- Risky mutations require idempotency keys.
- External side effects occur AFTER commit through outbox + queued commands.
- Provisioning commands are typed, allowlisted, idempotent, guarded by desired-state version.
- Secrets are encrypted, redacted, never listed by default; reveal is separately authorized/audited.
- No arbitrary router CLI/shell execution feature.
- A token ability is never a substitute for a Laravel policy/ownership check.

Stop and request product-owner approval before changing billing date semantics, wallet/commission
rules, tenant hierarchy, accounting behavior, destructive retention, or public network exposure.
Do not claim completion if tests are skipped, the UI was not exercised, OpenAPI is stale, or an
external integration was only mocked without a documented lab-validation step.
```
