import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Network, Router as RouterIcon } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';

type Pop = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    status: string;
    routers: { public_id: string; name: string; host: string; status: string }[];
    upstream_links: { id: number; provider_name: string; capacity_mbps: number | null; monthly_cost_amount: number; currency: string; contract_start: string; contract_end: string | null; notes: string | null }[];
};

export default function PopShowPage({ pop }: { pop: Pop }) {
    return (
        <AppLayout>
            <Head title={pop.name} />
            <Link href="/operations/pops" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"><ArrowLeft size={16} /> Back to POPs</Link>
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p className="eyebrow">Network inventory</p><div className="mt-2 flex items-center gap-3"><h1 className="page-title">{pop.name}</h1><StatusBadge status={pop.status as 'active' | 'inactive'} /></div><p className="page-subtitle">{pop.code} · {pop.address ?? 'No address recorded'}</p></div></div>
            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <section className="card overflow-hidden"><div className="flex items-center gap-2 border-b border-line px-5 py-4"><RouterIcon size={17} className="text-brand" /><h2 className="section-title">Routers</h2></div><div className="divide-y divide-line">{pop.routers.map((router) => <Link key={router.public_id} href={`/operations/routers/${router.public_id}`} className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-sand/30"><div><p className="text-sm font-semibold">{router.name}</p><p className="mt-1 text-xs text-muted">{router.host}</p></div><StatusBadge status={router.status as 'online' | 'offline' | 'unknown'} /></Link>)}{pop.routers.length === 0 && <p className="px-5 py-10 text-center text-sm text-muted">No routers assigned.</p>}</div></section>
                <section className="card overflow-hidden"><div className="flex items-center gap-2 border-b border-line px-5 py-4"><Network size={17} className="text-brand" /><h2 className="section-title">Upstream links</h2></div><div className="divide-y divide-line">{pop.upstream_links.map((link) => <div key={link.id} className="px-5 py-4"><div className="flex items-start justify-between gap-4"><div><p className="text-sm font-semibold">{link.provider_name}</p><p className="mt-1 text-xs text-muted">{link.capacity_mbps ? `${link.capacity_mbps.toLocaleString()} Mbps` : 'Capacity not recorded'} · {formatDate(link.contract_start)}</p></div><p className="text-sm font-semibold">{formatMoney(link.monthly_cost_amount, link.currency)}<span className="block text-end text-xs font-normal text-muted">monthly</span></p></div>{link.contract_end && <p className="mt-2 text-xs text-muted">Contract ends {formatDate(link.contract_end)}</p>}{link.notes && <p className="mt-2 text-sm text-muted">{link.notes}</p>}</div>)}{pop.upstream_links.length === 0 && <p className="px-5 py-10 text-center text-sm text-muted">No upstream links recorded.</p>}</div></section>
            </div>
            <div className="mt-6 flex items-center gap-2 text-xs text-muted"><ExternalLink size={14} /> Provider contracts are inventory records; billing settlement remains in the finance workflow.</div>
        </AppLayout>
    );
}
