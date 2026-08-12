import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { CheckCircle2, Edit3, Globe2, Plus, Save, Server, X } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import type { Status } from '@/components/StatusBadge';
import type { Paginator } from '@/types';

type Pool = {
    id: number;
    name: string;
    cidr: string;
    gateway: string | null;
    type: string;
    version: number;
    is_active: boolean;
    addresses_count: number;
    free_addresses_count: number;
    router: { id: number; name: string } | null;
};

type Address = {
    id: number;
    address: string;
    status: Status;
    assigned_at: string | null;
    service: { public_id: string; username: string } | null;
};
type Router = { id: number; name: string; host: string };
type Props = {
    pools: Pool[];
    selectedPoolId?: number;
    addresses: Paginator<Address> | null;
    routers: Router[];
    canManage: boolean;
};

export default function IpPoolsPage({ pools, selectedPoolId, addresses, routers, canManage }: Props) {
    const poolForm = useForm({
        name: '',
        cidr: '',
        gateway: '',
        type: 'dynamic',
        version: '4',
        router_id: '',
        is_active: true,
    });
    const addressForm = useForm({ address: '', status: 'free' });
    const selectedPool = pools.find((pool) => pool.id === selectedPoolId) ?? pools[0];
    const [editOpen, setEditOpen] = useState(false);
    const editForm = useForm({
        name: selectedPool?.name ?? '',
        gateway: selectedPool?.gateway ?? '',
        type: selectedPool?.type ?? 'dynamic',
        router_id: selectedPool?.router?.id ? String(selectedPool.router.id) : '',
        is_active: selectedPool?.is_active ?? true,
    });

    const submitPool = (event: React.FormEvent) => {
        event.preventDefault();
        poolForm.transform((data) => ({
            ...data,
            version: Number(data.version),
            router_id: data.router_id ? Number(data.router_id) : null,
        }));
        poolForm.post('/operations/ip-pools');
    };

    const submitAddress = (event: React.FormEvent) => {
        event.preventDefault();
        if (selectedPool) addressForm.post(`/operations/ip-pools/${selectedPool.id}/addresses`);
    };

    const startEdit = () => {
        if (!selectedPool) return;
        editForm.setData({
            name: selectedPool.name,
            gateway: selectedPool.gateway ?? '',
            type: selectedPool.type,
            router_id: selectedPool.router?.id ? String(selectedPool.router.id) : '',
            is_active: selectedPool.is_active,
        });
        editForm.clearErrors();
        setEditOpen(true);
    };

    const cancelEdit = () => {
        setEditOpen(false);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitEdit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedPool) return;
        editForm.patch(`/operations/ip-pools/${selectedPool.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    return (
        <AppLayout>
            <Head title="IP pools" />
            <div>
                <p className="eyebrow">Network inventory</p>
                <h1 className="page-title">IP pools and addresses</h1>
                <p className="page-subtitle">
                    Keep address capacity, gateways, and assignment state visible before provisioning changes are
                    queued.
                </p>
            </div>

            {canManage && (
                <form onSubmit={submitPool} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Plus size={17} className="text-brand" />
                        <h2 className="section-title">Add IP pool</h2>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                        <label>
                            <span className="field-label">Pool name</span>
                            <input
                                className="field"
                                value={poolForm.data.name}
                                onChange={(event) => poolForm.setData('name', event.target.value)}
                                placeholder="Subscriber IPv4"
                            />
                            {poolForm.errors.name && <p className="field-error">{poolForm.errors.name}</p>}
                        </label>
                        <label>
                            <span className="field-label">CIDR</span>
                            <input
                                className="field"
                                value={poolForm.data.cidr}
                                onChange={(event) => poolForm.setData('cidr', event.target.value)}
                                placeholder="10.20.10.0/24"
                            />
                            {poolForm.errors.cidr && <p className="field-error">{poolForm.errors.cidr}</p>}
                        </label>
                        <label>
                            <span className="field-label">Gateway</span>
                            <input
                                className="field"
                                value={poolForm.data.gateway}
                                onChange={(event) => poolForm.setData('gateway', event.target.value)}
                                placeholder="10.20.10.1"
                            />
                        </label>
                        <label>
                            <span className="field-label">IP version</span>
                            <ResponsiveSelect
                                className="field"
                                value={poolForm.data.version}
                                onChange={(event) => poolForm.setData('version', event.target.value)}
                            >
                                <option value="4">IPv4</option>
                                <option value="6">IPv6</option>
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">Use</span>
                            <ResponsiveSelect
                                className="field"
                                value={poolForm.data.type}
                                onChange={(event) => poolForm.setData('type', event.target.value)}
                            >
                                <option value="dynamic">Dynamic</option>
                                <option value="static">Static</option>
                                <option value="blocked">Blocked</option>
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">Router</span>
                            <ResponsiveSelect
                                className="field"
                                value={poolForm.data.router_id}
                                onChange={(event) => poolForm.setData('router_id', event.target.value)}
                            >
                                <option value="">No router</option>
                                {routers.map((router) => (
                                    <option key={router.id} value={router.id}>
                                        {router.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button className="button-primary" disabled={poolForm.processing}>
                            <Plus size={16} /> Add pool
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-8 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <section className="card overflow-hidden">
                    <div className="border-b border-line px-5 py-4">
                        <div className="flex items-center gap-2">
                            <Globe2 size={17} className="text-brand" />
                            <h2 className="section-title">Pools</h2>
                        </div>
                    </div>
                    <div className="divide-y divide-line">
                        {pools.map((pool) => (
                            <button
                                type="button"
                                key={pool.id}
                                className={`block w-full px-5 py-4 text-start transition hover:bg-sand/30 ${selectedPool?.id === pool.id ? 'bg-sand/60' : ''}`}
                                onClick={() =>
                                    router.get(
                                        '/operations/ip-pools',
                                        { pool_id: pool.id },
                                        { preserveState: true, replace: true },
                                    )
                                }
                            >
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <p className="text-sm font-semibold">{pool.name}</p>
                                        <p className="mt-1 font-mono text-xs text-muted">
                                            {pool.cidr} · {pool.type} · IPv{pool.version}
                                        </p>
                                    </div>
                                    <StatusBadge status={pool.is_active ? 'active' : 'inactive'} />
                                </div>
                                <div className="mt-3 flex items-center justify-between text-xs text-muted">
                                    <span>
                                        {pool.free_addresses_count} free of {pool.addresses_count} recorded
                                    </span>
                                    <span>{pool.router?.name ?? 'Unassigned router'}</span>
                                </div>
                            </button>
                        ))}
                        {pools.length === 0 && (
                            <div className="px-5 py-14 text-center">
                                <Globe2 className="mx-auto text-muted" size={28} />
                                <p className="mt-3 font-semibold">No IP pools yet</p>
                            </div>
                        )}
                    </div>
                </section>
                <section className="card overflow-hidden">
                    <div className="flex items-center justify-between border-b border-line px-5 py-4">
                        <div>
                            <h2 className="section-title">{selectedPool?.name ?? 'Addresses'}</h2>
                            <p className="mt-1 text-xs text-muted">
                                {selectedPool?.gateway ? `Gateway ${selectedPool.gateway}` : 'No gateway recorded'}
                            </p>
                        </div>
                        <div className="flex items-center gap-3">
                            {selectedPool && canManage && !editOpen && (
                                <button type="button" className="button-quiet" onClick={startEdit}>
                                    <Edit3 size={15} /> Edit pool
                                </button>
                            )}
                            <Server size={17} className="text-brand" />
                        </div>
                    </div>
                    {selectedPool && canManage && editOpen && (
                        <form onSubmit={submitEdit} className="grid gap-4 border-b border-line bg-sand/30 p-5 md:grid-cols-2">
                            <label>
                                <span className="field-label">Pool name</span>
                                <input
                                    className="field"
                                    value={editForm.data.name}
                                    onChange={(event) => editForm.setData('name', event.target.value)}
                                    required
                                />
                                {editForm.errors.name && <p className="field-error">{editForm.errors.name}</p>}
                            </label>
                            <label>
                                <span className="field-label">Gateway</span>
                                <input
                                    className="field"
                                    value={editForm.data.gateway}
                                    onChange={(event) => editForm.setData('gateway', event.target.value)}
                                />
                                {editForm.errors.gateway && <p className="field-error">{editForm.errors.gateway}</p>}
                            </label>
                            <label>
                                <span className="field-label">Use</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={editForm.data.type}
                                    onChange={(event) => editForm.setData('type', event.target.value)}
                                >
                                    <option value="dynamic">Dynamic</option>
                                    <option value="static">Static</option>
                                    <option value="blocked">Blocked</option>
                                </ResponsiveSelect>
                                {editForm.errors.type && <p className="field-error">{editForm.errors.type}</p>}
                            </label>
                            <label>
                                <span className="field-label">Router</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={editForm.data.router_id}
                                    onChange={(event) => editForm.setData('router_id', event.target.value)}
                                >
                                    <option value="">No router</option>
                                    {routers.map((router) => (
                                        <option key={router.id} value={router.id}>
                                            {router.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                {editForm.errors.router_id && <p className="field-error">{editForm.errors.router_id}</p>}
                            </label>
                            <label>
                                <span className="field-label">Status</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={editForm.data.is_active ? 'active' : 'inactive'}
                                    onChange={(event) => editForm.setData('is_active', event.target.value === 'active')}
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </ResponsiveSelect>
                                {editForm.errors.is_active && <p className="field-error">{editForm.errors.is_active}</p>}
                            </label>
                            <div className="flex items-end gap-2">
                                <button type="submit" className="button-primary" disabled={editForm.processing}>
                                    <Save size={15} /> Save changes
                                </button>
                                <button type="button" className="button-quiet" disabled={editForm.processing} onClick={cancelEdit}>
                                    <X size={15} /> Cancel
                                </button>
                            </div>
                        </form>
                    )}
                    {selectedPool && canManage && (
                        <form
                            onSubmit={submitAddress}
                            className="flex flex-col gap-3 border-b border-line bg-sand/30 p-5 sm:flex-row sm:items-end"
                        >
                            <label className="flex-1">
                                <span className="field-label">Record address</span>
                                <input
                                    className="field"
                                    value={addressForm.data.address}
                                    onChange={(event) => addressForm.setData('address', event.target.value)}
                                    placeholder={selectedPool.version === 6 ? '2001:db8::10' : '10.20.10.10'}
                                />
                                {addressForm.errors.address && (
                                    <p className="field-error">{addressForm.errors.address}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">Status</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={addressForm.data.status}
                                    onChange={(event) => addressForm.setData('status', event.target.value)}
                                >
                                    <option value="free">Free</option>
                                    <option value="reserved">Reserved</option>
                                    <option value="conflict">Conflict</option>
                                </ResponsiveSelect>
                            </label>
                            <button className="button-primary" disabled={addressForm.processing}>
                                <Plus size={16} /> Record
                            </button>
                        </form>
                    )}
                    {addresses ? (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[620px] text-start">
                                <thead>
                                    <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                        <th className="px-5 py-3 text-start">Address</th>
                                        <th className="px-5 py-3 text-start">Status</th>
                                        <th className="px-5 py-3 text-start">Service</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {addresses.data.map((address) => (
                                        <tr key={address.id}>
                                            <td className="px-5 py-3 font-mono text-sm">{address.address}</td>
                                            <td className="px-5 py-3">
                                                <StatusBadge status={address.status} />
                                            </td>
                                            <td className="px-5 py-3 text-sm text-muted">
                                                {address.service ? (
                                                    <Link
                                                        href={`/services/${address.service.public_id}`}
                                                        className="font-semibold text-brand"
                                                    >
                                                        {address.service.username}
                                                    </Link>
                                                ) : (
                                                    'Unassigned'
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {addresses.data.length === 0 && (
                                        <tr>
                                            <td colSpan={3} className="px-5 py-14 text-center text-sm text-muted">
                                                No address records in this pool.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <div className="grid min-h-64 place-items-center text-sm text-muted">
                            Select a pool to view addresses.
                        </div>
                    )}
                </section>
            </div>
            <p className="mt-5 flex items-center gap-2 text-xs text-muted">
                <CheckCircle2 size={14} /> Pool creation and address records require network provisioning capability and
                recent authentication.
            </p>
        </AppLayout>
    );
}
