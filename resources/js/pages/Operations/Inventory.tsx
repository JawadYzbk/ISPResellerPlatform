import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Package, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type InventoryUnit = {
    id: number;
    serial_number: string;
    status: 'available' | 'assigned' | 'returned' | 'damaged';
    assigned_at: string | null;
    item: { sku: string; name: string; category: string } | null;
    warehouse: { code: string; name: string } | null;
    service: { public_id: string; username: string; customer_public_id: string | null; customer: string | null } | null;
};

type AssignableService = {
    public_id: string;
    username: string;
    customer: string | null;
};

type BulkBalance = {
    inventory_item_id: number;
    warehouse_id: number;
    sku: string | null;
    name: string | null;
    warehouse: string | null;
    quantity: string;
};

type BulkItem = { id: number; sku: string; name: string };
type BulkWarehouse = { id: number; code: string; name: string };
type InventoryMovement = {
    id: string;
    movement_type: string;
    kind: 'serialized' | 'bulk';
    occurred_at: string | null;
    item: { sku: string; name: string } | null;
    serial_number: string | null;
    from_warehouse: string | null;
    to_warehouse: string | null;
    quantity: number | string;
    reference: string | null;
    actor: string | null;
    note: string | null;
};

type Props = PageProps & {
    units: Paginator<InventoryUnit>;
    filters: { status?: string; search?: string; movement_type?: string };
    canAssign?: boolean;
    canReceive?: boolean;
    canTransfer?: boolean;
    assignableServices?: AssignableService[];
    bulkBalances: BulkBalance[];
    bulkItems: BulkItem[];
    bulkWarehouses: BulkWarehouse[];
    transferWarehouses: BulkWarehouse[];
    movements: InventoryMovement[];
};

