# 01 — Product Specification

## 1. Personas and what they need

| Persona | Where they work | Primary need | Interface |
|---|---|---|---|
| **Super Admin** (platform operator) | Office, desktop | Onboard tenants, monitor platform health, cross-tenant billing of the SaaS itself | Web |
| **Tenant Owner / Manager** | Office, desktop + phone | Revenue, collection rate, churn, who owes what, margin vs upstream bandwidth cost | Web + mobile |
| **Operator / Front desk** | Office, desktop | Register subscribers, take payments, renew, answer "why am I cut off" | Web |
| **Collector / Cashier** | Field, phone, patchy signal | List of customers due today in their zone, record cash, print/send receipt, work offline | Mobile |
| **Technician** | Field, phone, on a ladder | Today's work orders, customer location, signal readings, install checklist, photos, customer signature | Mobile |
| **NOC / Network admin** | Office, desktop | Router health, active sessions, bandwidth graphs, who is on which tower, kill a stuck session | Web |
| **Sub-reseller / Agent** | Anywhere | Their own subscribers, their wallet balance, activate/renew from wallet, commission earned | Web + mobile |
| **End customer** | Home, phone | Balance and expiry, usage, pay, get a receipt, report a fault, see outage notices | Mobile + portal |

**Design implication:** the office web app is dense and keyboard-driven; the field surfaces are big-target, low-bandwidth, offline-tolerant. Don't build one UI for both.

### 1a. Full persona and capability model

The eight personas above are the everyday surfaces; the authorization model recognises a finer set of roles so a real ISP org (finance vs cashier vs collector, network admin vs support, reseller owner vs reseller staff) maps cleanly. **Authorize on capabilities, not role names.** Seed roles are templates; policies decide using tenant membership + partner scope + record ownership + branch/zone scope + status + approval limit + permission.

| Persona | Main responsibilities | Restricted from |
|---|---|---|
| Platform operator (super admin) | SaaS tenant lifecycle, platform health | Tenant financial/customer data by default; access is **break-glass**, time-bounded, owner-visible, audited |
| Tenant owner | Company settings, roles, finance, integrations | Nothing inside its tenant except platform secrets |
| Operations manager | Customers, services, staff, approvals, reports | Platform / other-tenant data |
| Billing manager | Plans, invoices, adjustments, settlements | Router credentials and high-risk network ops unless separately granted |
| Cashier | Receive payments, print receipts, close own till | Voids/refunds/discounts above policy limit |
| Collector | Assigned customers, field cash, sync receipts | Other routes, price changes, debt write-offs |
| Support agent | Customer/service view, tickets, diagnostics | Wholesale cost, wallet ops, secret credentials |
| Technician | Assigned work orders, equipment, install evidence | Billing edits, broad customer exports |
| Network administrator | NAS, pools, provisioning, sessions, diagnostics | Financial adjustments unless separately granted |
| Reseller owner | Own descendants, price book, customers, wallet, statements | Parent cost/margin, siblings, tenant-wide settings |
| Reseller staff | Delegated subset of reseller functions | Wallet funding, price/margin management by default |
| Auditor / read-only | Reports, audit, non-secret records | Mutations and decrypted secrets |
| Customer | Own accounts, services, invoices, payments, tickets | Staff notes, costs, other customers |

**Permission catalog** (enumerated in a seeder; never invented at a call site). Naming is `<module>.<action>`:

```text
customers.view customers.create customers.update customers.export
services.view services.create services.activate services.suspend services.change_plan services.force_resume
billing.invoices.view billing.invoices.issue billing.adjustments.create
payments.collect payments.backdate payments.void refunds.approve
wallets.view wallets.fund wallets.adjust settlements.approve
network.view network.provision network.disconnect network.credentials.reveal
suppliers.view credentials.import credentials.reserve credentials.assign credentials.reveal
inventory.view inventory.receive inventory.transfer inventory.assign inventory.write_off
tickets.view tickets.create tickets.assign tickets.close
reports.finance reports.operations reports.export
settings.manage users.manage roles.manage audit.view
```

