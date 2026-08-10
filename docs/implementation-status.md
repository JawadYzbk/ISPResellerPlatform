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
| ISP-020/021 | Customer seed, index and show screen | first vertical slice delivered |
| ISP-011/012/015 | Capability catalog, invitations, Sanctum tokens, 2FA, re-authentication, session devices and audit events | delivered |
| ISP-022/023/024 | Versioned plan prices, customer creation/phone normalization, service transitions and billing-period arithmetic | delivered |
| ISP-030/031/032/033 | Historical FX, append-only double-entry ledger, invoice lifecycle, idempotent payments and reversals | delivered |
| ISP-034/035/036 | Payment renewal, cash shifts and scheduled tenant-aware idempotent billing runs | delivered |
| ISP-041/043/044 | Driver boundary, post-commit network outbox, stale guards, retries, overdue suspension and reactivation | delivered with manual/fake drivers |
| ISP-050/051/052 | Template rendering, idempotent message queueing, configured WhatsApp/SMS/FCM/email adapter seams, signed delivery callbacks, tenant-local expiry reminders, customer welcome, payment receipt, service suspension/reactivation notices with opt-out/channel selection and configured-channel fallback, plus scoped zone/POP outage broadcasts | delivered at adapter/automation level; provider credentials remain external/in progress |
| ISP-060/061/062 | Ticket lifecycle, tenant-timezone business-hours SLA clock, scheduled resolved-ticket auto-close, work-order completion and serialized inventory assignment | delivered |
| ISP-070/071/072 | Sanctum API with role-scoped abilities, cursor pagination, query whitelists, idempotency middleware, app version config, customer portal OTP/session and billing history, collector batches, offline bootstrap/delta/push sync, service discovery/state actions and OpenAPI slice | delivered for current customer/payment/service/collector scope |
| ISP-073 | Technician-assigned work-order list/detail endpoints, idempotent completion, separate image upload, service diagnostics and van inventory | delivered for current technician scope |
| ISP-074 | Tenant-private service status broadcast events with after-commit dispatch, channel authorization and Laravel Reverb transport configuration | delivered in repository; TLS/process supervision/client rollout remain operations gates |
| ISP-080/081/082/083 | FreeRADIUS sync, encrypted router CoA settings, UDP CoA/Disconnect client with response validation, current sessions, scheduled stale-session cleanup, daily usage rollups, cycle quota/FUP command foundation, RouterOS FUP profile driver path and idempotent warning notifications | foundation delivered; CoA driver path delivered, lab acceptance pending |
| ISP-040/063 | POP/router inventory, encrypted connection tests, repeated-failure incidents and bounded router health observations | foundation delivered |
| ISP-090/091 | Partner hierarchy/wallets with descendant-scoped API, effective price books, immutable commission accruals, settlement statements, tenant finance and operations reports with CSV/XLSX export, aging, collection rate, plan/zone/POP revenue, upstream cost/margin, collector performance, retention and usage breakdowns | delivered for current commercial/reporting scope |
| ISP-092 | Deferred owner dashboard metrics, manager attention queue with deep links, plus live NOC signal panels for routers, sessions, commands, drift and incidents | delivered for current dashboard scope |
| ISP-093 | Customer, plan, service, serialized-equipment and journal-backed balance CSV/XLSX import preview, row-level validation, partial-success commit and guarded/reversing rollback API, plus RouterOS PPP subscriber discovery import with redacted reports | delivered for current tabular/router-discovery scope |
| ISP-P1-01 | Supplier credential inventory, import, assignment and audited reveal | foundation delivered |
| ISP-100 | Security headers, dependency audits, session hardening and transactional APP_KEY credential re-encryption command | key rotation delivered; remaining launch controls in progress |
| ISP-102/103 | Encrypted backup package configuration, dependency health, scheduler/queue-worker heartbeats, daily ledger invariant checks and privacy-safe Sentry wiring | repository wiring delivered; off-site storage, restore rehearsal and external alert routing remain |
| ISP-101 | Lazy Inertia page chunks and production frontend bundle audit | repository optimization delivered; 50k-service query benchmark and EXPLAIN review remain |

## In progress

- Real RouterOS/CoA integration against a CHR lab remains an acceptance gate; scheduled subscriber reconciliation reports drift, while device-side disablement has an explicit opt-in healing command.
- Real provider credentials/approvals.
- Customer self-service online payments through a real gateway and realtime deployment rollout.
- Monitoring alert routing and external dashboard signal delivery.
- Security operations: backup/restore rehearsal and Sentry project validation.
- Performance acceptance against the 50k-service seed and ten-query EXPLAIN review.

## Deliberately not claimed yet

Production credentials, provider approvals, RouterOS CHR lab acceptance, native mobile offline storage/sync integration and pilot readiness remain external gates. The repository contains tested API seams and fakes for these boundaries; it does not claim those external integrations are production-ready.
