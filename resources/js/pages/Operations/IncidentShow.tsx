import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CalendarClock, Router, Server, UserRound } from 'lucide-react';

import StatusBadge, { type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatDate, keysOrEmpty } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Incident = {
    public_id: string;
    type: string;
    severity: string;
    status: 'open' | 'resolved';
    title: string;
    description: string | null;
    opened_at: string | null;
    resolved_at: string | null;
    metadata: Record<string, unknown>;
    router: { public_id: string; name: string; host: string; pop: string | null } | null;
    service: { public_id: string; username: string } | null;
    customer: { public_id: string; code: string; name: string } | null;
};

type Props = PageProps & { incident: Incident };

const severityClass: Record<string, string> = {
    critical: 'bg-rose-50 text-rose-700',
    high: 'bg-rose-50 text-rose-700',
    warning: 'bg-amber-50 text-amber-700',
    info: 'bg-blue-50 text-blue-700',
};

export default function IncidentShow({ incident }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);

    return (
        <AppLayout>
            <Head title={incident.title} />
            <Link
                href="/operations/incidents"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to incidents')}
            </Link>
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div>
                    <p className="eyebrow">
                        {t('incident_detail.eyebrow')} · {incident.public_id}
                    </p>
                    <h1 className="page-title">{incident.title}</h1>
                    <p className="mt-2 text-sm capitalize text-muted">{incident.type.replaceAll('_', ' ')}</p>
                </div>
                <div className="flex items-center gap-2">
                    <span
                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${severityClass[incident.severity] ?? 'bg-slate-100 text-slate-600'}`}
                    >
                        {incident.severity}
                    </span>
                    <StatusBadge status={incident.status as Status} />
                </div>
            </div>
            <div className="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                <div className="space-y-6">
                    <section className="card p-6">
                        <div className="flex items-center gap-2">
                            <AlertTriangle size={18} className="text-brand" />
                            <h2 className="section-title">{t('incident_detail.what_happened')}</h2>
                        </div>
                        <p className="mt-5 whitespace-pre-wrap text-sm leading-7 text-muted">
                            {incident.description ?? t('incident_detail.no_description')}
                        </p>
                    </section>
                    <section className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <h2 className="section-title">{t('Timeline')}</h2>
                        </div>
                        <div className="divide-y divide-line">
                            <div className="flex items-start gap-3 px-6 py-5">
                                <CalendarClock size={17} className="mt-0.5 text-brand" />
                                <div>
                                    <p className="text-sm font-semibold">{t('incident_detail.opened')}</p>
                                    <p className="mt-1 text-xs text-muted">{formatDate(incident.opened_at)}</p>
                                </div>
                            </div>
                            {incident.resolved_at && (
                                <div className="flex items-start gap-3 px-6 py-5">
                                    <StatusBadge status="resolved" />
                                    <div>
                                        <p className="text-sm font-semibold">{t('incident_detail.resolved')}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {formatDate(incident.resolved_at)} ·{' '}
                                            {t('incident_detail.resolved_by_automation')}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {!incident.resolved_at && (
                                <div className="px-6 py-5 text-sm text-muted">
                                    {t('incident_detail.open_description')}
                                </div>
                            )}
                        </div>
                    </section>
                </div>
                <aside className="space-y-6">
                    <section className="card p-6">
                        <h2 className="section-title">{t('incident_detail.affected_scope')}</h2>
                        <div className="mt-5 space-y-4">
                            {incident.router && (
                                <div className="flex items-start gap-3">
                                    <Router size={17} className="mt-0.5 text-brand" />
                                    <div>
                                        <p className="text-xs text-muted">{t('Router')}</p>
                                        <Link
                                            href={`/operations/routers/${incident.router.public_id}`}
                                            className="mt-1 block font-semibold hover:text-brand"
                                        >
                                            {incident.router.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">
                                            {incident.router.host}
                                            {incident.router.pop ? ` · ${incident.router.pop}` : ''}
                                        </p>
                                    </div>
                                </div>
                            )}
                            {incident.service && (
                                <div className="flex items-start gap-3">
                                    <Server size={17} className="mt-0.5 text-brand" />
                                    <div>
                                        <p className="text-xs text-muted">{t('Service')}</p>
                                        <Link
                                            href={`/services/${incident.service.public_id}`}
                                            className="mt-1 block font-semibold hover:text-brand"
                                        >
                                            {incident.service.username}
                                        </Link>
                                    </div>
                                </div>
                            )}
                            {incident.customer && (
                                <div className="flex items-start gap-3">
                                    <UserRound size={17} className="mt-0.5 text-brand" />
                                    <div>
                                        <p className="text-xs text-muted">{t('Customer')}</p>
                                        <Link
                                            href={`/customers/${incident.customer.public_id}`}
                                            className="mt-1 block font-semibold hover:text-brand"
                                        >
                                            {incident.customer.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{incident.customer.code}</p>
                                    </div>
                                </div>
                            )}
                            {!incident.router && !incident.service && !incident.customer && (
                                <p className="text-sm text-muted">{t('incident_detail.no_related_record')}</p>
                            )}
                        </div>
                    </section>
                    <section className="card p-6">
                        <h2 className="section-title">{t('incident_detail.metadata')}</h2>
                        <dl className="mt-5 space-y-3 text-sm">
                            {entriesOrEmpty(incident.metadata).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="flex justify-between gap-4 border-b border-line pb-3 last:border-0 last:pb-0"
                                >
                                    <dt className="capitalize text-muted">{key.replaceAll('_', ' ')}</dt>
                                    <dd className="text-end font-semibold">{String(value)}</dd>
                                </div>
                            ))}
                            {keysOrEmpty(incident.metadata).length === 0 && (
                                <p className="text-sm text-muted">{t('incident_detail.no_metadata')}</p>
                            )}
                        </dl>
                    </section>
                </aside>
            </div>
        </AppLayout>
    );
}
