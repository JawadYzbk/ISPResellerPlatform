import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Network, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import type { PageProps, Paginator } from '@/types';

type Pop = { id: number; name: string; code: string; address: string | null; status: string; routers_count: number; upstream_links_count: number };
type Props = PageProps & { pops: Paginator<Pop>; filters: { status?: string; search?: string } };

export default function PopsPage({ pops, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/operations/pops', { search: search || undefined, status: status || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="POPs" />
            <div><p className="eyebrow">Network inventory</p><h1 className="page-title">Points of presence</h1><p className="page-subtitle">Keep router locations, transit capacity, and provider contracts visible together.</p></div>
            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72"><span className="field-label">Search POP</span><div className="relative"><Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" /><input className="field ps-10" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Name or code" /></div></label>
                <label className="block sm:min-w-48"><span className="field-label">Status</span><select className="field" value={status} onChange={(event) => setStatus(event.target.value)}><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
                <button type="submit" className="button-primary">Apply filters</button>
            </form>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4"><Network size={17} className="text-brand" /><p className="text-sm font-semibold">{pops.total.toLocaleString()} POP(s)</p></div>
                <div className="overflow-x-auto"><table className="w-full min-w-[820px] text-start"><thead><tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted"><th className="px-5 py-3.5 text-start">POP</th><th className="px-5 py-3.5 text-start">Address</th><th className="px-5 py-3.5 text-start">Status</th><th className="px-5 py-3.5 text-start">Routers</th><th className="px-5 py-3.5 text-start">Upstream links</th></tr></thead><tbody className="divide-y divide-line">
                    {pops.data.map((pop) => <tr key={pop.id} className="hover:bg-sand/30"><td className="px-5 py-4"><Link href={`/operations/pops/${pop.id}`} className="text-sm font-semibold hover:text-brand">{pop.name}</Link><p className="mt-1 text-xs text-muted">{pop.code}</p></td><td className="px-5 py-4 text-sm text-muted">{pop.address ?? 'No address recorded'}</td><td className="px-5 py-4"><StatusBadge status={pop.status as 'active' | 'inactive'} /></td><td className="px-5 py-4 text-sm text-muted">{pop.routers_count}</td><td className="px-5 py-4 text-sm text-muted">{pop.upstream_links_count}</td></tr>)}
                    {pops.data.length === 0 && <tr><td colSpan={5} className="px-5 py-16 text-center"><Network className="mx-auto text-muted" size={28} /><p className="mt-3 font-semibold">No POPs match these filters</p></td></tr>}
                </tbody></table></div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4"><p className="text-xs text-muted">Page {pops.current_page} of {pops.last_page}</p><div className="flex items-center gap-1">{pops.links.map((link, index) => { const isPrevious = index === 0; const isNext = index === pops.links.length - 1; if (!link.url) return <span key={index} className="grid size-8 place-items-center text-muted/40">{isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}</span>; return <Link key={index} href={link.url} className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}>{isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}</Link>; })}</div></div>
            </div>
        </AppLayout>
    );
}
