# ISP Manager — Planning Repository

This repository holds the **build handoff pack** for the ISP/reseller management platform
(Laravel 13 + Inertia v3 + React 19 + Tailwind 4, on PostgreSQL 17 + Redis).

## Where to start

👉 **[`plan1/00-START-HERE.md`](plan1/00-START-HERE.md)** — read this first. It lists the reading
order, the baked-in assumptions, the guardrails, and the decision log.

The pack (`plan1/`) is the **single source of truth**. It was merged from two source plans — a
build-handoff pack and a research-backed architecture brief — into one consolidated set of files:

| File | What it is |
|---|---|
| `plan1/00-START-HERE.md` | Assumptions, guardrails, decision log, ADR list, agent handoff prompt |
| `plan1/01-product-spec.md` | Personas, capability model, modules, provisioning modes, user stories, business rules |
| `plan1/02-domain-model.md` | Full schema, double-entry journal, state machines, invariants |
| `plan1/03-architecture.md` | Versions, packages, modular-monolith boundaries, network control layer, outbox |
| `plan1/04-api-spec.md` | Versioned REST `/api/v1` contract for mobile + portal |
| `plan1/05-frontend-spec.md` | Inertia/React setup, page inventory, component library, UX rules |
| `plan1/06-build-plan.md` | Phased ticket queue (ISP-001…) with acceptance criteria |
| `plan1/07-conventions-and-testing.md` | Code standards, tests, Definition of Done, CI |
| `plan1/08-suppliers-credentials-wallets.md` | Suppliers, upstream-credential inventory, price books, settlement — **P1, post-v1** |
| `plan1/09-security-and-operations.md` | Threat model, OWASP mapping, deployment, observability, DR, runbooks |

## Provenance

`archive/plan-2-research-brief.SOURCE.md` is the original research brief, kept for reference.
Its content has been folded into the pack above; where the two sources disagreed, the resolved
decisions are recorded in `plan1/00-START-HERE.md` §5 (marked `(merge)`).
