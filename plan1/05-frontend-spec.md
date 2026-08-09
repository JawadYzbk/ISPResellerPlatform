# 05 — Frontend Specification (Inertia v3 + React 19 + TypeScript)

## 1. Setup

```bash
npm i @inertiajs/react@^3 @inertiajs/vite
npm i -D vite@^7 typescript @types/react @types/react-dom
npm i tailwindcss@^4 @tailwindcss/vite
```

`resources/js/app.tsx` — with the v3 Vite plugin this is nearly empty:

```tsx
import { createInertiaApp } from '@inertiajs/react'
createInertiaApp()
```

Configure the plugin in `vite.config.ts` with the pages directory, code splitting, and SSR. Do not hand-roll `resolve()` / `setup()` — that's the v2 way and the plugin does it better.

**Server side, in `AppServiceProvider`:**
- `Inertia::encryptHistory()` globally for authenticated route groups.
- Share via `HandleInertiaRequests`: `auth.user` (slim), `auth.permissions` (string array), `tenant` (name, logo, base currency, timezone, locale, feature flags), `flash`, `locale`, `direction` (`ltr|rtl`), `unreadCounts`.
- Keep shared props **small**. They ride on every single response. No collections, no settings blobs, no plan lists.

---

## 2. Type safety across the boundary

- Backend DTOs use `spatie/laravel-data`. Run `php artisan typescript:transform` (or `spatie/laravel-typescript-transformer`) into `resources/js/types/generated.d.ts` as part of the build and in CI.
- Page components are typed against the generated types:

```tsx
type Props = { customer: App.Data.Customer.CustomerData; services: App.Data.Service.ServiceData[] }
export default function Show({ customer, services }: Props) { ... }
```

- `tsc --noEmit` is part of the Definition of Done. A page prop that drifts from its DTO must fail the build, not fail in production.

---

## 3. Layouts

| Layout | Used by | Characteristics |
|---|---|---|
| `AppLayout` | staff web app | Persistent sidebar + topbar, command palette, global search, tenant switcher (super admin only), notification bell, dense |
| `AuthLayout` | login, 2FA, password reset | Centered, tenant branding |
| `PortalLayout` | customer self-service | Simple, mobile-first, large type, one primary action per screen |
| `FieldLayout` | technician/collector web fallback | Big targets, bottom nav, offline banner |
| `PrintLayout` | invoice/receipt preview | |

Layouts are persistent (assigned via `Page.layout`) so the sidebar doesn't remount on navigation. Use v3 **layout props** (`useLayoutProps`) for per-page title, breadcrumbs, and header actions instead of prop-drilling.

---

## 4. Page inventory

Mirrors the route tree; `pages/` path matches the Inertia component name.

### Staff app
```
Dashboard/Index                     KPI tiles, expiring soon, collections today, network alerts
Customers/Index                     data grid: search, filter by zone/status/expiry, bulk actions
Customers/Create | Edit
Customers/Show                      ← the most important screen in the product
Services/Index                      cross-customer view, filter by router/plan/state/drift
Services/Show                       state machine, network commands, sessions, usage chart
Plans/Index | Create | Edit
Billing/Invoices/Index | Show | Create
Billing/Payments/Index | Create
Billing/Payments/Show                receipt, allocations, reversal
Billing/CreditNotes/Index
Billing/Shifts/Index | Show          collector reconciliation
Billing/ExchangeRates/Index
Reports/Revenue | Collections | Aging | Churn | UsageTop | MarginByPop | CollectorPerformance
Network/Routers/Index | Show | Create | Edit
Network/Pops/Index | Show
Network/Sessions/Index               live
Network/IpPools/Index
Network/Commands/Index               failed/queued, retry (the "provisioning operations" desk)
Network/Incidents/Index | Show
Suppliers/Index | Show               contracts, bills, credential utilisation
Suppliers/Credentials/Index          batches; import; reserve/assign; reveal (re-auth); state filter
Partners/Index | Show                wallet, customers, price book, commission, statements
Partners/Wallets/TopUps              funding request + approval
Support/Tickets/Index | Show
Support/WorkOrders/Index | Show | Calendar
Support/Outages/Index | Show
Inventory/Items/Index | Stock/Index | Movements/Index
AttentionQueue/Index                 manager cross-cutting queue (see §5a)
Settings/General | Billing | Network | Notifications | Templates | Roles | Users | Zones | Branches | CustomFields | Import
Admin/Tenants/Index | Show           super admin only (break-glass, audited)
```