Sensitive capabilities (`*.reveal`, `refunds.approve`, `wallets.fund`, `roles.manage`, mass export) require **re-authentication** at the moment of use, not just possession of the permission. See `09`.

---

## 2. Module map

```
CORE
├── Tenancy & Settings        tenants, branches, zones, currencies, FX, numbering, locales
├── Identity & Access         staff users, roles, permissions, sessions, 2FA, audit log
├── Subscribers (CRM)         customers, contacts, documents, addresses/GPS, leads
├── Catalog                   plans, pricing (multi-currency), promotions, add-ons, FUP policies
├── Services (Subscriptions)  the actual internet line: credentials, router, IP, status, expiry
BILLING
├── Ledger                    immutable double-sided entries, per-customer balance
├── Invoicing                 recurring generation, proration, credit notes, tax
├── Payments                  cash/bank/gateway, partial, allocation, receipts, idempotency
├── FX                        rate table, snapshot-at-transaction, revaluation reports
├── Journal                   double-entry accounts, balanced entries, wallet/settlement postings
├── Reseller wallets          top-ups, deductions, commission accrual and payout
SUPPLIERS & RESELLERS
├── Suppliers                 upstream providers, contracts, wholesale cost, supplier bills
├── Credential inventory      purchased upstream accounts/vouchers: import, reserve, assign, reveal
├── Partner hierarchy         reseller → sub-reseller adjacency tree, price books, entitlements
├── Settlement                commissions, statements, prepaid/postpaid partner settlement
NETWORK
├── Devices                   routers/NAS, POPs/towers, IP pools, credentials vault
├── Enforcement               activate / suspend / throttle / disconnect via driver
├── Sessions & Usage          RADIUS accounting or API polling, daily rollups, quota + FUP
├── Monitoring                ping/SNMP health, uptime, alerts, outage broadcast
OPERATIONS
├── Tickets                   customer complaints, SLA, categories, canned replies
├── Work Orders               install/repair/relocate/disconnect, scheduling, checklists
├── Inventory                 warehouses, serialised devices, assignment to customer, returns
├── Costs                     upstream bandwidth contracts, per-POP cost, margin analysis
COMMS
├── Templates & Sending       WhatsApp / SMS / email / push, per-locale templates
├── Automations               expiry reminders, payment receipts, suspension notices
SELF-SERVICE
├── Customer portal           balance, usage, pay, tickets, documents
└── Mobile API                staff apps + customer app, offline sync
```

---

### 2a. Service provisioning modes

A **service** (one internet connection) runs in exactly one provisioning mode. The commercial lifecycle (billing, renewal, suspension) is identical across modes; only *how the entitlement reaches the network* differs. This lets a reseller with no router start on `manual`/`upstream_credential` and graduate to automation without changing the customer model.

| Mode | Meaning | MVP behavior |
|---|---|---|
| `manual` | Staff tracks the service; the network is handled outside the app | Create a queued checklist/action; allow authorized manual confirmation of activate/suspend/restore |
| `upstream_credential` | A purchased upstream account/voucher is assigned to the customer | The repository-side `CredentialDriver` reserves and assigns from credential inventory (`08`), with expiry/quota warnings. External supplier provisioning and RouterOS/CoA acceptance remain launch gates. |
| `mikrotik` | The app provisions a RouterOS PPP/Hotspot/etc. identity | MikroTik adapter creates/updates/enables/disables; command results recorded (`03 §5.2`) |
| `radius` | Central AAA (FreeRADIUS) authorizes and accounts sessions | App manages authorization rows; RADIUS sends accounting (`03 §5.3`) |
| `external` | A third-party OSS/UISP/ACS is the system of execution | Webhook/API adapter; the local service stays the commercial source of truth |

The provisioning mode drives which `NetworkDriver` (`03 §5.1`) executes a command; `manual` and `upstream_credential` use a null/inventory driver that records intent for a human to confirm.

---

## 3. The core loop (build this first, correctly)

