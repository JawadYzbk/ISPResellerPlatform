import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, MessageSquare, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type TicketRow = {
    public_id: string;
    number: string;
    subject: string;
    priority: string;
    status: 'open' | 'in_progress' | 'pending' | 'resolved' | 'closed';
    due_at: string | null;
    message_count: number;
    customer: { public_id: string; code: string; name: string } | null;
    service: { public_id: string; username: string } | null;
    assignee: { name: string } | null;
};

type Props = PageProps & {
    tickets: Paginator<TicketRow>;
    filters: { status?: string; priority?: string; search?: string };
    canMutate?: boolean;
    canClose?: boolean;
};

export default function TicketsPage({ tickets, filters }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [priority, setPriority] = useState(filters.priority ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/tickets',
            { search: search || undefined, status: status || undefined, priority: priority || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('Tickets')} />
            <div>
                <p className="eyebrow">{t('tickets.support_operations')}</p>
                <h1 className="page-title">{t('Tickets')}</h1>
                <p className="page-subtitle">{t('tickets.subtitle')}</p>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 lg:flex-row lg:items-end">
                <label className="block lg:min-w-72">
                    <span className="field-label">{t('tickets.search')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('TCK-0001, subject, customer')}
                        />
                    </div>
                </label>
                <label className="block lg:min-w-48">
                    <span className="field-label">{t('Status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('All statuses')}</option>
                        <option value="open">{t('Open')}</option>
                        <option value="in_progress">{t('In progress')}</option>
                        <option value="pending">{t('Pending')}</option>
                        <option value="resolved">{t('Resolved')}</option>
                        <option value="closed">{t('Closed')}</option>
                    </ResponsiveSelect>
                </label>
                <label className="block lg:min-w-40">
                    <span className="field-label">{t('Priority')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={priority}
                        onChange={(event) => setPriority(event.target.value)}
                    >
                        <option value="">{t('tickets.all_priorities')}</option>
                        <option value="critical">{t('Critical')}</option>
                        <option value="high">{t('High')}</option>
                        <option value="normal">{t('Normal')}</option>
                        <option value="low">{t('Low')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <MessageSquare size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {tickets.total.toLocaleString()} {t('tickets.count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('tickets.order_note')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Ticket')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Priority')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('tickets.sla_due')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {tickets.data.map((ticket) => (
                                <tr key={ticket.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/operations/tickets/${ticket.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {ticket.number}
                                        </Link>
                                        <p className="mt-1 max-w-xs truncate text-xs text-muted">{ticket.subject}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {ticket.message_count} {t('tickets.messages')}
                                            {ticket.service ? ` · ${ticket.service.username}` : ''}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        {ticket.customer ? (
                                            <Link
                                                href={`/customers/${ticket.customer.public_id}`}
                                                className="text-sm font-semibold hover:text-brand"
                                            >
                                                {ticket.customer.name}
                                            </Link>
                                        ) : (
                                            <span className="text-sm text-muted">{t('tickets.no_customer')}</span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">{ticket.customer?.code ?? '—'}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold capitalize">{enumLabel(ticket.priority, t)}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={ticket.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(ticket.due_at)}</td>
                                    <td className="px-5 py-4 text-end">
                                        <Link
                                            href={`/operations/tickets/${ticket.public_id}`}
                                            className="text-sm font-semibold text-brand"
                                        >
                                            {t('Open')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {tickets.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <MessageSquare className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('tickets.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {tickets.current_page} {t('of')} {tickets.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {tickets.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === tickets.links.length - 1;
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
