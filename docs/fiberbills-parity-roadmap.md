# FiberBills parity roadmap

Last reviewed: 2026-08-13

## Objective

Make ISP Manager a production-usable, Lebanon-first web platform that meets or
exceeds FiberBills for local ISPs and resellers. Native Android/iOS applications
and Bluetooth thermal printing are a later phase; the responsive web/PWA field
desk and APIs must remain mobile-ready.

Parity is not visual imitation. A capability counts as delivered only when its
complete business workflow has tenant-safe authorization, a usable role-aware
UI, clear success and failure feedback, auditability, tests, and documented
production acceptance.

## Evidence and limits

This comparison uses FiberBills' public website, ISP billing page, mobile-app
page, Google Play listing, sitemap, public route metadata, and frontend bundle
module names. Public marketing claims are evidence of advertised scope. Routes
and module names indicate shipped client code but do not prove that a private
workflow works correctly in production. No private FiberBills tenant or customer
data was accessed.

Sources:

- <https://fiberbills.net/>
- <https://fiberbills.net/internet-billing>
- <https://fiberbills.net/mobile-app>
- <https://fiberbills.net/sitemap.xml>
- <https://fiberbills.net/robots.txt>
- <https://play.google.com/store/apps/details?id=net.fiberbills.app>

## Capability comparison

| Capability advertised or exposed by FiberBills | ISP Manager status | Product decision |
|---|---|---|
| Subscriber history, plans, contacts, status and expiry | Delivered | Keep ISP Manager's richer service lifecycle, documents, tickets and timeline. |
| Plan/speed, PPPoE credentials and bandwidth usage | Delivered/partial | Finish live-device acceptance and add an operator-friendly usage/FUP history. |
| Zone organization | Delivered | Retain tenant, branch, zone, POP and reseller scoping. |
| Box and building organization | Gap | Add building/site and distribution-box/cabinet entities, map placement, capacity and subscriber assignment. |
| Monthly auto-invoicing, grace and overdue handling | Delivered | Keep idempotent billing runs, ledger posting and network enforcement. |
| Custom billing cycle per subscriber | Partial | Add explicit anchor day, first-period proration, cycle preview and safe cycle change. |
| Subscription, usage-based and one-time billing combinations | Partial | Add recurring invoice items and a metered-rating engine over usage/readings; retain operator-created invoice lines. |
| Plan upgrades and renewals | Delivered | Keep immediate/next-cycle swaps, proration, multi-period renewal and router/RADIUS sync. |
| Bulk billing operations | Partial | Add a previewable bulk invoice/renewal desk with row-level outcomes and idempotent retries. |
| PDF invoices, receipts and Excel exports | Delivered | Add compact 58/80 mm browser print layouts now; Bluetooth printing remains mobile-phase work. |
| Revenue, pending, overdue and financial reports | Delivered | Keep allocation-backed aging, margin, ARPU, retention, usage and reseller reporting. |
| Cash collection and automatic reconciliation | Delivered | Keep append-only payments, cash shifts, allocations, reversals and daily collector totals. |
| Lebanon multi-currency collection | Exceeds reference | Keep USD/LBP/AED support, FX snapshots, Frankfurter import, manual rates, Whish, Stripe and configurable rounding including nearest LBP 5,000. |
| Collector due/overdue customer list and offline collection | Delivered | Keep the encrypted offline web queue and daily route-oriented execution with ordered customer stops. |
| Collector assignment to zones | Delivered | Scoped all-zone/restricted territories, descendant coverage, server-side sync/API enforcement and effective-dated reassignment history are implemented; temporary scheduled coverage remains part of route planning. |
| Live collector GPS, check-ins, routes and nearby dues | Delivered for responsive web | Explicit consent-based field-day check-in/out, GPS accuracy evidence, manager route planning, territory-safe ordered stops, on-demand nearby sorting and location-backed visit outcomes are delivered without continuous background tracking. Native background execution remains deferred. |
| Collector tasks, messaging and daily summaries | Delivered for responsive web | Managers can assign prioritized, due-dated, customer-linked tasks; collectors acknowledge/start/complete them in order; participant-only threads include unread state and private attachments; checkout persists route, collection, task, cash-shift and handover summaries. Native push/background behavior remains deferred. |
| Collector performance | Partial | Route progress and visit outcomes are now visible to managers; extend finance totals with collection rate, cash variance and historical trends. |
| Collector wallet/cash custody | Delivered for responsive web | Physical custody combines cash-only collections, opening float, manager advances, approved field expenses, confirmed handovers and documented adjustments in an append-only multi-currency position. Pending requests do not affect balances, debits cannot overdraw custody, and checkout snapshots the handover position. |
| Inventory and equipment assignment | Delivered | Keep serialized and bulk stock, warehouses, transfers, service assignment and movement audit. |
| Stock assigned to collectors and field sales | Partial | Add collector/vehicle stock locations, custody handover, returns, replenishment, sales and variance reconciliation. |
| Generic business expenses | Gap | Add categories, vendors, attachments, recurring expenses, approvals, cash/bank/collector payment sources and ledger posting. |
| Customer/admin WhatsApp and SMS notifications | Delivered/partial | Complete live provider acceptance; retain multi-account jobs, deduplication, pacing and audit history. |
| Customer portal and public invoice/payment access | Delivered/partial | Finish branded tenant-domain payment links, statement sharing, receipt access and production gateway acceptance. |
| Multi-company distributor operation | Delivered | Keep the stronger tenant and reseller hierarchy, wallets, price books, commissions and settlements. |
| Network integrations | Exceeds reference in RouterOS/RADIUS; gap in optical access | Complete RouterOS/CoA lab acceptance, then add OLT/ONU installation inventory and a vendor-neutral optical driver boundary. |
| Installations | Partial | Extend work orders into installation surveys, box/port/ONU assignment, optical readings and activation acceptance. |
| Activity log, security and trusted devices | Delivered | Keep audit events, re-authentication, 2FA and session-device controls; expose any missing owner-facing audit filters. |
| Backup/export controls | Backend delivered; UI partial | Add owner-visible backup health, last verified restore metadata and safe export controls without exposing secrets. |
| Electricity/generator meter and odometer billing | Deferred vertical | Build after core ISP/Jebaya parity as a modular reading, approval, rating and billing capability. |
| Satellite/cable subscriptions | Deferred vertical | Build after core ISP/Jebaya parity using the shared subscription engine, without polluting ISP network concepts. |
| Native collector/admin mobile application | Deferred | Implement after web production acceptance against the existing versioned APIs and offline sync contract. |
| Bluetooth thermal printing | Deferred with mobile | Preserve browser-printable compact receipts now; add device printing in the native client later. |

