# Domain glossary

- **Tenant** — an independent ISP/reseller workspace. Every tenant-owned record carries `tenant_id` and is protected by model scope, explicit context and policy checks.
- **Customer** — the person or organization that receives service. A customer may own multiple services.
- **Service** — one connection/subscription belonging to a customer. Commercial status and network state are separate.
- **Plan** — a versionable commercial offer with rate limits, duration, integer minor-unit price and currency.
- **Provisioning mode** — how a service is fulfilled: manual, upstream credential, MikroTik, RADIUS or external.
- **Desired state** — the database-authoritative network intent. A router is an executor/observer, never the source of truth.
- **Outbox** — a committed record of external work that can be relayed and retried without rolling back finance or entitlement state.
- **Minor unit** — the integer representation of money in an ISO currency. USD 12.50 is `1250`, never a float.
