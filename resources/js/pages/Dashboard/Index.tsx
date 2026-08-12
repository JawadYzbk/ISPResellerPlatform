import { Deferred, Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, CircleAlert, Clock3, CreditCard, Percent, Plus, TrendingUp, Users, WalletCards, Wifi } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import type { AttentionQueueItem, DashboardMetrics, PageProps } from '@/types';

type Props = PageProps & {
    metrics?: DashboardMetrics;
    attentionQueue?: AttentionQueueItem[];
};

export default function Dashboard({ metrics, attentionQueue }: Props) {
    const { auth } = usePage<PageProps>().props;
    const canCreateCustomer = auth.permissions.includes('customers.create');
    const cards = metrics
        ? [
              {
                  label: 'Customers',
                  value: metrics.customers,
                  note: 'Across this workspace',
                  icon: Users,
                  tint: 'bg-blue-50 text-blue-700',
              },
              {
                  label: 'Active services',
                  value: metrics.activeServices,
                  note: 'Currently provisioned',
                  icon: Wifi,
                  tint: 'bg-emerald-50 text-emerald-700',
              },
              {
                  label: 'Needs attention',
                  value: metrics.attention,
                  note: 'Pending or suspended',
                  icon: CircleAlert,
                  tint: 'bg-rose-50 text-rose-700',
              },
              {
                  label: 'Expiring this week',
                  value: metrics.expiringSoon,
                  note: 'Renewal opportunities',
                  icon: Clock3,
                  tint: 'bg-amber-50 text-amber-700',
              },
          ]
        : [];

    return (
        <AppLayout>
            <Head title="Overview" />
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Good morning, {auth.user?.name.split(' ')[0]}</p>
                    <h1 className="page-title">Your operations at a glance.</h1>
                    <p className="page-subtitle">
                        Keep an eye on the customers, services and actions that need you today.
                    </p>
                </div>
                <Link href={canCreateCustomer ? '/customers/create' : '/customers'} className="button-primary">
                    {canCreateCustomer ? <Plus size={17} /> : <Users size={17} />}
                    {canCreateCustomer ? 'Add customer' : 'Find customers'}
                </Link>
            </div>
            <Deferred data="metrics" fallback={<DashboardMetricsFallback />}>
                {metrics ? (
                    <>
                        <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {cards.map(({ label, value, note, icon: Icon, tint }) => (
                                <div key={label} className="card p-5">
                                    <div className="flex items-start justify-between">
                                        <div className={`grid size-10 place-items-center rounded-xl ${tint}`}>
                                            <Icon size={19} />
                                        </div>
                                        <ArrowUpRight size={17} className="text-muted" />
                                    </div>
                                    <p className="mt-5 text-sm text-muted">{label}</p>
                                    <p className="mt-1 font-display text-3xl font-semibold tracking-tight">
                                        {value.toLocaleString()}
                                    </p>
                                    <p className="mt-1 text-xs text-muted">{note}</p>
                                </div>
                            ))}
                        </div>
                        {metrics.owner && <OwnerFinancePanel owner={metrics.owner} />}
                        <div className="mt-6 grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
                            <div className="card overflow-hidden">
                                <div className="flex items-center justify-between border-b border-line px-6 py-5">
                                    <div>
                                        <h2 className="section-title">Today’s operating rhythm</h2>
                                        <p className="mt-1 text-sm text-muted">The few signals worth checking first.</p>
                                    </div>
                                    <Link href="/reports/finance" className="button-quiet">
                                        View report
                                        <ArrowUpRight size={15} />
                                    </Link>
                                </div>
                                <div className="grid divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                                    <div className="p-6">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                            Collections
                                        </p>
                                        <p className="mt-3 font-display text-2xl font-semibold">
                                            {formatMoney(metrics.collectionsToday, metrics.collectionsCurrency)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">Posted collections today</p>
                                    </div>
                                    <div className="p-6">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                            Network
                                        </p>
                                        <div className="mt-3 flex items-center gap-2">
                                            <StatusBadge
                                                status={metrics.networkPending > 0 ? 'pending_sync' : 'in_sync'}
                                            />
                                            <span className="text-sm font-medium">
                                                {metrics.networkPending} pending command(s)
                                            </span>
                                        </div>
                                        <p className="mt-2 text-xs text-muted">Command pipeline is ready</p>
                                    </div>
                                    <div className="p-6">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                            Field work
                                        </p>
                                        <p className="mt-3 font-display text-2xl font-semibold">
                                            {metrics.openWorkOrders}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">Open work orders</p>
                                    </div>
                                </div>
                            </div>
                            <div className="card p-6">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <h2 className="section-title">Quick actions</h2>
                                        <p className="mt-1 text-sm text-muted">Shortcuts for the front desk.</p>
                                    </div>
                                    <CreditCard className="text-brand" size={20} />
                                </div>
                                <div className="mt-5 space-y-2">
                                    <Link href="/customers" className="quick-action">
                                        <span className="grid size-8 place-items-center rounded-lg bg-brand-soft text-brand">
                                            <Users size={16} />
                                        </span>
                                        <span>
                                            <b>Find a customer</b>
                                            <small>Search by name, phone or code</small>
                                        </span>
                                        <ArrowUpRight size={16} className="ms-auto text-muted" />
                                    </Link>
                                    <Link href="/customers" className="quick-action">
                                        <span className="grid size-8 place-items-center rounded-lg bg-sand text-ink">
                                            <CreditCard size={16} />
                                        </span>
                                        <span>
                                            <b>Open a customer</b>
                                            <small>Review services and billing history</small>
                                        </span>
                                        <ArrowUpRight size={16} className="ms-auto text-muted" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="card mt-6 overflow-hidden">
                            <div className="border-b border-line px-6 py-5">
                                <h2 className="section-title">NOC signals</h2>
                                <p className="mt-1 text-sm text-muted">
                                    Network health from the latest router, session, command, and incident state.
                                </p>
                            </div>
                            <div className="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-5">
                                {[
                                    ['Offline routers', metrics.offlineRouters, 'bg-rose-50 text-rose-700'],
                                    ['Open incidents', metrics.openIncidents, 'bg-rose-50 text-rose-700'],
                                    ['Failed commands', metrics.failedCommands, 'bg-amber-50 text-amber-700'],
                                    ['Drifted services', metrics.driftedServices, 'bg-amber-50 text-amber-700'],
                                    ['Live sessions', metrics.activeSessions, 'bg-emerald-50 text-emerald-700'],
                                ].map(([label, value, tint]) => (
                                    <div key={label} className="p-5">
                                        <div className={`grid size-9 place-items-center rounded-xl ${tint}`}>
                                            <Wifi size={16} />
                                        </div>
                                        <p className="mt-4 text-xs font-semibold uppercase tracking-wider text-muted">
                                            {label}
                                        </p>
                                        <p className="mt-1 font-display text-2xl font-semibold">{value}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </>
                ) : null}
            </Deferred>
            <Deferred data="attentionQueue" fallback={<DashboardAttentionFallback />}>
                {attentionQueue ? (
                    <div id="attention" className="card mt-6 overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div>
                                <h2 className="section-title">Manager attention queue</h2>
                                <p className="mt-1 text-sm text-muted">
                                    The next operational decisions, already linked to their records.
                                </p>
                            </div>
                            <CircleAlert className="text-brand" size={20} />
                        </div>
                        {attentionQueue.length === 0 ? (
                            <p className="px-6 py-8 text-sm text-muted">Nothing needs attention right now.</p>
                        ) : (
                            <div className="divide-y divide-line">
                                {attentionQueue.map((item) => {
                                    const tone = {
                                        critical: 'bg-rose-50 text-rose-700',
                                        warning: 'bg-amber-50 text-amber-700',
                                        info: 'bg-blue-50 text-blue-700',
                                    }[item.severity];

                                    return (
                                        <Link
                                            key={`${item.type}-${item.href}`}
                                            href={item.href}
                                            className="flex items-center gap-4 px-6 py-4 transition hover:bg-sand/30"
                                        >
                                            <span
                                                className={`grid size-9 shrink-0 place-items-center rounded-xl ${tone}`}
                                            >
                                                <CircleAlert size={16} />
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block text-sm font-semibold">{item.title}</span>
                                                <span className="mt-1 block truncate text-xs text-muted">
                                                    {item.detail}
                                                </span>
                                            </span>
                                            <ArrowUpRight size={16} className="shrink-0 text-muted" />
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                ) : null}
            </Deferred>
        </AppLayout>
    );
}

function DashboardMetricsFallback() {
    return <div className="mt-8 h-80 animate-pulse rounded-2xl bg-sand/60" aria-label="Loading dashboard metrics" />;
}

function DashboardAttentionFallback() {
    return <div className="card mt-6 h-32 animate-pulse bg-sand/60" aria-label="Loading manager attention queue" />;
}

function OwnerFinancePanel({ owner }: { owner: NonNullable<DashboardMetrics['owner']> }) {
    const cards = [
        { label: 'Revenue', value: formatMoney(owner.revenue, owner.baseCurrency), icon: TrendingUp },
        { label: 'Collected', value: formatMoney(owner.collected, owner.baseCurrency), icon: WalletCards },
        {
            label: 'Collection rate',
            value: owner.collectionRate === null ? '—' : `${owner.collectionRate.toFixed(2)}%`,
            icon: Percent,
        },
        { label: 'Margin', value: formatMoney(owner.margin, owner.baseCurrency), icon: TrendingUp },
    ];
    const maxServices = Math.max(1, ...owner.statusTrend.flatMap((month) => [month.active, month.suspended]));
    const currencies = Object.entries(owner.currencyMetrics);

    return (
        <section className="card mt-6 overflow-hidden" aria-label="Owner finance metrics">
            <div className="flex flex-col justify-between gap-3 border-b border-line px-6 py-5 sm:flex-row sm:items-center">
                <div>
                    <h2 className="section-title">Owner finance</h2>
                    <p className="mt-1 text-sm text-muted">
                        Month to date · base currency {owner.baseCurrency}
                    </p>
                </div>
                <Link href="/reports/finance" className="button-quiet">
                    Open finance report
                    <ArrowUpRight size={15} />
                </Link>
            </div>
            <div className="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-4">
                {cards.map(({ label, value, icon: Icon }) => (
                    <div key={label} className="p-5">
                        <div className="grid size-9 place-items-center rounded-xl bg-brand-soft text-brand">
                            <Icon size={16} />
                        </div>
                        <p className="mt-4 text-xs font-semibold uppercase tracking-wider text-muted">{label}</p>
                        <p className="mt-1 font-display text-2xl font-semibold">{value}</p>
                    </div>
                ))}
            </div>
            <div className="grid gap-6 border-t border-line px-6 py-5 xl:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="font-semibold">Service status trend</h3>
                            <p className="mt-1 text-xs text-muted">Active and suspended services over six months.</p>
                        </div>
                        <span className="text-xs text-muted">Active / suspended</span>
                    </div>
                    <div className="mt-5 grid grid-cols-6 gap-3">
                        {owner.statusTrend.map((month) => (
                            <div key={month.month} className="min-w-0 text-center">
                                <div className="flex h-24 items-end justify-center gap-1.5">
                                    <span
                                        className="w-3 rounded-t bg-brand"
                                        style={{ height: `${Math.max(4, (month.active / maxServices) * 100)}%` }}
                                        title={`${month.active} active`}
                                    />
                                    <span
                                        className="w-3 rounded-t bg-rose-300"
                                        style={{ height: `${Math.max(4, (month.suspended / maxServices) * 100)}%` }}
                                        title={`${month.suspended} suspended`}
                                    />
                                </div>
                                <p className="mt-2 truncate text-[11px] text-muted">{month.month}</p>
                            </div>
                        ))}
                    </div>
                </div>
                <div>
                    <h3 className="font-semibold">By currency</h3>
                    <p className="mt-1 text-xs text-muted">Issued revenue, posted collections, and margin.</p>
                    <div className="mt-4 divide-y divide-line rounded-xl border border-line">
                        <div className="grid grid-cols-[auto_1fr_1fr_1fr] gap-3 border-b border-line bg-sand/30 px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-muted">
                            <span>Code</span>
                            <span>Revenue</span>
                            <span>Collected</span>
                            <span className="text-end">Margin</span>
                        </div>
                        {currencies.length === 0 ? (
                            <p className="px-4 py-4 text-sm text-muted">No finance activity this month.</p>
                        ) : (
                            currencies.map(([currency, values]) => (
                                <div key={currency} className="grid grid-cols-[auto_1fr_1fr_1fr] items-center gap-3 px-4 py-3 text-sm">
                                    <span className="font-semibold">{currency}</span>
                                    <span className="text-muted">{formatMoney(values.revenue, currency)}</span>
                                    <span className="text-muted">{formatMoney(values.collected, currency)}</span>
                                    <span className="text-end font-medium">{formatMoney(values.margin, currency)}</span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </section>
    );
}
