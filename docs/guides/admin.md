# Administrator handover

This guide covers the current tenant administrator surface. It is written for the person responsible for access, tenant configuration, release coordination, and operational safety.

## Access model

Every staff request is evaluated inside the authenticated user’s tenant. Use the narrowest role that matches the job:

| Role | Use for |
| --- | --- |
| `tenant_owner` | Full tenant administration and approval responsibility |
| `operations_manager` | Customers, services, plans, tickets, work orders, inventory and operations reporting |
| `billing_manager` | Invoices, payments, adjustments, refunds, wallets, settlements and finance reporting |
| `cashier` / `collector` | Customer lookup and payment collection |
| `support_agent` | Customer/service visibility, network visibility and ticket handling |
| `technician` | Assigned work orders, diagnostics, inventory and completion evidence |
| `network_administrator` | Network state changes, provisioning and controlled credential access |
| `auditor` | Read-only operational, finance and audit visibility |

Do not share staff accounts. API tokens should be device-specific and issued with one of `staff:collector`, `staff:technician`, or `staff:operator`. Revoke a token with `DELETE /api/v1/tokens/current` when a device is lost or retired.

## First tenant setup

1. Copy `.env.example` to `.env`, generate `APP_KEY`, configure the database, cache, queue, mail and object-storage credentials, then run migrations and seed the capability catalog.
2. Confirm the tenant timezone, collection currency, grace-period policy, notification preferences, and router/POP inventory before importing services.
3. Create or invite staff through the existing user-management integration and assign the role that matches the table above. Verify tenant isolation with a non-owner account before production use.
4. Configure Sentry, encrypted backups, Reverb, provider credentials and payment gateway credentials only in the deployment secret store. Never place these values in source control or support tickets.

## Two-factor status

The two-factor setup and challenge routes remain available, but web enforcement is currently off by default to keep local development and the current rollout unblocked. Set `SECURITY_ENFORCE_TWO_FACTOR=true` and restart all PHP/queue/scheduler processes to require verified 2FA for the web application. API token issuance still requires a valid OTP when the user’s policy requires it.

## Safe administrative changes

- Prefer migrations and configuration changes over direct production database edits.
- Treat ledger rows, payments, allocations, and audit events as append-only evidence. Use an approved adjustment workflow for corrections.
- Treat service commercial status and network state as separate. A paid service may remain operationally drifted until the outbox command succeeds.
- Require re-authentication and the appropriate capability before revealing upstream credentials. Record the reason and ticket reference in the audit trail.
- Use the customer **Anonymize** action only where legal retention permits. It requires recent re-authentication, replaces personal fields with a non-personal placeholder, preserves financial and service history, and records a privacy-safe audit event. The action is irreversible.
- Use idempotency keys for payment, activation, suspension, settlement payment, import rollback, and network retry requests.

## Release checklist

Before approving a release, confirm:

- migrations are reviewed and run with `--force` only in the intended environment;
- `php artisan migrate:status`, `/up`, and `/api/v1/health` are healthy;
- the queue worker and scheduler are running;
- `php artisan ledger:check-invariants` returns `ok` for every tenant;
- `php artisan backup:list` shows a recent usable backup and the restore rehearsal record is current;
- the deployment has a rollback plan that does not rewrite ledger or customer history.

See [tenant onboarding](../runbooks/tenant-onboarding.md), [deployment](../runbooks/deployment.md), [backup and restore](../runbooks/backup-restore.md), [mass reactivation](../runbooks/mass-reactivation.md), and [observability](../runbooks/observability.md) for the operational procedures.