export default function InventoryPage({ units, filters, canAssign = false, canReceive = false, canTransfer = false, assignableServices = [], bulkBalances, bulkItems, bulkWarehouses, transferWarehouses, movements }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [movementType, setMovementType] = useState(filters.movement_type ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});
    const [selectedWarehouses, setSelectedWarehouses] = useState<Record<number, string>>({});
    const receiveForm = useForm({ inventory_item_id: '', warehouse_id: '', quantity: '', note: '' });

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/inventory',
            { search: search || undefined, status: status || undefined, movement_type: movementType || undefined },
            { preserveState: true, replace: true },
        );
    };

    const assignUnit = (unit: InventoryUnit) => {
        const servicePublicId = selectedServices[unit.id];
        if (!servicePublicId) {
            return;
        }

        if (window.confirm(`Assign unit ${unit.serial_number} to this service?`)) {
            router.post(`/operations/inventory/${unit.id}/assign`, { service_public_id: servicePublicId });
        }
    };

    const submitReceive = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        receiveForm.post('/operations/inventory/bulk-receive', { onSuccess: () => receiveForm.reset() });
    };

    const transferUnit = (unit: InventoryUnit) => {
        const warehouseId = selectedWarehouses[unit.id];
        if (warehouseId) router.post(`/operations/inventory/${unit.id}/transfer`, { warehouse_id: warehouseId });
    };

    return (
        <AppLayout>
            <Head title="Inventory" />
            <div>
                <p className="eyebrow">Field operations</p>
                <h1 className="page-title">Serialized inventory</h1>
                <p className="page-subtitle">Trace equipment from warehouse stock to the service it was assigned to.</p>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">Search stock or service</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Serial, SKU, equipment, username"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Unit status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="available">Available</option>
                        <option value="assigned">Assigned</option>
                        <option value="returned">Returned</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>

            <section className="card mt-6 p-5">
                <div className="flex items-center justify-between gap-4">
                    <div><p className="section-title">Bulk stock</p><p className="mt-1 text-sm text-muted">Cable, connectors, and other quantity-tracked materials by warehouse.</p></div>
                    <Package size={18} className="text-brand" />
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {bulkBalances.map((balance) => <div key={`${balance.inventory_item_id}-${balance.warehouse_id}`} className="rounded-lg border border-line px-4 py-3 text-sm"><p className="font-semibold">{balance.name ?? balance.sku}</p><p className="mt-1 text-xs text-muted">{balance.sku} · {balance.warehouse}</p><p className="mt-2 text-lg font-semibold text-brand">{balance.quantity}</p></div>)}
                    {bulkBalances.length === 0 && <p className="text-sm text-muted">No bulk stock balances have been recorded.</p>}
                </div>
                {canReceive && bulkItems.length > 0 && bulkWarehouses.length > 0 && (
                    <form onSubmit={submitReceive} className="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
                        <label><span className="field-label">Material</span><select className="field" value={receiveForm.data.inventory_item_id} onChange={(event) => receiveForm.setData('inventory_item_id', event.target.value)}><option value="">Select item</option>{bulkItems.map((item) => <option key={item.id} value={item.id}>{item.sku} · {item.name}</option>)}</select></label>
                        <label><span className="field-label">Warehouse</span><select className="field" value={receiveForm.data.warehouse_id} onChange={(event) => receiveForm.setData('warehouse_id', event.target.value)}><option value="">Select warehouse</option>{bulkWarehouses.map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}</select></label>
                        <label><span className="field-label">Quantity received</span><input className="field" inputMode="decimal" value={receiveForm.data.quantity} onChange={(event) => receiveForm.setData('quantity', event.target.value)} placeholder="0.000" />{receiveForm.errors.quantity && <p className="field-error">{receiveForm.errors.quantity}</p>}</label>
                        <button type="submit" className="button-secondary" disabled={receiveForm.processing}>Receive stock</button>
                        <label className="sm:col-span-2 lg:col-span-4"><span className="field-label">Note</span><input className="field" value={receiveForm.data.note} onChange={(event) => receiveForm.setData('note', event.target.value)} placeholder="Optional receiving note" /></label>
                    </form>
                )}
            </section>

            <section className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div><p className="section-title">Movement audit</p><p className="mt-1 text-sm text-muted">The latest serialized and bulk stock events, including receiving and work-order consumption.</p></div>
                    <label className="min-w-40"><span className="sr-only">Movement type</span><select className="field py-2 text-xs" value={movementType} onChange={(event) => setMovementType(event.target.value)}><option value="">All movement types</option><option value="receive">Receive</option><option value="consume">Consume</option><option value="assign">Assign</option><option value="return">Return</option><option value="transfer">Transfer</option></select></label>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead><tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted"><th className="px-5 py-3.5 text-start">When</th><th className="px-5 py-3.5 text-start">Movement</th><th className="px-5 py-3.5 text-start">Item</th><th className="px-5 py-3.5 text-start">Warehouse</th><th className="px-5 py-3.5 text-end">Quantity</th><th className="px-5 py-3.5 text-start">Reference</th><th className="px-5 py-3.5 text-start">Actor</th></tr></thead>
                        <tbody className="divide-y divide-line">
                            {movements.map((movement) => <tr key={movement.id}><td className="px-5 py-4 text-sm text-muted">{formatDate(movement.occurred_at)}</td><td className="px-5 py-4"><span className="inline-flex rounded-full bg-brand-soft px-2.5 py-1 text-xs font-semibold capitalize text-brand">{movement.movement_type}</span><p className="mt-1 text-xs text-muted">{movement.kind}</p></td><td className="px-5 py-4"><p className="text-sm font-semibold">{movement.item?.name ?? 'Unknown item'}</p><p className="mt-1 text-xs text-muted">{movement.serial_number ?? movement.item?.sku ?? '—'}</p></td><td className="px-5 py-4 text-sm text-muted">{movement.from_warehouse ? `${movement.from_warehouse} → ` : ''}{movement.to_warehouse ?? '—'}</td><td className="px-5 py-4 text-end text-sm font-semibold">{movement.quantity}</td><td className="px-5 py-4 text-sm text-muted">{movement.reference ?? movement.note ?? '—'}</td><td className="px-5 py-4 text-sm text-muted">{movement.actor ?? 'System'}</td></tr>)}
                            {movements.length === 0 && <tr><td colSpan={7} className="px-5 py-12 text-center text-sm text-muted">No inventory movements match this filter.</td></tr>}
                        </tbody>
                    </table>
                </div>
            </section>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Package size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{units.total.toLocaleString()} unit(s)</p>
                    </div>
                    <p className="text-xs text-muted">Assignment and movement history remains auditable.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Unit</th>
                                <th className="px-5 py-3.5 text-start">Equipment</th>
                                <th className="px-5 py-3.5 text-start">Warehouse</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Assigned service</th>
                                <th className="px-5 py-3.5 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {units.data.map((unit) => (
                                <tr key={unit.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{unit.serial_number}</p>
                                        <p className="mt-1 text-xs text-muted">Unit #{unit.id}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{unit.item?.name ?? 'Unknown equipment'}</p>
                                        <p className="mt-1 text-xs text-muted">{unit.item?.sku ?? 'No SKU'}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {unit.warehouse ? `${unit.warehouse.name} (${unit.warehouse.code})` : 'No warehouse'}
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={unit.status} />
                                        {unit.assigned_at && <p className="mt-1 text-xs text-muted">{formatDate(unit.assigned_at)}</p>}
                                    </td>
                                    <td className="px-5 py-4">
                                        {unit.service ? (
                                            unit.service.customer_public_id ? (
                                                <Link href={`/customers/${unit.service.customer_public_id}`} className="text-sm font-semibold hover:text-brand">
                                                    {unit.service.username}
                                                </Link>
                                            ) : (
                                                <span className="text-sm font-semibold">{unit.service.username}</span>
                                            )
                                        ) : (
                                            <span className="text-sm text-muted">Unassigned</span>
                                        )}
                                        {unit.service?.customer && <p className="mt-1 text-xs text-muted">{unit.service.customer}</p>}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canAssign && unit.status === 'available' && assignableServices.length > 0 && (
                                            <div className="flex items-center justify-end gap-2">
                                                <select
                                                    className="field max-w-56 py-2 text-xs"
                                                    value={selectedServices[unit.id] ?? ''}
                                                    onChange={(event) =>
                                                        setSelectedServices((current) => ({
                                                            ...current,
                                                            [unit.id]: event.target.value,
                                                        }))
                                                    }
                                                >
                                                    <option value="">Select service</option>
                                                    {assignableServices.map((service) => (
                                                        <option key={service.public_id} value={service.public_id}>
                                                            {service.username} · {service.customer ?? 'No customer'}
                                                        </option>
                                                    ))}
                                                </select>
                                                <button
                                                    type="button"
                                                    className="text-sm font-semibold text-brand"
                                                    onClick={() => assignUnit(unit)}
                                                >
                                                    Assign
                                                </button>
                                            </div>
                                        )}
                                        {canTransfer && ['available', 'returned'].includes(unit.status) && transferWarehouses.length > 0 && (
                                            <div className="mt-2 flex items-center justify-end gap-2">
                                                <select className="field max-w-56 py-2 text-xs" value={selectedWarehouses[unit.id] ?? ''} onChange={(event) => setSelectedWarehouses((current) => ({ ...current, [unit.id]: event.target.value }))}>
                                                    <option value="">Recover or transfer to</option>
                                                    {transferWarehouses.filter((warehouse) => warehouse.code !== unit.warehouse?.code).map((warehouse) => <option key={warehouse.id} value={warehouse.id}>{warehouse.code} · {warehouse.name}</option>)}
                                                </select>
                                                <button type="button" className="text-sm font-semibold text-brand" onClick={() => transferUnit(unit)}>Transfer</button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {units.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <Package className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No inventory units match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {units.current_page} of {units.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {units.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === units.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