### Customer portal
```
Portal/Login                         phone + OTP
Portal/Dashboard                     status card, expiry countdown, quota ring, pay button
Portal/Usage
Portal/Invoices/Index | Show
Portal/Payments/Index
Portal/Tickets/Index | Show | Create
Portal/Profile
```

---

## 5. The Customer Show screen (specified in detail because everything else orbits it)

Single page, no tab-hunting for the things you always need.

**Header bar (always visible):** name + code, phone with click-to-call and click-to-WhatsApp, zone, status pill, **balance** (large, red if owing), **expiry countdown** ("expires in 3 days" / "expired 6 days ago"), and three primary buttons: **Take payment**, **Renew**, **Open ticket**.

**Left column:**
- *Service card* per service — plan, speed, username, router, IP, status, `network_state` badge (with a "Re-sync" button when drifted), quota ring, session indicator (online/offline with uptime), and an actions menu (suspend, resume, change plan, disconnect session, change credentials).
- *Equipment* — assigned devices with serials, assignment date, and a "mark returned" action.
- *Location* — small map with the GPS pin, plus a directions link.

**Right column (tabs):**
`Timeline` (default) · `Invoices` · `Payments` · `Tickets` · `Work orders` · `Usage` · `Documents` · `Notes`

The **Timeline** is the merged feed: status changes, payments with amounts, invoices, tickets, network commands (with success/failure), messages sent (with delivery status), staff notes. Each entry shows the actor. This is the screen that answers "what happened to this customer" without asking four people.

