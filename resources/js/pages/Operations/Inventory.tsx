import { Head, Link, router } from '@inertiajs/react';
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

type Props = PageProps & {
    units: Paginator<InventoryUnit>;
    filters: { status?: string; search?: string };
    canAssign?: boolean;
    assignableServices?: AssignableService[];
};

export default function InventoryPage({ units, filters, canAssign = false, assignableServices = [] }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/inventory',
            { search: search || undefined, status: status || undefined },
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
