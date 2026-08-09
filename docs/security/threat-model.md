# Threat model baseline

## Assets

- Customer identity, contact details, location and service history.
- Payment records, future journal entries, balances and receipts.
- Router, PPPoE, RADIUS and upstream credentials.
- Desired network state and commands that may disconnect customers.

## Current controls

- Tenant-owned models fail closed without an explicit tenant context.
- Public ULIDs avoid exposing sequential customer/service IDs.
- Service credentials use Laravel encrypted casts and are hidden from serialization.
- The root app is invite-oriented; no public registration route exists.
- Money is represented as integer minor units and not floating point.
- CI runs tests, formatting, static analysis, typecheck and a production frontend build.

## Required controls before pilot

- Capability-based policies and a deny/allow matrix for every route group.
- Re-authentication and audit events for secret reveal, refunds, wallet funding and role elevation.
- Append-only double-entry journal with database immutability constraints.
- Outbox + queued typed network commands with SSRF protections and stale desired-state guards.
- Redacted structured logs, security headers, rate limits, backup restore rehearsal and secret scanning.