**Take payment** is a modal, not a page: amount, currency (defaulting to the tenant's collection currency), FX rate (prefilled, editable with reason), method, allocation preview showing exactly which invoices/services it settles and the resulting new expiry date, then a confirmation showing the receipt and the reactivation status. It must be completable in under 10 seconds with a keyboard.

---

### 5a. Other high-value screens

These earn dedicated design attention alongside Customer Show:

1. **Global search / command palette** (`Cmd/Ctrl+K`) — resolves customer number, name, normalized phone, service username, IP, MAC, serial, invoice, receipt, ticket.
2. **Renewal desk** — scan/search → select service → preview totals + new expiry → payment → receipt → live provisioning result.
3. **Provisioning operations** (`Network/Commands`) — desired vs current state, pending/failed actions with a sanitized error, retry, jump to the related service.
4. **Cash close** — expected by denomination/currency/method, counted, discrepancy, handover approval.
5. **Manager attention queue** — one page aggregating: expired-but-still-active services, **paid-but-provisioning-failed**, unallocated payments, stale sessions, low reseller balances, expiring supplier credentials. This is the "what needs a human today" screen.

**Capability-driven UI:** actions and fields render only when the current user holds the capability (`auth.permissions` shared prop). Hiding a control is UX, not security — the server re-checks every mutation. Sensitive reveals (credentials, cost) additionally prompt for re-authentication before the value is fetched.

---

## 6. Component library

Build on shadcn/ui, then add these domain components. Each lives in `components/domain/`.

| Component | Responsibility |
|---|---|
| `<DataGrid>` | TanStack Table + server-driven filters/sort/cursor. URL-synced state (so a filtered view is shareable), column visibility, saved views, CSV export, row selection + bulk actions, sticky header, keyboard nav. **Build this once, use it 20 times — budget real time for it.** |
| `<MoneyInput>` | currency selector + minor-unit-safe integer input; never a float; handles 0/2/3-decimal currencies |
| `<MoneyDisplay>` | formats `{amount, currency}` per locale, optional base-currency tooltip |
| `<FxRateField>` | prefilled rate, override toggle, reason field, shows resulting base amount live |
| `<StatusBadge>` | service/invoice/ticket/network states with consistent colour semantics |
| `<NetworkStateBadge>` | shows drift explicitly: "Active (device says disabled) — Re-sync" |
| `<ExpiryCountdown>` | humanised, colour-coded, tenant-timezone aware |
| `<QuotaRing>` | used/total with FUP threshold marker |
| `<PhoneField>` | country-aware, normalises to E.164, shows WhatsApp/call actions |
| `<CustomerCombobox>` | async search by phone/name/code, shows balance and status inline |
| `<ServicePicker>` | for allocations |
| `<Timeline>` | polymorphic event feed with actor, icon, and relative time |
| `<CommandPalette>` | `Cmd/Ctrl+K` — global search + quick actions ("take payment for…", "suspend…") |
| `<MapPicker>` / `<MapView>` | Leaflet + OpenStreetMap (no API key, works everywhere); pin, drag, zone polygon |
| `<UsageChart>` | daily bytes, stacked in/out, cycle boundaries marked |
| `<SignaturePad>` | work order completion |
| `<PhotoUploader>` | compress client-side before upload, show progress, retry, works on flaky connections |
| `<ConfirmDangerous>` | typed confirmation for destructive/financial actions |
| `<OfflineBanner>` | connection state + pending-sync count for field surfaces |

---

## 7. Data-loading patterns

- **Deferred props** (`Inertia::defer()` + `<Deferred>`) for anything slow on a page: dashboard charts, live session lists, usage aggregates. The shell must paint instantly.
- **Grouped deferred props** so independent panels load in parallel rather than one round trip.
- **Prefetching** on hover for row links in the customer and service grids.
- **Polling** (`router.reload({only: [...]})` on an interval) for the live sessions page and the network commands queue — 10s interval, paused when the tab is hidden.
- **`useHttp`** for typeahead search, "test router connection", and FX rate lookup — requests that shouldn't create a history entry.
- **Optimistic updates** for cheap toggles only. Never for payments, never for network state.
- **Partial reloads** after actions: reload only the props that changed, not the page.

---

## 8. Internationalisation and RTL

- Locales `en`, `ar`, `fr`. Backend messages via Laravel `lang/`; frontend strings via a small `t()` helper backed by JSON dictionaries shared as a prop **only for the current locale**.
- `direction` is a shared prop; `<html dir>` is set server-side in the Blade root so there is no flash of wrong direction.
- Tailwind 4: use **logical properties** (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`) everywhere. A physical `ml-4` in a shared component is a bug.
- Icons that imply direction (arrows, chevrons) flip with `rtl:rotate-180` or an RTL-aware icon wrapper.
- Numbers: keep Latin digits by default (Arabic-speaking businesses generally read Latin digits for money); make it a tenant setting rather than assuming.
- Dates: format via `Intl.DateTimeFormat` with the tenant timezone; never `toLocaleDateString()` with the browser's zone for business dates.
- **Test RTL in CI**: at least one visual/Playwright test per major layout with `dir=rtl`.

---

## 9. UX rules for this specific product

1. **Money and expiry are always visible** on any customer-related screen. They're the two questions every conversation starts with.
2. **Every destructive or financial action shows its consequence before confirming.** "This will extend service to 10 Sep 2026 and reconnect the customer." Not "Are you sure?"
3. **Network truth is never hidden.** If the DB says active and the router says disabled, say so on the badge and offer the fix. Do not paper over drift.
4. **Async is visible.** When an action queues a network command, show a small inline progress state that resolves to success/failure via websocket — not a toast that lies about completion.
5. **Failure states are actionable.** "Router unreachable (timeout after 10s) — retry / view command log / check router", not "Something went wrong".
6. **Keyboard-first for the office.** `Cmd+K` search, `n` new customer, `p` take payment, `/` focus filter, `Esc` close. Operators do hundreds of these a day.
7. **Bulk actions with a preview.** "Suspend 47 services" shows the list and the total revenue impact before executing, and executes as a monitored batch, not 47 fire-and-forget requests.
8. **Empty states teach.** A new tenant's empty customers page shows the setup checklist (add a zone, add a plan, add a router, import customers), not a shrug.
9. **The field surfaces assume no signal.** Show pending-sync counts, never block on a request, never lose typed input on a failed submit.
10. **Print/PDF matters.** Receipts are printed on thermal printers and sent on WhatsApp. Design both a 58/80mm thermal-friendly receipt and an A4 invoice.

---

## 10. Performance budgets

- Initial JS for the staff app: < 250KB gzipped after code splitting (the v3 Vite plugin handles per-page splits).
- Customer list with 50 rows: TTFB < 300ms, interactive < 1s on a mid-range Android over 3G.
- Portal dashboard: full paint < 1.5s on 3G. This is the screen a customer with *no internet* loads over mobile data, so it must be light.
- No page ships more than 200KB of JSON props. If it does, defer or paginate it.

---

## 11. Accessibility baseline

Keyboard reachable for every action; visible focus rings; form fields labelled and error-associated (`aria-describedby`); colour never the sole status carrier (badges carry text too); contrast AA; `prefers-reduced-motion` respected. Run `axe` in the Playwright suite on the top 10 pages.
