import { Head, router } from '@inertiajs/react';
import { ExternalLink, MapPinned, Navigation, TimerReset } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatDate, formatMoney } from '@/lib/format';

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
    return (
        <AppLayout>
            <Head title="Collector check-ins" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Jebaya supervision</p>
                    <h1 className="page-title text-balance">Collector check-ins</h1>
                    <p className="page-subtitle text-pretty">
                        Review explicit field-day starts and finishes. This view does not continuously track a collector
                        in the background.
                    </p>
                </div>
                <label className="field-label">
                    Work date
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
                    <p className="eyebrow">Checked in</p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">{fieldDays.length}</p>
                </div>
                <div className="card p-5">
                    <p className="eyebrow">In the field</p>
                    <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                        {fieldDays.filter((item) => item.status === 'active').length}
                    </p>
                </div>
                <div className="card p-5">
                    <p className="eyebrow">Completed</p>
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
                                <th className="px-5 py-3.5 text-start">Collector</th>
                                <th className="px-5 py-3.5 text-start">Started</th>
                                <th className="px-5 py-3.5 text-start">Finished</th>
                                <th className="px-5 py-3.5 text-start">Check-in location</th>
                                <th className="px-5 py-3.5 text-start">Check-out location</th>
                                <th className="px-5 py-3.5 text-start">Route</th>
                                <th className="px-5 py-3.5 text-start">Collections</th>
                                <th className="px-5 py-3.5 text-start">Tasks / note</th>
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
                                        {fieldDay.checked_out_at ? formatDate(fieldDay.checked_out_at) : 'Still active'}
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <a
                                            href={fieldDay.check_in.map_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 font-semibold text-brand"
                                        >
                                            Open map <ExternalLink size={13} />
                                        </a>
                                        <p className="mt-1 text-xs text-muted tabular-nums">
                                            {fieldDay.check_in.accuracy_meters === null
                                                ? 'Accuracy unavailable'
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
                                                Open map <ExternalLink size={13} />
                                            </a>
                                        ) : (
                                            <span className="text-muted">Pending</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums">
                                        {fieldDay.summary ? (
                                            <>
                                                <p className="font-semibold">
                                                    {fieldDay.summary.route.completed}/{fieldDay.summary.route.stops}{' '}
                                                    stops
                                                </p>
                                                <p className="mt-1 text-xs capitalize text-muted">
                                                    {fieldDay.summary.route.status?.replaceAll('_', ' ') ?? 'No route'}
                                                </p>
                                            </>
                                        ) : (
                                            <span className="text-muted">Pending checkout</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums">
                                        {fieldDay.summary ? (
                                            <>
                                                <p className="font-semibold">
                                                    {fieldDay.summary.payments.count} payment(s)
                                                </p>
                                                <p className="mt-1 text-xs text-muted">
                                                    {entriesOrEmpty(fieldDay.summary.payments.totals)
                                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                                        .join(' · ') || 'No collections'}
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
                                                    {fieldDay.summary.tasks.completed} completed ·{' '}
                                                    {fieldDay.summary.tasks.open} open
                                                </p>
                                                <p className="mt-1 max-w-64 text-pretty text-xs text-muted">
                                                    {fieldDay.summary_note ??
                                                        `${fieldDay.summary.duration_minutes} minutes in field`}
                                                </p>
                                                <p className="mt-1 max-w-64 text-xs text-muted">
                                                    Custody:{' '}
                                                    {entriesOrEmpty(fieldDay.summary.custody.balances)
                                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                                        .join(' · ') || 'No physical cash'}{' '}
                                                    · {fieldDay.summary.custody.pending_count} pending
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
                                        <p className="mt-3 font-semibold">No field check-ins for this date</p>
                                        <p className="mt-1 text-sm text-muted">
                                            Collector check-ins will appear after location capture is confirmed.
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
                    <p className="text-pretty text-sm text-muted">
                        Coordinates are captured only when the collector presses start or finish and grants browser
                        permission.
                    </p>
                </div>
                <div className="card flex items-start gap-3 p-5">
                    <TimerReset className="mt-0.5 shrink-0 text-brand" size={18} />
                    <p className="text-pretty text-sm text-muted">
                        Route stops and visit outcomes will build on these bounded field-day records.
                    </p>
                </div>
            </section>
        </AppLayout>
    );
}
