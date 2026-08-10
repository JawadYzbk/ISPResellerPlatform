# Implementation status

Last updated: 2026-08-10

## Delivered

| Ticket | Slice | Status |
|---|---|---|
| ISP-001 | Laravel 13 runtime, React/Inertia frontend, Docker, CI | foundation delivered |
| ISP-002 | Tenant context and integer money primitive | foundation delivered |
| ISP-003 | Initial architecture conventions and tests | foundation delivered |
| ISP-010 | Tenant, branch, zone and document-sequence schema | foundation delivered |
| ISP-013 | Model scope plus route-binding isolation proof | foundation delivered |
| ISP-014 | RTL-aware Inertia app shell and staff navigation | foundation delivered |
| ISP-020/021 | Customer seed, index/show screen, staff create/edit flows, tenant-safe zone/status/expiry filters and selectable columns, real activity timeline, persisted provisioning details, recent support-ticket context and tenant-isolated search entry point | vertical slice delivered; bulk actions, saved views, map/GPS and document workflows remain |
| ISP-010/011/012/015 | Tenant provisioning and capability-gated settings, operator directory/invitations, capability catalog, Sanctum tokens, 2FA, re-authentication, session devices and audit events | delivered for current tenant/operator scope |
| ISP-022/023/024 | Versioned plan prices, tenant-safe plan catalog/create workflow, customer creation/phone normalization, service transitions and billing-period arithmetic | delivered |
| ISP-030/031/032/033 | Historical FX with capability-gated effective-dated rate administration, append-only double-entry ledger, invoice lifecycle, idempotent payments and reversals, staff invoice/payment queues, tenant-safe invoice/receipt details, browser print layouts, invoice-balance validation and recent-auth reversal controls | delivered for current billing scope; server PDF engine and credit notes remain |
| ISP-034/035/036 | Payment renewal, current-cashier cash-shift reconciliation with variance controls, and scheduled tenant-aware idempotent billing runs | delivered for current billing scope |
| ISP-041/043/044 | Driver boundary, post-commit network outbox, stale guards with 10s/60s/300s retry backoff, overdue suspension and reactivation, authorized service re-sync, network command queue, router operations health surface and RouterOS comment/device-reference hardening | delivered with manual/fake drivers plus a configured external OSS/UISP/ACS webhook adapter; real endpoint and device acceptance remain external |
| ISP-050/051/052 | Template rendering, idempotent message queueing, configured WhatsApp/SMS/FCM/email adapter seams, signed delivery callbacks, tenant-local expiry reminders, customer welcome, payment receipt, service suspension/reactivation notices with opt-out/channel selection and configured-channel fallback, plus scoped zone/POP outage broadcasts | delivered at adapter/automation level; provider credentials remain external/in progress |
| ISP-060/061/062 | Ticket lifecycle, tenant-timezone business-hours SLA clock, scheduled resolved-ticket auto-close, tenant-safe staff ticket queue/detail/reply/status/assignment workflow, public versus internal message visibility, customer portal ticket messages, work-order queue/detail/history/checklist/completion, and serialized inventory assignment | delivered for current ticket/work-order scope; calendar, media/signature and material consumption remain |
| ISP-070/071/072 | Sanctum API with role-scoped abilities, cursor pagination, query whitelists, idempotency middleware, app version config, customer portal OTP/session, profile/services/usage/notices/tickets/billing APIs and portal UI, collector batches, offline bootstrap/delta/push sync, service discovery/state actions and OpenAPI slice | delivered for current customer/payment/service/collector/portal scope |
| ISP-073 | Technician-assigned work-order list/detail endpoints, tenant-safe staff work-order completion surface, idempotent completion, separate image upload, service diagnostics and serialized inventory operations/assignment view | delivered for current technician scope |
| ISP-074 | Tenant-private service status broadcast events with after-commit dispatch, public-ID channel authorization, Laravel Reverb transport configuration and authenticated React client refresh bridge | delivered in repository; TLS, process supervision and runtime rollout remain operations gates |
| ISP-080/081/082/083 | FreeRADIUS sync, encrypted router CoA settings, UDP CoA/Disconnect client with response validation, current sessions, scheduled stale-session cleanup, daily usage rollups, cycle quota/FUP command foundation, RouterOS FUP profile driver path and idempotent warning notifications | foundation delivered; CoA driver path delivered, lab acceptance pending |
| ISP-040/063 | POP/router inventory and capability-gated onboarding, encrypted connection tests, repeated-failure incidents, bounded router health observations and tenant-safe staff router operations page | delivered for current registry scope |
| ISP-090/091 | Partner hierarchy/wallets with descendant-scoped API, effective price books, immutable commission accruals, settlement statements, tenant finance and operations reports with CSV/XLSX export, aging, collection rate, plan/zone/POP revenue, upstream cost/margin, collector performance, retention and usage breakdowns | delivered for current commercial/reporting scope |
| ISP-092 | Deferred owner dashboard metrics, manager attention queue with deep links, plus live NOC signal panels for routers, sessions, commands, drift and incidents | delivered for current dashboard scope |
| ISP-093 | Customer, plan, service, serialized-equipment and journal-backed balance CSV/XLSX import preview, row-level validation, partial-success commit and guarded/reversing rollback API, plus RouterOS PPP subscriber discovery import with redacted reports | delivered for current tabular/router-discovery scope |
| ISP-P1-01 | Supplier credential inventory web surface, import, assignment and audited reveal | inventory and tenant-safe assignment surfaces delivered; import/reveal rollout remains controlled |
| ISP-100 | Security headers, dependency audits, session hardening, transactional APP_KEY credential re-encryption command and audited customer PII anonymization | key rotation and PII anonymization delivered; disposable local APP_KEY rotation rehearsal passed; coordinated staging rotation, permission-matrix proof and remaining launch controls remain |
| ISP-102/103 | Encrypted backup package configuration, dependency health, scheduler/queue-worker heartbeats, daily ledger invariant checks and privacy-safe Sentry wiring | repository wiring delivered; local encrypted SQLite and disposable Docker PostgreSQL/MinIO backup/restore rehearsals passed; off-site retention, production secret recovery, second-person restore and external alert routing remain |
| ISP-101 | Lazy Inertia page chunks and production frontend bundle audit | coherent 200-customer demo seed plus reproducible 50k-service seed/benchmark commands delivered; isolated PostgreSQL 18.4 and PostgreSQL 17 50k data/query-plan benchmarks passed; production-shaped pool and external cold-cache acceptance remain |
| ISP-104 | Administrator, operator and field-app guides, mobile API handover, deployment runbook and incident runbook | repository documentation plus screenshot-led local operator walkthrough delivered; approved-tenant execution, production sign-off and pilot handover remain |

## In progress

- Real RouterOS/CoA integration against a CHR lab remains an acceptance gate; scheduled subscriber reconciliation reports drift, while device-side disablement has an explicit opt-in healing command.
- Real provider credentials/approvals.
- Customer self-service online payments through a real gateway and realtime deployment rollout.
- Monitoring alert routing and external dashboard signal delivery.
- Security operations: deployment-shaped PostgreSQL/object-media backup restore rehearsal, off-site retention and Sentry project validation; the local encrypted SQLite rehearsal passed.
- PostgreSQL 17 production-shaped pool and infrastructure-level cold-cache acceptance against the 50k-service seed; the isolated PostgreSQL 17 data/query-plan and post-restart evidence is recorded in the performance runbook.

## Deliberately not claimed yet

Production credentials, provider approvals, RouterOS CHR lab acceptance, native mobile offline storage/sync integration and pilot readiness remain external gates. The repository contains tested API seams and fakes for these boundaries; it does not claim those external integrations are production-ready.
