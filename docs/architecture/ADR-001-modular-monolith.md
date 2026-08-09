# ADR-001: Modular monolith first

Status: accepted  
Date: 2026-08-10

## Context

The platform coordinates subscriber records, money, field operations and router control. Splitting these into services before the transaction boundaries are proven would make the core payment → entitlement → provisioning loop harder to reason about.

## Decision

Build a modular monolith in Laravel. Controllers are transport adapters, Actions own domain transactions, Jobs own asynchronous execution, and network/message gateways sit behind interfaces. Modules may share public domain contracts but may not reach into another module's private persistence by raw SQL.

## Consequences

We keep one deployable artifact and one authoritative database while the product is small. The boundaries remain explicit enough to extract a queue consumer or integration service later. Queue names and outbox records preserve the seams for that future split.