## Delivery sequence

### Phase 1: core ISP acceptance

1. Run role-by-role browser acceptance for setup, customer import, plan assignment,
   plan swap, invoice, cash/Whish/Stripe collection, receipt, cash-shift close,
   suspension/reactivation, ticket, work order, stock movement and reports.
2. Close incomplete UI/CRUD, authorization, toast, locale, navigation and responsive
   behavior before adding new breadth.
3. Complete live RouterOS/RADIUS, payment-provider, WhatsApp and backup/restore gates.

### Phase 2: Lebanese Jebaya operations

1. Collector territories and zone assignment.
2. Shift check-in/out, GPS consent, routes, nearby dues and stop outcomes.
3. Collector/admin tasks and messaging. **Delivered for responsive web.**
4. Collector cash custody, handover, expenses and reconciliation. **Delivered for responsive web.**
5. Collector stock custody, replenishment, return, sale and variance.
6. Daily route, collection and performance supervision.

### Phase 3: billing and finance breadth

1. Per-service custom billing anchors and cycle-change previews.
2. Recurring invoice items, usage rating and safe bulk billing.
3. Generic operational expenses with approval and ledger posting.
4. Branded public payment/statement links and compact browser receipts.
5. Owner-facing backup health and audited business exports.

### Phase 4: physical ISP topology

1. Buildings/sites, boxes/cabinets, ports, capacity and map views.
2. Installation survey and activation workflow.
3. OLT/ONU registry, optical readings and vendor-neutral integration drivers.
4. Usage/FUP and network-quality history tied to customers and topology.

### Phase 5: adjacent recurring-service verticals

1. Electricity/generator meter definitions, reading capture, approval, correction,
   rounding, consumption rating and invoice generation.
2. Satellite/cable packages, receivers/cards, renewal and suspension workflows.

### Later mobile phase

Build native collector and admin clients only after the responsive web platform
passes a real tenant pilot. Reuse the versioned APIs, idempotency keys, offline
delta sync and device revocation contracts. Add OS-backed encrypted storage,
background sync, push notifications, camera/GPS workflows and Bluetooth 58/80 mm
printing in that phase.

## Completion standard

The web goal is complete only when an approved Lebanese ISP can onboard a tenant,
import and organize subscribers, provision and monitor service, bill in the needed
currencies, collect and reconcile money in the office and field, manage stock and
expenses, communicate with customers and staff, resolve support/network work, and
close the month with trustworthy reports and a tested recovery path without using
the database, source code or `.env` for routine operations.
