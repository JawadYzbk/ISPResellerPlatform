import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type WorkOrder = {
    public_id: string;
    number: string;
    type: string;
    status: 'pending' | 'assigned' | 'en_route' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    checklist: Record<string, boolean | string>;
    customer: { public_id: string; code: string; name: string } | null;
    service: { public_id: string; username: string } | null;
    assignee: { name: string } | null;
};

type Props = PageProps & {
    workOrders: Paginator<WorkOrder>;
    filters: { status?: string; search?: string };
};

const checklistProgress = (checklist: Record<string, boolean | string>) => {
    const items = Object.values(checklist);
    const complete = items.filter((value) => value === true || value === 'true').length;
    return items.length === 0 ? 'No checklist' : `${complete}/${items.length} checked`;
};

export default function WorkOrdersPage({ workOrders, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/work-orders',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Work orders" />
            <div>
                <p className="eyebrow">Field operations</p>
                <h1 className="page-title">Work orders</h1>
                <p className="page-subtitle">
                    Coordinate installations and repairs, then complete the service transition from one controlled
                    action.
                </p>
                <Link
                    href="/operations/work-orders/calendar"
                    className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-brand"
                >
                    <CalendarDays size={15} /> Open calendar
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">Search work order or customer</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="WO-0001, type, customer"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-52">
                    <span className="field-label">Work order status</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="assigned">Assigned</option>
                        <option value="en_route">En route</option>
                        <option value="in_progress">In progress</option>
                        <option value="completed">Completed</option>
                        <option value="failed">Failed</option>
                        <option value="cancelled">Cancelled</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <ClipboardList size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{workOrders.total.toLocaleString()} work order(s)</p>
                    </div>
                    <p className="text-xs text-muted">Installation completion can activate the linked service.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1040px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Work order</th>
                                <th className="px-5 py-3.5 text-start">Customer</th>
                                <th className="px-5 py-3.5 text-start">Schedule</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Checklist</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {workOrders.data.map((order) => (
                                <tr key={order.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/operations/work-orders/${order.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {order.number}
                                        </Link>
                                        <p className="mt-1 text-xs capitalize text-muted">
                                            {order.type.replace('_', ' ')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {order.service?.username ?? 'No service linked'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        {order.customer ? (
                                            <Link
                                                href={`/customers/${order.customer.public_id}`}
                                                className="text-sm font-semibold hover:text-brand"
                                            >
                                                {order.customer.name}
                                            </Link>
                                        ) : (
                                            <span className="text-sm text-muted">No customer</span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">
                                            {order.assignee?.name ?? 'Unassigned'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(order.scheduled_at)}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={order.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {checklistProgress(order.checklist)}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {['assigned', 'in_progress'].includes(order.status) ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                onClick={() =>
                                                    router.post(`/operations/work-orders/${order.public_id}/complete`, {
                                                        idempotency_key: crypto.randomUUID(),
                                                    })
                                                }
                                            >
                                                <CheckCircle2 size={14} /> Complete
                                            </button>
                                        ) : (
                                            <span className="text-xs text-muted">{formatDate(order.completed_at)}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {workOrders.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <ClipboardList className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No work orders match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {workOrders.current_page} of {workOrders.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {workOrders.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === workOrders.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? (
                                            <ChevronLeft size={16} />
                                        ) : isNext ? (
                                            <ChevronRight size={16} />
                                        ) : (
                                            link.label
                                        )}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? (
                                        <ChevronLeft size={16} />
                                    ) : isNext ? (
                                        <ChevronRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
