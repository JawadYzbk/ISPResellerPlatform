# 09 — Security, Deployment, Observability & Disaster Recovery

Security is a **release criterion**, not a phase — this app controls customer data, money, and network access. `03 §7` covers the baseline auth/encryption/rate-limit posture and `07 §6` lists the runbooks; this file is the deeper, consolidated reference: the threat model, OWASP mapping, router/SSRF specifics, production topology, observability, and disaster recovery.

Use **OWASP API Security Top 10** and **OWASP Top 10:2025** as the working checklists. For this product the sharp edges are: broken access control (tenant/partner isolation), security misconfiguration (router exposure), authentication failures, supply-chain, and missing security logging.

---

## 1. Threat model (write `docs/security/threat-model.md` in Phase 0)

| Asset | Threat | Primary control |
|---|---|---|
| Subscriber PII | Cross-tenant/cross-partner read; enumeration | Tenant scope + policy + 404-not-403; UUIDv7 public IDs; isolation test suite (`07 §2 #1`) |
| Money / ledger | Double-charge, silent balance edit, deletion of evidence | Idempotency; immutable balanced journal; no balance-edit endpoint; approval thresholds |
| Router credentials & upstream secrets | Leak via logs/props/responses; theft | `encrypted` casts, `$hidden`, log scrubbing, keyed-hash lookup, permissioned+re-auth reveal, audit |
| Router control plane | Public exposure; SSRF; arbitrary command | Private network/VPN/connector; endpoint IP validation; typed allowlisted operations only |
| Collector devices | Lost phone with cached data | Token revocation, remote wipe of cache, device list, shift reconciliation |
| Platform (super-admin) access | Insider access to tenant data | Break-glass: time-bounded, reasoned, owner-visible, audited |
| Supply chain | Compromised dependency | Lockfiles, `composer audit` + `npm audit`, secret scanning, protected prod builds |

---

## 2. Identity and access

- **Invite-only** staff creation; no public staff registration.
- **MFA required** for tenant owner, finance approver (refunds/settlements), network admin, and platform operator. Email/phone verification per role.
- **Re-authenticate** at the moment of use for: secret reveal (router/upstream credential), refunds, wallet adjustment/funding, role elevation, API-credential creation, and mass exports. Possession of the permission is not enough.
- **Deny by default** in policies. Test both object-level (can I read *this* record) and function-level (can I call *this* action) authorization.
- **Break-glass platform access:** a platform operator reaching into a tenant must supply a reason, is time-bounded, is visible to the tenant owner, and is fully audited. No standing access to tenant financial/customer data.
- Session/device revocation on role or tenant suspension.

## 3. Tenant and reseller isolation

- Resolve tenant from authenticated membership/host — **never** from an untrusted body field.
- Route-model binding is tenant/partner scoped; nested resources verify their parent belongs to the same tenant.
- Partner descendant queries go through the tested hierarchy service over `path`/`depth`, never arbitrary IDs.
- **Field-level protection:** wholesale cost, wallet, supplier, internal notes, and credential fields have explicit per-resource serializers/permissions; they are never serialized merely because they were eager-loaded.
- Cross-tenant/cross-partner access returns **404**, not 403 — never leak existence.
- Exports and queued reports re-check scope both at generation and at download time.

## 4. Financial integrity

- All balance-affecting actions are transactional, idempotent, row-locked, journaled, and audited (`02 §9`, `02 §15`).
- **No direct balance-edit endpoint.** Adjustments are reasoned, permissioned journal postings, with approval above thresholds.
- Issued invoices/receipts/journal entries use **reversal**, never update/delete.
- Backdating is permissioned and stores both the effective time and the actual creation time.
- Approval thresholds for discounts, refunds, write-offs, wallet funding, inventory adjustments, and negative-margin sales.
- **Daily automated invariants** (alert on failure, never auto-fix): balanced journals; payment-allocation sums ≤ payment/invoice; invoice balances; wallet cache == wallet lines; cash-session totals; inventory conservation; receivable == customer balance == statement projection.

## 5. Router and integration security

- Encrypt secrets with application encryption + a key-rotation strategy; prefer an external secret store in SaaS production. Ship the `APP_KEY` rotation + re-encryption command in Phase 1, not after a leak.
- **Never log** Authorization headers, RADIUS secrets, router passwords, full PPP passwords, gateway payload secrets, or upstream-credential plaintext. Configure log scrubbing explicitly and test it.
- **SSRF is a first-class risk** because tenants configure router endpoints: resolve and validate the target IP, enforce a tenant allowlist, block loopback/link-local/cloud-metadata/unapproved-private ranges, block redirects, and revalidate at connect time.
- Dedicated **least-privilege** RouterOS account (never the default admin) and certificate validation on HTTPS REST.
- Adapter commands are **typed and allowlisted** (activate, suspend, change profile, disconnect, rotate credential, fetch status). **No arbitrary shell/CLI field** in ordinary UI or public API.
- Redact vendor responses before persistence and display.
- If an outbound site connector is built, its command/result payloads are signed (mTLS), builds are signed, and there is a revocation mechanism (`03 §5.6`).

## 6. Application and API security

- Form Requests / typed DTOs validate every write; allowlist sort/filter/include fields (`04`, `spatie/laravel-query-builder`).
- Parameterized queries only; raw expressions require review and never interpolate untrusted identifiers.
- CSRF for cookie requests; CORS explicit and narrow; secure cookies, HSTS, CSP, clickjacking and MIME protections.
- File uploads: size/type/extension checks, random object keys, private storage, malware scan/quarantine, signed expiring download links.
- Per-actor and per-IP rate limits, with stronger limits on auth, OTP, export, bulk notify, webhook, and network actions (`04 §9`).
- Generic external errors with a request ID; detailed internal logs redacted.
- Structured audit for login, access denial, role/permission change, financial mutation, export, secret reveal, integration change, network action, and delete/archive.

