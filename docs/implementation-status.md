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

## In progress

- Capability catalog and invitation-based identity flows.
- Full customer create/edit workflow and phone normalization package.
- Versioned plans/prices and service transition actions.
- Ledger, invoices, payments and the payment-to-renewal loop.

## Deliberately not claimed yet

Router control, provisioning commands, financial posting, online gateways, SMS/WhatsApp, supplier credentials and mobile offline sync are not implemented in this checkpoint. Their interfaces will be added only alongside the corresponding migrations, actions, policies, jobs and invariant tests from `plan1/06-build-plan.md`.
