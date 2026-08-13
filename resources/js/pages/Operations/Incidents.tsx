import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, ChevronRight, ExternalLink, RefreshCw } from 'lucide-react';
import { useEffect, useState } from 'react';

import StatusBadge, { type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type Incident = {
    public_id: string;
    type: string;
    severity: string;
    status: 'open' | 'resolved';
    title: string;
    opened_at: string | null;
    resolved_at: string | null;
    router: { public_id: string; name: string; host: string; pop: string | null } | null;
    service: { public_id: string; username: string } | null;
    customer: { public_id: string; code: string; name: string } | null;
};

type Props = PageProps & {
    incidents: Paginator<Incident>;
    filters: { status?: string; severity?: string; search?: string };
};

const severityClass: Record<string, string> = {
    critical: 'bg-rose-50 text-rose-700',
    high: 'bg-rose-50 text-rose-700',
    warning: 'bg-amber-50 text-amber-700',
    info: 'bg-blue-50 text-blue-700',
};

export default function IncidentsPage({ incidents, filters }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [status, setStatus] = useState(filters.status ?? '');
    const [severity, setSeverity] = useState(filters.severity ?? '');
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const reloadWhenVisible = () => {
            if (document.visibilityState !== 'visible') return;
            router.reload({ only: ['incidents'] });
        };
        const interval = window.setInterval(reloadWhenVisible, 10_000);
        document.addEventListener('visibilitychange', reloadWhenVisible);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', reloadWhenVisible);
        };
    }, []);

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/incidents',
            { status: status || undefined, severity: severity || undefined, search: search || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('incidents.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('incidents.eyebrow')}</p>
                    <h1 className="page-title">{t('incidents.title')}</h1>
                    <p className="page-subtitle">{t('incidents.subtitle')}</p>
                </div>
                <span className="inline-flex items-center gap-2 text-sm text-muted">
                    <RefreshCw size={15} /> {t('incidents.updates')}
                </span>
            </div>

            <form
                onSubmit={applyFilters}
                className="card mt-8 grid gap-4 p-5 md:grid-cols-[1.4fr_0.7fr_0.7fr_auto] md:items-end"
            >
                <label>
                    <span className="field-label">{t('Search')}</span>
                    <input
                        className="field"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('incidents.search_placeholder')}
                    />
                </label>
                <label>
                    <span className="field-label">{t('Status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('incidents.all_statuses')}</option>
                        <option value="open">{t('Open')}</option>
                        <option value="resolved">{t('Resolved')}</option>
                    </ResponsiveSelect>
                </label>
                <label>
                    <span className="field-label">{t('incidents.severity')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={severity}
                        onChange={(event) => setSeverity(event.target.value)}
                    >
                        <option value="">{t('incidents.all_severities')}</option>
                        <option value="critical">{t('Critical')}</option>
                        <option value="high">{t('High')}</option>
                        <option value="warning">{t('Warning')}</option>
                        <option value="info">{t('Info')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                    <AlertTriangle size={17} className="text-brand" />
                    <p className="text-sm font-semibold">
                        {incidents.total.toLocaleString()} {t('incidents.count')}
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1000px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Incident')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Scope')}</th>
                                <th className="px-5 py-3.5 text-start">{t('incidents.severity')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Opened')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {incidents.data.map((incident) => (
                                <tr key={incident.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/operations/incidents/${incident.public_id}`}
                                            className="font-semibold hover:text-brand"
                                        >
                                            {incident.title}
                                        </Link>
                                        <p className="mt-1 text-xs capitalize text-muted">
                                            {enumLabel(incident.type, t)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        {incident.router ? (
                                            <Link
                                                href={`/operations/routers/${incident.router.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {incident.router.name}
                                            </Link>
                                        ) : incident.service ? (
                                            <Link
                                                href={`/services/${incident.service.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {incident.service.username}
                                            </Link>
                                        ) : (
                                            <span className="text-muted">{t('Platform')}</span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">
                                            {incident.customer ? (
                                                <Link
                                                    href={`/customers/${incident.customer.public_id}`}
                                                    className="hover:text-brand"
                                                >
                                                    {incident.customer.name}
                                                </Link>
                                            ) : (
                                                (incident.router?.pop ??
                                                incident.router?.host ??
                                                t('incidents.no_customer'))
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <span
                                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${severityClass[incident.severity] ?? 'bg-slate-100 text-slate-600'}`}
                                        >
                                            {incident.severity}
                                        </span>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={incident.status as Status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {formatDate(incident.opened_at)}
                                        {incident.resolved_at && (
                                            <span className="mt-1 block text-xs">
                                                {t('Resolved')} {formatDate(incident.resolved_at)}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        <Link
                                            href={`/operations/incidents/${incident.public_id}`}
                                            className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                        >
                                            {t('View')} <ExternalLink size={14} />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {incidents.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <AlertTriangle className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('incidents.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {incidents.current_page} {t('of')} {incidents.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {incidents.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === incidents.links.length - 1;
                            if (!link.url)
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
