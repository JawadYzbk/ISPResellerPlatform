import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus, Search, Tags } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type Plan = {
    public_id: string;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    services_count: number;
    price: { amount_minor: number; currency: string; effective_from: string } | null;
};

type Props = PageProps & {
    plans: Paginator<Plan>;
    filters: { status?: string; search?: string };
};

export default function PlansIndex({ plans, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/plans', { search: search || undefined, status: status || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Plans" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Commercial catalog</p>
                    <h1 className="page-title">Plans</h1>
                    <p className="page-subtitle">Manage service speeds, billing duration, and effective catalog prices.</p>
                </div>
                <Link href="/plans/create" className="button-primary">
                    <Plus size={16} /> New plan
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">Search plans</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input className="field ps-10" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Plan name or slug" />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">Apply filters</button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Tags size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{plans.total.toLocaleString()} plan(s)</p>
                    </div>
                    <p className="text-xs text-muted">Historical prices stay attached to their effective dates.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Plan</th>
                                <th className="px-5 py-3.5 text-start">Speed</th>
                                <th className="px-5 py-3.5 text-start">Current price</th>
                                <th className="px-5 py-3.5 text-start">Term</th>
                                <th className="px-5 py-3.5 text-start">Services</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {plans.data.map((plan) => (
                                <tr key={plan.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4"><p className="text-sm font-semibold">{plan.name}</p><p className="mt-1 text-xs text-muted">{plan.slug}</p></td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.download_kbps / 1000} / {plan.upload_kbps / 1000} Mbps</td>
                                    <td className="px-5 py-4 text-sm font-semibold">{plan.price ? formatMoney(plan.price.amount_minor, plan.price.currency) : 'No effective price'}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.duration_days} days</td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.services_count}</td>
                                    <td className="px-5 py-4"><StatusBadge status={plan.status} /></td>
                                </tr>
                            ))}
                            {plans.data.length === 0 && <tr><td colSpan={6} className="px-5 py-16 text-center"><Tags className="mx-auto text-muted" size={28} /><p className="mt-3 font-semibold">No plans match these filters</p></td></tr>}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4"><p className="text-xs text-muted">Page {plans.current_page} of {plans.last_page}</p><div className="flex items-center gap-1">{plans.links.map((link, index) => { const isPrevious = index === 0; const isNext = index === plans.links.length - 1; if (!link.url) return <span key={index} className="grid size-8 place-items-center text-muted/40">{isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}</span>; return <Link key={index} href={link.url} className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}>{isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}</Link>; })}</div></div>
            </div>
        </AppLayout>
    );
}
