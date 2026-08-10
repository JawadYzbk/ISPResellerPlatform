import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, CircleAlert, Clock3, CreditCard, Plus, Users, Wifi } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import type { PageProps } from '@/types';

type Props = PageProps & {
    metrics: { customers: number; activeServices: number; attention: number; expiringSoon: number };
};

export default function Dashboard({ metrics }: Props) {
    const { auth } = usePage<PageProps>().props;
    const cards = [
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
    ];

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
                <Link href="/customers" className="button-primary">
                    <Plus size={17} />
                    Add customer
                </Link>
            </div>
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
            <div className="mt-6 grid gap-6 xl:grid-cols-[1.4fr_0.9fr]">
                <div className="card overflow-hidden">
                    <div className="flex items-center justify-between border-b border-line px-6 py-5">
                        <div>
                            <h2 className="section-title">Today’s operating rhythm</h2>
                            <p className="mt-1 text-sm text-muted">The few signals worth checking first.</p>
                        </div>
                        <button className="button-quiet">
                            View report
                            <ArrowUpRight size={15} />
                        </button>
                    </div>
                    <div className="grid divide-y divide-line sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div className="p-6">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Collections</p>
                            <p className="mt-3 font-display text-2xl font-semibold">$0.00</p>
                            <p className="mt-1 text-xs text-muted">No payments recorded yet</p>
                        </div>
                        <div className="p-6">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Network</p>
                            <div className="mt-3 flex items-center gap-2">
                                <StatusBadge status="in_sync" />
                                <span className="text-sm font-medium">All systems in sync</span>
                            </div>
                            <p className="mt-2 text-xs text-muted">Command pipeline is ready</p>
                        </div>
                        <div className="p-6">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Field work</p>
                            <p className="mt-3 font-display text-2xl font-semibold">0</p>
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
        </AppLayout>
    );
}
