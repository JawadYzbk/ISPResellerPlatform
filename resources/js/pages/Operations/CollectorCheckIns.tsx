import { Head, router, usePage } from '@inertiajs/react';
import { ExternalLink, MapPinned, Navigation, TimerReset } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatDate, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Location = {
    latitude: number;
    longitude: number;
    accuracy_meters: number | null;
    map_url: string;
};

type FieldDay = {
    id: string;
    status: 'active' | 'completed';
    checked_in_at: string;
    checked_out_at: string | null;
    check_in: Location;
    check_out: Location | null;
    collector: { name: string; email: string };
    summary: {
        duration_minutes: number;
        payments: { count: number; totals: Record<string, number> };
        route: { status: string | null; stops: number; completed: number; outcomes: Record<string, number> };
        tasks: { completed: number; open: number };
        cash_shift: { id: string; status: string; system_totals: Record<string, number>; variance: boolean } | null;
        custody: { balances: Record<string, number>; cash_payment_count: number; pending_count: number };
    } | null;
    summary_note: string | null;
};

export default function CollectorCheckIns({ date, fieldDays }: { date: string; fieldDays: FieldDay[] }) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);

    return (
        <AppLayout>
            <Head title={t('collector_checkins.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('collector_checkins.eyebrow')}</p>
                    <h1 className="page-title text-balance">{t('collector_checkins.title')}</h1>
                    <p className="page-subtitle text-pretty">{t('collector_checkins.subtitle')}</p>
                </div>
                <label className="field-label">
                    {t('collector_checkins.work_date')}
                    <input
                        className="field mt-1"
                        type="date"
                        value={date}
                        onChange={(event) =>
                            router.get(
                                '/operations/collector-check-ins',
                                { date: event.target.value },
                                { preserveState: true, replace: true },
                            )
                        }
                    />
                </label>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                <div className="card p-5">
                    <p className="eyebrow">{t('collector_checkins.checked_in')}</p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">{fieldDays.length}</p>
                </div>
                <div className="card p-5">
                    <p className="eyebrow">{t('collector_checkins.in_field')}</p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {fieldDays.filter((item) => item.status === 'active').length}
                    </p>
                </div>
                <div className="card p-5">
                    <p className="eyebrow">{t('collector_checkins.completed')}</p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {fieldDays.filter((item) => item.status === 'completed').length}
                    </p>
                </div>
            </div>

            <section className="card mt-6 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1180px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Collector')}</th>
                                <th className="px-5 py-3.5 text-start">{t('collector_checkins.started')}</th>
                                <th className="px-5 py-3.5 text-start">{t('collector_checkins.finished')}</th>
                                <th className="px-5 py-3.5 text-start">{t('collector_checkins.check_in_location')}</th>
                                <th className="px-5 py-3.5 text-start">{t('collector_checkins.check_out_location')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Route')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Collections')}</th>
                                <th className="px-5 py-3.5 text-start">{t('collector_checkins.tasks_note')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {fieldDays.map((fieldDay) => (
                                <tr key={fieldDay.id}>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{fieldDay.collector.name}</p>
                                        <p className="mt-1 text-xs text-muted">{fieldDay.collector.email}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">{formatDate(fieldDay.checked_in_at)}</td>
                                    <td className="px-5 py-4 text-sm">
                                        {fieldDay.checked_out_at
                                            ? formatDate(fieldDay.checked_out_at)
                                            : t('collector_checkins.still_active')}
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <a
                                            href={fieldDay.check_in.map_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 font-semibold text-brand"
                                        >
                                            {t('collector_checkins.open_map')} <ExternalLink size={13} />
                                        </a>
                                        <p className="mt-1 text-xs text-muted tabular-nums">
                                            {fieldDay.check_in.accuracy_meters === null
                                                ? t('collector_checkins.accuracy_unavailable')
                                                : `±${fieldDay.check_in.accuracy_meters} m`}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        {fieldDay.check_out ? (
                                            <a
                                                href={fieldDay.check_out.map_url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex items-center gap-1 font-semibold text-brand"
                                            >
                                                {t('collector_checkins.open_map')} <ExternalLink size={13} />
                                            </a>
                                        ) : (
                                            <span className="text-muted">{t('Pending')}</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums">
                                        {fieldDay.summary ? (
                                            <>
                                                <p className="font-semibold">
                                                    {fieldDay.summary.route.completed}/{fieldDay.summary.route.stops}{' '}
                                                    {t('collector_checkins.stops')}
                                                </p>
                                                <p className="mt-1 text-xs capitalize text-muted">
                                                    {enumLabel(fieldDay.summary.route.status, t) ||
                                                        t('collector_checkins.no_route')}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-muted">
                                                {t('collector_checkins.pending_checkout')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums">
                                        {fieldDay.summary ? (
                                            <>
                                                <p className="font-semibold">
                                                    {fieldDay.summary.payments.count} {t('collector_checkins.payments')}
                                                </p>
                                                <p className="mt-1 text-xs text-muted">
                                                    {entriesOrEmpty(fieldDay.summary.payments.totals)
                                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                                        .join(' · ') || t('collector_checkins.no_collections')}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums">
                                        {fieldDay.summary ? (
                                            <>
                                                <p className="font-semibold">
                                                    {fieldDay.summary.tasks.completed}{' '}
                                                    {t('Completed').toLocaleLowerCase()} · {fieldDay.summary.tasks.open}{' '}
                                                    {t('collector_checkins.open').toLocaleLowerCase()}
                                                </p>
                                                <p className="mt-1 max-w-64 text-pretty text-xs text-muted">
                                                    {fieldDay.summary_note ??
                                                        `${fieldDay.summary.duration_minutes} minutes in field`}
                                                </p>
                                                <p className="mt-1 max-w-64 text-xs text-muted">
                                                    {t('collector_checkins.custody')}:{' '}
                                                    {entriesOrEmpty(fieldDay.summary.custody.balances)
                                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                                        .join(' · ') || t('collector_checkins.no_physical_cash')}{' '}
                                                    · {fieldDay.summary.custody.pending_count}{' '}
                                                    {t('Pending').toLocaleLowerCase()}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {fieldDays.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-5 py-14 text-center">
                                        <MapPinned className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('collector_checkins.no_checkins')}</p>
                                        <p className="mt-1 text-sm text-muted">
                                            {t('collector_checkins.no_checkins_description')}
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>

            <section className="mt-6 grid gap-4 md:grid-cols-2">
                <div className="card flex items-start gap-3 p-5">
                    <Navigation className="mt-0.5 shrink-0 text-brand" size={18} />
                    <p className="text-pretty text-sm text-muted">{t('collector_checkins.coordinates_description')}</p>
                </div>
                <div className="card flex items-start gap-3 p-5">
                    <TimerReset className="mt-0.5 shrink-0 text-brand" size={18} />
                    <p className="text-pretty text-sm text-muted">{t('collector_checkins.route_description')}</p>
                </div>
            </section>
        </AppLayout>
    );
}
