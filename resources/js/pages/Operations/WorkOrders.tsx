import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createIdempotencyKey } from '@/lib/idempotency';
import { createTranslator } from '@/lib/i18n';
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

const checklistProgress = (checklist: Record<string, boolean | string>, t: (key: string) => string) => {
    const items = Object.values(checklist);
    const complete = items.filter((value) => value === true || value === 'true').length;
    return items.length === 0
        ? t('work_orders.no_checklist')
        : [complete + '/' + items.length, t('work_orders.checked')].join(' ');
};

export default function WorkOrdersPage({ workOrders, filters }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
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
            <Head title={t('work_orders.title')} />
            <div>
                <p className="eyebrow">{t('work_orders.eyebrow')}</p>
                <h1 className="page-title">{t('work_orders.title')}</h1>
                <p className="page-subtitle">{t('work_orders.subtitle')}</p>
                <Link
                    href="/operations/work-orders/calendar"
                    className="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-brand"
                >
                    <CalendarDays size={15} /> {t('work_orders.calendar')}
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">{t('work_orders.search')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('work_orders.search_placeholder')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-52">
                    <span className="field-label">{t('work_orders.status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('work_orders.all_statuses')}</option>
                        <option value="pending">{t('Pending')}</option>
                        <option value="assigned">{t('Assigned')}</option>
                        <option value="en_route">{t('work_orders.en_route')}</option>
                        <option value="in_progress">{t('In progress')}</option>
                        <option value="completed">{t('Completed')}</option>
                        <option value="failed">{t('Failed')}</option>
                        <option value="cancelled">{t('Cancelled')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <ClipboardList size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {workOrders.total.toLocaleString()} {t('work_orders.count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('work_orders.note')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1040px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('work_orders.work_order')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Schedule')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Checklist')}</th>
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
                                            {order.service?.username ?? t('work_orders.no_service')}
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
                                            <span className="text-sm text-muted">{t('work_orders.no_customer')}</span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">
                                            {order.assignee?.name ?? t('Unassigned')}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(order.scheduled_at)}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={order.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {checklistProgress(order.checklist, t)}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {['assigned', 'in_progress'].includes(order.status) ? (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                onClick={() =>
                                                    router.post(`/operations/work-orders/${order.public_id}/complete`, {
                                                        idempotency_key: createIdempotencyKey('work-order'),
                                                    })
                                                }
                                            >
                                                <CheckCircle2 size={14} /> {t('Complete')}
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
                                        <p className="mt-3 font-semibold">{t('work_orders.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {workOrders.current_page} {t('of')} {workOrders.last_page}
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
