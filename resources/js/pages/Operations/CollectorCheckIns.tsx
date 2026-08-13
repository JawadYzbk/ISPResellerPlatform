import { Head, router } from '@inertiajs/react';
import { ExternalLink, MapPinned, Navigation, TimerReset } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';

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
                    <table className="w-full min-w-[860px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase text-muted">
                                <th className="px-5 py-3.5 text-start">Collector</th>
                                <th className="px-5 py-3.5 text-start">Started</th>
                                <th className="px-5 py-3.5 text-start">Finished</th>
                                <th className="px-5 py-3.5 text-start">Check-in location</th>
                                <th className="px-5 py-3.5 text-start">Check-out location</th>
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
                                </tr>
                            ))}
                            {fieldDays.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-14 text-center">
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