## 7. Privacy and retention

- Data minimization; avoid collecting national IDs unless a business/legal requirement exists. Keep internal vs customer-visible notes/attachments separate.
- Retention schedules by data class; a legal/financial hold overrides deletion.
- Customer **anonymization** workflow nulls name/phone/email/national-ID/media and keeps the financial record with a placeholder label; logs the request and operator (`02 §5.6`). Only run where legal retention permits.
- Encrypted backups and tested restore; deletion from live data does **not** imply immediate backup erasure.

---

## 8. Production topology

```mermaid
flowchart TD
    LB[HTTPS reverse proxy / TLS] --> APP[Laravel app instances (PHP-FPM)]
    APP --> PG[(PostgreSQL 17 + PITR)]
    APP --> REDIS[(Redis, auth, private net)]
    APP --> OBJ[(Private S3-compatible object storage)]
    REDIS --> WORK[Horizon workers]
    REDIS --> REV[Reverb]
    WORK --> EXT[Allowlisted integrations / private network / connector]
```

Minimum services: TLS reverse proxy (Nginx/Caddy or managed LB); PHP-FPM app; PostgreSQL 17 with WAL archiving / PITR; authenticated Redis on a private network; **separate Horizon queues** — `critical`, `billing`, `provisioning`, `notifications`, `imports`, `reports`, `default` (mirrors `03 §6`); one scheduler process; Reverb as a supervised process if realtime is enabled; private object storage + backup destination.

### Deployment rules

- Build immutable artifacts from a tagged commit; never run frontend builds on the production host.
- Migrations are a controlled release step; **expand → migrate → contract** for breaking schema changes (add nullable, backfill in a separate command, then constrain). Never a destructive one-step migration on production volume.
- `config:cache`, `route:cache`, `event:cache` where compatible; graceful worker/Reverb restart after deploy.
- Health endpoints separate **liveness** and **readiness**; readiness checks DB/Redis without mutating.
- Feature flags gate unfinished migrations/integrations. Rollback plan covers code and forward-compatible schema — never assume a DB down-migration is safe in production.

**Market reality:** many of these shops self-host on hardware with unreliable power/connectivity. The app must survive a briefly unreachable database without corrupting state (transactions + retries), backups must be off-site and automatic, and a single-command restore must be documented and *rehearsed*.

---

## 9. Observability

- **Structured JSON logs** with timestamp, environment, `tenant_id`, `user_id`, `request_id`/correlation id, `job_id`, `service_id`, module, severity — sensitive fields redacted.
- **Metrics:** request latency/errors, DB connections/slow queries, cache hit rate, queue depth/age/failures, scheduled-job last success, provisioning success/latency **by adapter**, notification delivery, webhook failures, reconciliation-invariant failures.
- **Alerts** (each distinct and actionable within 5 min): no scheduler heartbeat (`spatie/laravel-schedule-monitor`), critical queue age, repeated provisioning failure, DB/Redis unavailable, disk/object-store errors, backup failure, journal-invariant failure, abnormal secret-reveal/export activity.
- **Exception tracking** (Sentry or equivalent) with PII scrubbing on.
- `/up` liveness plus a deeper `/health` reporting DB, Redis, queue depth, oldest queued network command, and last successful scheduled run.
- In-app **business-metrics dashboard** (active/suspended/reactivated today, collection rate, failed commands, drift) — these tell you the system is actually working and belong in the product, not just the monitoring tool.
- Admin status page shows integration health **without** exposing credentials.

---

## 10. Backup and disaster recovery

- Encrypted daily full **plus** continuous WAL / frequent incremental DB backups per hosting capability.
- Object-storage versioning/retention; an off-site copy in a separate failure domain.
- **Targets** (product owner must approve): suggested **RPO ≤ 15 min** for finance, **RTO ≤ 4 h** for initial launch.
- Automated backup-success alerts and a **monthly restore drill** to an isolated environment.
- The recovery runbook documents restoring: DB, files, **encryption keys**, environment secrets, DNS/TLS, worker configuration, and router/connector trust.

---

## 11. Runbooks

The concrete, numbered, tested procedures live in `07 §6` and `docs/runbooks/`. The load-bearing ones:

1. Router unreachable — identify affected services, **pause enforcement for that router** (don't mass-suspend during an outage), replay commands after recovery.
2. Queue stuck / backlog growing — diagnose, scale workers, clear poison jobs safely.
3. Ledger/journal invariant alert — investigate **without mutating**; write the correcting reversal.
4. Restore from backup — full, tested, timed, on a clean host.
5. Mass reactivation after a billing error — the "we suspended 400 people by mistake" procedure + customer comms template. (This is the most common serious incident in this category; have it written before it happens.)
6. `APP_KEY` rotation + credential re-encryption.
7. Onboarding a new tenant — settings, plans, routers, import, pilot checklist.

---

## 12. Security release gate (Phase 10)

Before pilot (`06 ISP-100`, ISP-102, ISP-103):

- Dependency + secret scan clean (no high/critical); protected production build.
- Security headers (CSP, HSTS, X-Frame-Options) verified; session hardening.
- `APP_KEY` rotation exercised end-to-end in staging.
- Permission/capability matrix re-verified (allow **and** deny).
- PII anonymisation action exercised.
- Restore-from-backup performed and timed by someone who didn't write the runbook.
- Killing the queue worker, the scheduler, or a router each produces a distinct, actionable alert.
- Every daily financial invariant runs green over the demo seed.
