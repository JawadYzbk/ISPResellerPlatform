# ADR-002: Shared-schema tenant isolation

Status: accepted  
Date: 2026-08-10

## Context

The platform serves multiple independent providers and needs cross-tenant platform reporting. Separate databases would complicate backups, migrations and reporting at the initial scale.

## Decision

Use one database with a `tenant_id` on tenant-owned tables. `BelongsToTenant` applies a global scope and stamps new rows from the `Tenancy` singleton. Authenticated requests establish context in `IdentifyTenant`; actions and policies must still verify tenant membership and ownership explicitly.

## Consequences

Normal model queries fail closed when context is absent. Super-admin access must be an explicit, audited scope bypass. Cross-tenant route binding returns 404, and the isolation suite tests direct queries, relationships, validation and route binding.