```
Customer registered
  → Service created (plan, router, credentials)
    → Activated on network  ──────────► subscriber gets internet
      → expires_at reached
        → grace period
          → auto-suspend job ─────────► network blocks / throttles
            → customer pays (office, field agent, portal, or gateway)
              → payment recorded → ledger entry → service extended
                → auto-reactivate job ─► network unblocks
                  → receipt sent (WhatsApp/SMS)
```

Everything else in this document is scaffolding around that loop. Phases 2–4 in the build plan exist to make this loop work end to end.

---

## 4. User stories with acceptance criteria

Written as `As a <persona>, I want <goal>, so that <reason>` with testable criteria. IDs are referenced by tickets in `06`.

### 4.1 Subscribers

**US-C1 — Register a subscriber**
As an Operator, I want to register a new subscriber with their location and plan, so that a technician can install them.
- Required: full name, primary phone, zone, address line, plan, connection type.
- Optional: second phone, WhatsApp number, national ID, email, GPS pin, building/floor/apartment, notes, referral source.
- Phone is unique per tenant; attempting a duplicate shows the existing customer inline, not a validation error dead-end.
- On save, a `Service` in `pending` status and an `installation` work order are created atomically.
- Customer code is auto-generated per tenant with a configurable prefix (e.g. `BAA-00142`).

**US-C2 — Find a subscriber fast**
As an Operator on a phone call, I want to find a subscriber by any fragment of phone, name, code, PPPoE username, or IP, so that I can answer in under five seconds.
- One global search box, `Cmd/Ctrl+K`, debounced, returns across customers, services, invoices, tickets, and devices.
- Search on partial phone works regardless of formatting (`70123456`, `+961 70 123 456`, `70-123456` all match).

**US-C3 — See everything about a subscriber on one screen**
As an Operator, I want one page showing balance, service status, expiry, last payment, active session, assigned equipment, open tickets, and full timeline.
- Timeline is a merged, reverse-chronological feed of: status changes, payments, invoices, tickets, work orders, notes, network commands, and messages sent.

### 4.2 Plans and services

**US-S1 — Define a plan**
As a Manager, I want to define a plan with speed, quota, price per currency, and billing period.
- Fields: name, download/upload rate (kbps), burst config (optional), quota bytes (nullable = unlimited), FUP action on quota exhaustion (`throttle` to a defined rate | `block` | `notify_only`), billing period (`monthly` | `weekly` | `days:N`), grace days, setup fee, prices per currency.
- Changing a plan's price never retroactively alters issued invoices.

**US-S2 — Activate a service**
As a Technician, I want to mark an installation complete and have the line go live.
- Completing the install work order sets service `active`, sets `activated_at`, computes `expires_at` = activation date + billing period, dispatches the network activation job, assigns consumed inventory, and triggers a welcome message.
- If the network job fails after retries, the service shows a prominent `network out of sync` badge with a one-click retry, and an alert is raised. The service is **not** silently marked active-but-broken.

**US-S3 — Change a plan mid-cycle**
As an Operator, I want to upgrade or downgrade a subscriber.
- Choose effective date: `immediately` (with proration) or `at next renewal`.
- Immediate change generates a proration credit for the unused portion and a charge for the new plan's remainder, both as ledger entries.
- Network rate-limit is pushed immediately via CoA (RADIUS) or profile/queue update (direct API) without disconnecting the session where the driver supports it.

**US-S4 — Suspend and restore**
As the system, I want to suspend overdue services and restore them on payment, without human action.
- Daily scheduled job at a tenant-configurable hour: any `active` service where `expires_at + grace_days < now` moves to `suspended`.
- Suspension is a status change plus a queued network command; the two are never assumed to be in lockstep — `network_state` is tracked separately from `status`.
- A payment that brings the balance to zero (or covers the renewal amount) triggers reactivation within 60 seconds, measured from payment save to network command acknowledgement.
- Manual suspension (abuse, fraud, customer request) is distinct from automatic suspension and is not undone by a payment. Track `suspension_reason`.

### 4.3 Money

**US-B1 — Record a cash payment in a second currency**
As a Collector, I want to take cash in local currency against a bill priced in USD.
- Payment records: amount, currency, FX rate used, base-currency equivalent, method, received-by, received-at, optional reference.
- The FX rate defaults to the tenant's current rate but is overridable per payment with a reason, because street rates move.
- Over-payment becomes customer credit, not an error. Under-payment is allowed and leaves a balance.

