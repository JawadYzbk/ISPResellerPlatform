# New tenant onboarding

Use this checklist for a new ISP/reseller tenant. The tenant owner and the implementation operator should sign the checklist before importing live customers or connecting a production router.

## Provision the tenant boundary

1. Obtain the approved tenant name, unique slug, base currency, collection currency, timezone, locale, billing policy, and primary owner details.
2. In development or an approved internal environment, sign in as the platform operator and use **Admin → Tenants** (`/admin/tenants`) to create the workspace and its first owner. Production automation may use the same `CreateTenant` action through a reviewed integration. Do not create tenant-owned rows with ad-hoc SQL or a production copy of the demo seeder.
3. Confirm tenant creation ran the built-in provisioner. It creates the default branch and zone, document sequences, currencies, and baseline ledger accounts inside the new tenant context.
4. In the deployment environment, run reviewed migrations and seed the global capability catalog when required:

   ```text
   php artisan migrate --force
   php artisan db:seed --class=CapabilitySeeder --force
   ```

   Do not run `DatabaseSeeder` against a production database; it is a demo fixture.

## Configure the operating model

1. Set and verify timezone, locale, date/time formats, base and collection currency, grace-period behavior, notification quiet hours, notification channels, and customer self-service policy.
2. Add POPs, branches, zones, service plans, taxes/fees, invoice numbering expectations, and approved price books before importing services.
3. Invite the owner and staff through the invitation flow. Assign the least-privileged role, require separate accounts, and verify that a non-owner cannot read another tenant’s data.
4. Place payment, notification, Reverb, Sentry, object-storage, backup, and upstream/provider credentials in the deployment secret store. Never put credentials in imports, source control, screenshots, or support tickets.

## Import and connect infrastructure

1. Import plans first. Preview the import and resolve every validation error before committing it.
2. Import customers, then services, serialized equipment, and opening balances in that order. Keep the import batch IDs and preview reports with the onboarding record.
3. Add routers and POP assignments. Use encrypted credential fields, test connectivity against the approved lab or maintenance target, and record the router software/version and adapter used.
4. Run `routers:reconcile-subscribers` in report-only mode. Review the difference before using `--heal`; a heal requires an approved maintenance or incident record.
5. Verify a representative active, suspended, overdue, paid, and network-drifted service. Confirm customer, plan, service, invoice, payment, journal, outbox, and notification records remain inside the tenant boundary.

## Acceptance and handover

1. Confirm `/up`, `/api/v1/health`, queue-worker heartbeat, scheduler heartbeat, and a controlled test notification from **Settings → WhatsApp setup** when WhatsApp is enabled.
2. Run `php artisan ledger:check-invariants` and retain an `ok` result for the tenant.
3. Make one controlled payment in the approved test path, confirm the receipt and journal entry, and reconcile the customer balance. Never use a real payment during a lab test.
4. Exercise one service activation, one suspension, one reactivation, and one network retry. Confirm queued commands are idempotent and visible to the operator.
5. Confirm backup configuration, recent backup visibility, isolated restore rehearsal status, alert ownership, and incident contacts.
6. Run the tenant readiness report and attach its JSON output to the handover record:

   ```text
   php artisan platform:tenant-readiness northline --json
   ```

   Resolve every `FAIL`. Review every `WARN`; add `--strict` when logo, payment provider, and notification setup are mandatory for the pilot.
   The same checklist is available to the tenant owner at **Settings → Pilot readiness**.
   The report also verifies the 27 active notification templates for the tenant locale; rerun `CapabilitySeeder` if that check reports missing templates.
7. Start the pilot only after the owner signs off the customer import, router scope, payment/notification behavior, rollback plan, and two-week monitoring owner. Record the tenant public ID, not internal database IDs, in the handover document.
