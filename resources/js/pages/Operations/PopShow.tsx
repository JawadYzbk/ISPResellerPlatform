import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Network, Plus, Router as RouterIcon } from 'lucide-react';

import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';

type Pop = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    status: Status;
    routers: { public_id: string; name: string; host: string; status: Status }[];
    upstream_links: { id: number; provider_name: string; capacity_mbps: number | null; monthly_cost_amount: number; currency: string; contract_start: string; contract_end: string | null; notes: string | null }[];
};

type Props = {
    pop: Pop;
    canManage: boolean;
    statuses: Status[];
};

export default function PopShowPage({ pop, canManage, statuses }: Props) {
    const popForm = useForm({ name: pop.name, code: pop.code, address: pop.address ?? '', status: pop.status });
    const linkForm = useForm({ provider_name: '', capacity_mbps: '', monthly_cost_amount: '', currency: 'USD', contract_start: '', contract_end: '', notes: '' });

    const submitPop = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        popForm.put('/operations/pops/' + pop.id);
    };

    const submitLink = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        linkForm.post('/operations/pops/' + pop.id + '/upstream-links', { onSuccess: () => linkForm.reset() });
    };

    return (
        <AppLayout>
            <Head title={pop.name} />
            <Link href="/operations/pops" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"><ArrowLeft size={16} /> Back to POPs</Link>

            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Network inventory</p>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="page-title">{pop.name}</h1>
                        <StatusBadge status={pop.status} />
                    </div>
                    <p className="page-subtitle">{pop.code} · {pop.address ?? 'No address recorded'}</p>
                </div>
            </div>

            {canManage && (
                <form onSubmit={submitPop} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2"><Network size={17} className="text-brand" /><h2 className="section-title">Edit POP</h2></div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label><span className="field-label">Name</span><input className="field" value={popForm.data.name} onChange={(event) => popForm.setData('name', event.target.value)} />{popForm.errors.name && <p className="field-error">{popForm.errors.name}</p>}</label>
                        <label><span className="field-label">Code</span><input className="field uppercase" value={popForm.data.code} onChange={(event) => popForm.setData('code', event.target.value)} />{popForm.errors.code && <p className="field-error">{popForm.errors.code}</p>}</label>
                        <label><span className="field-label">Address</span><input className="field" value={popForm.data.address} onChange={(event) => popForm.setData('address', event.target.value)} />{popForm.errors.address && <p className="field-error">{popForm.errors.address}</p>}</label>
                        <label><span className="field-label">Status</span><select className="field" value={popForm.data.status} onChange={(event) => popForm.setData('status', event.target.value as Status)}>{statuses.map((option) => <option key={option} value={option}>{option.replace('_', ' ')}</option>)}</select>{popForm.errors.status && <p className="field-error">{popForm.errors.status}</p>}</label>
                    </div>
                    <div className="flex justify-end"><button type="submit" className="button-primary" disabled={popForm.processing}>Save changes</button></div>
                </form>
            )}

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <section className="card overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4"><RouterIcon size={17} className="text-brand" /><h2 className="section-title">Routers</h2></div>
                    <div className="divide-y divide-line">
                        {pop.routers.map((router) => <Link key={router.public_id} href={'/operations/routers/' + router.public_id} className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-sand/30"><div><p className="text-sm font-semibold">{router.name}</p><p className="mt-1 text-xs text-muted">{router.host}</p></div><StatusBadge status={router.status} /></Link>)}
                        {pop.routers.length === 0 && <p className="px-5 py-10 text-center text-sm text-muted">No routers assigned.</p>}
                    </div>
                </section>

                <section className="card overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4"><Network size={17} className="text-brand" /><h2 className="section-title">Upstream links</h2></div>
                    {canManage && (
                        <form onSubmit={submitLink} className="space-y-4 border-b border-line bg-sand/30 p-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label><span className="field-label">Provider</span><input className="field" value={linkForm.data.provider_name} onChange={(event) => linkForm.setData('provider_name', event.target.value)} placeholder="Transit provider" />{linkForm.errors.provider_name && <p className="field-error">{linkForm.errors.provider_name}</p>}</label>
                                <label><span className="field-label">Capacity (Mbps)</span><input type="number" min="0" className="field" value={linkForm.data.capacity_mbps} onChange={(event) => linkForm.setData('capacity_mbps', event.target.value)} placeholder="1000" />{linkForm.errors.capacity_mbps && <p className="field-error">{linkForm.errors.capacity_mbps}</p>}</label>
                                <label><span className="field-label">Monthly cost (minor units)</span><input type="number" min="0" className="field" value={linkForm.data.monthly_cost_amount} onChange={(event) => linkForm.setData('monthly_cost_amount', event.target.value)} placeholder="125000" />{linkForm.errors.monthly_cost_amount && <p className="field-error">{linkForm.errors.monthly_cost_amount}</p>}</label>
                                <label><span className="field-label">Currency</span><input className="field uppercase" maxLength={3} value={linkForm.data.currency} onChange={(event) => linkForm.setData('currency', event.target.value)} />{linkForm.errors.currency && <p className="field-error">{linkForm.errors.currency}</p>}</label>
                                <label><span className="field-label">Contract starts</span><input type="date" className="field" value={linkForm.data.contract_start} onChange={(event) => linkForm.setData('contract_start', event.target.value)} />{linkForm.errors.contract_start && <p className="field-error">{linkForm.errors.contract_start}</p>}</label>
                                <label><span className="field-label">Contract ends</span><input type="date" className="field" value={linkForm.data.contract_end} onChange={(event) => linkForm.setData('contract_end', event.target.value)} />{linkForm.errors.contract_end && <p className="field-error">{linkForm.errors.contract_end}</p>}</label>
                            </div>
                            <label><span className="field-label">Notes</span><textarea className="field min-h-20" value={linkForm.data.notes} onChange={(event) => linkForm.setData('notes', event.target.value)} placeholder="Primary transit" />{linkForm.errors.notes && <p className="field-error">{linkForm.errors.notes}</p>}</label>
                            <div className="flex justify-end"><button type="submit" className="button-primary" disabled={linkForm.processing}><Plus size={16} /> Record link</button></div>
                        </form>
                    )}
                    <div className="divide-y divide-line">
                        {pop.upstream_links.map((link) => <div key={link.id} className="px-5 py-4"><div className="flex items-start justify-between gap-4"><div><p className="text-sm font-semibold">{link.provider_name}</p><p className="mt-1 text-xs text-muted">{link.capacity_mbps ? link.capacity_mbps.toLocaleString() + ' Mbps' : 'Capacity not recorded'} · {formatDate(link.contract_start)}</p></div><p className="text-sm font-semibold">{formatMoney(link.monthly_cost_amount, link.currency)}<span className="block text-end text-xs font-normal text-muted">monthly</span></p></div>{link.contract_end && <p className="mt-2 text-xs text-muted">Contract ends {formatDate(link.contract_end)}</p>}{link.notes && <p className="mt-2 text-sm text-muted">{link.notes}</p>}</div>)}
                        {pop.upstream_links.length === 0 && <p className="px-5 py-10 text-center text-sm text-muted">No upstream links recorded.</p>}
                    </div>
                </section>
            </div>

            <div className="mt-6 flex items-center gap-2 text-xs text-muted"><ExternalLink size={14} /> Provider contracts are inventory records; billing settlement remains in the finance workflow.</div>
        </AppLayout>
    );
}