**US-B2 — Never double-charge on a flaky connection**
As a Collector on 1 bar of signal, I want to hit "save" three times and create one payment.
- Client generates a UUID `idempotency_key` per payment attempt; the server returns the original result on replay.
- Offline queue on the device; on reconnect, batch sync endpoint replays with the same keys.

**US-B3 — Reconcile a collector's day**
As a Manager, I want to see what each collector collected and confirm they handed the cash in.
- Cash session / shift model: open shift → collect payments → close shift with declared amount → variance flagged if declared ≠ system total.
- Report per collector per day: count, gross by currency, base equivalent, variance, unremitted balance.

**US-B4 — Issue and send an invoice**
As the system, I want to generate invoices on schedule.
- Configurable per tenant: anniversary billing (each customer bills on their own day) or calendar-cycle billing (everyone on the 1st, prorated on join).
- Sequential, gapless invoice numbering per tenant per year, generated inside a DB transaction with a lock — never `max(id)+1` outside a transaction.
- Invoice PDF renders in the customer's locale and currency, with tenant branding.

**US-B5 — Sub-reseller wallet**
As a Sub-reseller, I want to renew my customers from a prepaid wallet.
- Wallet has a balance per currency; renewal deducts the reseller price (which may differ from the retail price); commission is the delta.
- Insufficient balance blocks the action with a clear message and a top-up request flow.
- Optional credit limit allowing negative balance up to a configured amount.

### 4.4 Network

**US-N1 — Register a router**
As a NOC admin, I want to add a MikroTik router and verify connectivity.
- Fields: name, POP/site, host, API port, credentials, driver type, RADIUS secret + CoA port (if RADIUS mode), management VLAN/IP notes.
- "Test connection" performs a read-only call and reports RouterOS version, uptime, and identity, or a precise error (auth failed / timeout / TLS error / service disabled).
- Credentials are encrypted at rest and write-only in the UI (never sent back to the browser).

**US-N2 — See who is online**
As a NOC admin, I want a live list of active sessions with uptime, IP, MAC, and current rate.
- Sourced from RADIUS accounting where available, otherwise polled from the router API on a schedule.
- Per-session actions: disconnect, view history, jump to the customer.

**US-N3 — Enforce quota / FUP**
As the system, I want to act when a subscriber exceeds their plan quota.
- Daily usage rollup per service from accounting data.
- On breach: apply the plan's FUP action, log it, notify the customer, and reset at the start of the next cycle.

**US-N4 — Detect an outage before customers call**
As a NOC admin, I want to be alerted when a POP or router goes down.
- Ping/SNMP check per device on an interval; N consecutive failures raises an incident.
- Incident optionally broadcasts a notice to affected customers (by zone/POP) and shows a banner in the portal, suppressing duplicate tickets.

### 4.5 Operations

**US-O1 — Ticket lifecycle** — open, categorise (no service / slow / billing / relocation / other), assign, SLA clock by priority, internal notes vs customer-visible replies, resolve with a resolution code, reopen window. Tickets link to a service and can spawn a work order.

**US-O2 — Work orders** — type, scheduled window, assigned technician, checklist per type, required photos, signal readings (CCQ/signal/CRC for wireless, optical power for fiber), materials consumed from inventory, customer signature, completion notes. Technicians see only their own assignments.

**US-O3 — Inventory** — serialised items (routers, ONUs, antennas) tracked individually with a status (`in_stock`, `assigned`, `faulty`, `returned`, `lost`), bulk items (cable, connectors) tracked by quantity per warehouse. Assignment to a customer is a movement, and disconnection prompts for recovery of the device.

**US-O4 — Margin visibility** — record upstream bandwidth contracts (capacity, monthly cost, POP) so the dashboard can show revenue vs bandwidth cost per POP and per subscriber. This is the number that tells the owner whether a tower is worth keeping.

### 4.6 Self-service

