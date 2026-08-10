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
| ISP-034/035/036 | Payment renewal, cash shifts and tenant-aware idempotent billing runs | delivered |
| ISP-041/043/044 | Driver boundary, post-commit network outbox, stale guards, retries, overdue suspension and reactivation | delivered with manual/fake drivers |
| ISP-050/051 | Template rendering, idempotent message queueing and provider delivery boundary | delivered with null/fake providers |
| ISP-060/061/062 | Ticket lifecycle, work-order completion and serialized inventory assignment | delivered |
| ISP-070 | Sanctum API, cursor pagination, query whitelists, idempotency middleware and OpenAPI slice | delivered for customers/payments |
| ISP-080/082/083 | FreeRADIUS sync, current sessions, stale-session handling and daily usage rollups | delivered |
| ISP-040/063 | POP/router inventory, encrypted connection tests and repeated-failure incidents | foundation delivered |
| ISP-090/091 | Basic partner hierarchy/wallets and tenant finance report | foundation delivered |
| ISP-P1-01 | Supplier credential inventory, import, assignment and audited reveal | foundation delivered |

## In progress

- Real RouterOS/CoA integration against a CHR lab, automatic reconciliation and device inventory healing.
- Provider integrations (WhatsApp/SMS/email/FCM), callbacks, fallback policy and notification automations.
- Customer portal OTP/session flow, collector/technician sync APIs and realtime events.
- Full reseller price books/commissions/settlements, XLSX/import workflows and expanded reports.
- SLA/business-hours ticketing, monitoring retention/alerts and dashboard attention queues.
- Security operations: key rotation, backup/restore rehearsal, dependency audit and Sentry wiring.

## Deliberately not claimed yet

Production credentials, provider approvals, RouterOS CHR lab acceptance, mobile offline sync and pilot readiness remain external gates. The repository contains tested seams and fakes for these boundaries; it does not claim those external integrations are production-ready.