**US-P1 — Customer logs in with their phone number** via OTP (no password to forget), sees balance, expiry, current usage against quota, invoice history, and receipts.
**US-P2 — Customer pays online** where a gateway is configured; payment posts to the ledger and triggers reactivation through the same path as a cash payment. One code path, not two.
**US-P3 — Customer opens a ticket** with a category and optional photo, sees status updates, gets a push/WhatsApp notification on reply.
**US-P4 — Customer sees an outage notice** for their zone instead of being told to restart their router.

---

## 5. Business rules (the ones that cause bugs if left implicit)

### 5.1 Renewal date arithmetic
- Renewal extends from `max(now, expires_at)` — a customer who pays 3 days early does not lose 3 days; a customer who pays 10 days late does **not** get a free 10 days unless the tenant enables `grace_extends_period`.
- Monthly means "same day next month", clamped to month end (Jan 31 → Feb 28/29 → Mar 31, anchored to the original day, not drifting).
- All date arithmetic happens in the **tenant's timezone**, stored UTC. Never compare a UTC timestamp to a local business day boundary without conversion.

### 5.2 Status vs network state
Two separate fields, always:
- `status` — the business truth: `pending`, `active`, `suspended`, `blocked`, `terminated`, `archived`.
- `network_state` — the last confirmed reality on the device: `unknown`, `provisioned`, `enabled`, `disabled`, `error`, `pending_sync`.
A reconciliation job compares them and surfaces drift. Drift is normal (routers reboot, configs get hand-edited); silent drift is the enemy.

### 5.3 Suspension precedence
`manual_block` > `fraud_block` > `auto_overdue`. A payment clears only `auto_overdue`. Anything else needs a human to lift it.

### 5.4 Money
- Every monetary amount carries a currency. There is no "default currency" in the data layer.
- Each tenant has a `base_currency` used for reporting. Every ledger entry stores `amount_minor`, `currency`, `fx_rate`, and `base_amount_minor` computed at write time. Historical reports never re-derive FX.
- Rounding: half-up at the minor unit, applied once at the end of a calculation chain, never mid-chain.
- Zero-decimal and three-decimal currencies exist (JPY, KWD, BHD). Use the library's minor-unit scale; do not hardcode `* 100`.

### 5.5 Invoice numbering
Per tenant, per year, gapless, format configurable (`{prefix}-{year}-{seq:6}`). Allocated inside a transaction using a row lock on a `document_sequences` table. A voided invoice keeps its number and is marked void — numbers are never reused.

### 5.6 Deletion
Nothing financial or network-related is ever hard-deleted. Soft deletes plus an `archived` state. A "delete customer" action anonymises PII on request (name, phone, ID document) while preserving the financial record, for data-protection compliance.

### 5.7 Concurrency
- Recording a payment locks the customer's balance row for the duration of the transaction.
- The daily suspension job takes a per-tenant lock and processes in chunks; it must be safe to run twice.
- Network commands for a single service are serialised through a queue keyed on the service ID, so an "activate" can't overtake a "suspend".

---

## 6. Explicitly out of scope for v1

Say no now, or the build never ends. Each of these is a v2 candidate.

- VoIP/telephony billing, IPTV, and CDR rating
- TR-069 / auto-CPE provisioning
- Full double-entry accounting with a chart of accounts (the ledger here is subscriber-balance-focused; export to accounting software instead)
- Automated tax filing
- Native mobile apps (this pack delivers the API they will consume, and a responsive PWA-ready portal)
- Multi-language customer support chat / live chat
- IPv6 allocation management (design the schema to tolerate it; don't build the workflows)
- GPON/OLT vendor integrations beyond the driver interface

---

## 7. Success metrics for the build

The product is working when:
1. Time from cash received to internet restored is under 60 seconds, unattended.
2. The daily suspension run for 5,000 services completes in under 2 minutes with zero manual intervention.
3. A collector can record 40 payments on a phone with no signal and sync them all without a single duplicate.
4. An operator can answer "why is this customer offline" in one screen and under 10 seconds.
5. The owner can see revenue, collection rate, and bandwidth cost per POP for last month without exporting anything.
