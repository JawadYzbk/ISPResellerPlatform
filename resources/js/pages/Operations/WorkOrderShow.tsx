import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, CheckCircle2, ClipboardCheck, Clock3, Download, Images, UserRound } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';

type WorkOrder = {
    public_id: string;
    number: string;
    type: string;
    status: 'pending' | 'assigned' | 'in_progress' | 'completed' | 'failed' | 'cancelled';
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    failure_reason: string | null;
    checklist: Record<string, boolean | string>;
    metadata: Record<string, unknown>;
    customer: { public_id: string; code: string; name: string } | null;
    service: { public_id: string; username: string } | null;
    assignee: { name: string } | null;
    events: { id: number; event_type: string; from_status: string | null; to_status: string | null; actor: string | null; created_at: string | null }[];
    media: { id: string; filename: string; mime_type: string; size_bytes: number; purpose: string; created_at: string | null; download_url: string }[];
};

function checklistLabel(key: string): string {
    return key.replaceAll('_', ' ');
}

function isChecked(value: boolean | string): boolean {
    return value === true || value === 'true';
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 ** 2) return (bytes / 1024).toFixed(1) + ' KB';
    if (bytes < 1024 ** 3) return (bytes / 1024 ** 2).toFixed(1) + ' MB';

    return (bytes / 1024 ** 3).toFixed(1) + ' GB';
}

type Props = {
    workOrder: WorkOrder;
    scheduledAtLocal: string | null;
    timezone: string;
};

export default function WorkOrderShowPage({ workOrder, scheduledAtLocal, timezone }: Props) {
    const canComplete = ['assigned', 'in_progress'].includes(workOrder.status);
    const scheduleForm = useForm({ scheduled_at: scheduledAtLocal ?? '' });

    const submitSchedule = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        scheduleForm.post('/operations/work-orders/' + workOrder.public_id + '/schedule');
    };

    return (
        <AppLayout>
            <Head title={workOrder.number} />
            <Link href="/operations/work-orders" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand">
                <ArrowLeft size={16} /> Back to work orders
            </Link>

            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Field operations · {workOrder.type.replace('_', ' ')}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="page-title">{workOrder.number}</h1>
                        <StatusBadge status={workOrder.status} />
                    </div>
                    <p className="page-subtitle">Scheduled {formatDate(workOrder.scheduled_at)} · Assigned to {workOrder.assignee?.name ?? 'nobody'}</p>
                </div>
                {canComplete && (
                    <button type="button" className="button-primary" onClick={() => router.post(`/operations/work-orders/${workOrder.public_id}/complete`, { idempotency_key: crypto.randomUUID() })}>
                        <CheckCircle2 size={16} /> Complete work order
                    </button>
                )}
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <aside className="space-y-6">
                    <div className="card p-6">
                        <div className="flex items-center gap-2"><UserRound size={17} className="text-brand" /><h2 className="section-title">Assignment</h2></div>
                        <dl className="mt-5 space-y-4 text-sm">
                            <div><dt className="field-label">Customer</dt><dd className="mt-1 font-semibold">{workOrder.customer ? <Link href={`/customers/${workOrder.customer.public_id}`} className="hover:text-brand">{workOrder.customer.name}</Link> : 'No customer linked'}<span className="mt-1 block text-xs font-normal text-muted">{workOrder.customer?.code ?? ''}</span></dd></div>
                            <div><dt className="field-label">Service</dt><dd className="mt-1 font-semibold">{workOrder.service?.username ?? 'No service linked'}</dd></div>
                            <div><dt className="field-label">Operator</dt><dd className="mt-1 font-semibold">{workOrder.assignee?.name ?? 'Unassigned'}</dd></div>
                        </dl>
                    </div>
                    <div className="card p-6">
                        <div className="flex items-center gap-2"><Clock3 size={17} className="text-brand" /><h2 className="section-title">Timing</h2></div>
                        <dl className="mt-5 space-y-4 text-sm">
                            <div className="flex justify-between gap-4"><dt className="text-muted">Scheduled</dt><dd className="font-semibold">{formatDate(workOrder.scheduled_at)}</dd></div>
                            <div className="flex justify-between gap-4"><dt className="text-muted">Started</dt><dd className="font-semibold">{formatDate(workOrder.started_at)}</dd></div>
                            <div className="flex justify-between gap-4"><dt className="text-muted">Completed</dt><dd className="font-semibold">{formatDate(workOrder.completed_at)}</dd></div>
                        </dl>
                        {!['completed', 'cancelled'].includes(workOrder.status) && (
                            <form onSubmit={submitSchedule} className="mt-5 space-y-3 border-t border-line pt-5">
                                <div className="flex items-center gap-2"><CalendarClock size={15} className="text-brand" /><span className="text-sm font-semibold">Reschedule</span></div>
                                <label><span className="field-label">Tenant local time ({timezone})</span><input type="datetime-local" className="field" value={scheduleForm.data.scheduled_at} onChange={(event) => scheduleForm.setData('scheduled_at', event.target.value)} />{scheduleForm.errors.scheduled_at && <p className="field-error">{scheduleForm.errors.scheduled_at}</p>}</label>
                                <button type="submit" className="button-secondary w-full" disabled={scheduleForm.processing}>Save schedule</button>
                            </form>
                        )}
                        {workOrder.failure_reason && <p className="mt-5 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{workOrder.failure_reason}</p>}
                    </div>
                </aside>

                <div className="space-y-6">
                    <section className="card p-6">
                        <div className="flex items-center gap-2"><ClipboardCheck size={17} className="text-brand" /><h2 className="section-title">Completion checklist</h2></div>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {Object.entries(workOrder.checklist).map(([key, value]) => (
                                <div key={key} className="flex items-center justify-between gap-4 rounded-lg border border-line px-4 py-3 text-sm">
                                    <span className="capitalize text-muted">{checklistLabel(key)}</span>
                                    <span className={isChecked(value) ? 'font-semibold text-emerald-700' : 'font-semibold text-coral'}>{isChecked(value) ? 'Checked' : 'Open'}</span>
                                </div>
                            ))}
                            {Object.keys(workOrder.checklist).length === 0 && <p className="text-sm text-muted">No checklist items were recorded.</p>}
                        </div>
                    </section>
                    <section className="card p-6">
                        <div className="flex items-center gap-2"><Images size={17} className="text-brand" /><h2 className="section-title">Site evidence</h2></div>
                        <div className="mt-5 grid gap-3 sm:grid-cols-2">
                            {workOrder.media.map((media) => (
                                <div key={media.id} className="flex items-center justify-between gap-4 rounded-lg border border-line px-4 py-3">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">{media.filename}</p>
                                        <p className="mt-1 text-xs capitalize text-muted">{media.purpose.replace('_', ' ')} · {formatBytes(media.size_bytes)} · {formatDate(media.created_at)}</p>
                                    </div>
                                    <a href={media.download_url} className="button-ghost shrink-0" download>
                                        <Download size={15} /> Download
                                    </a>
                                </div>
                            ))}
                            {workOrder.media.length === 0 && <p className="text-sm text-muted">No site evidence has been uploaded.</p>}
                        </div>
                    </section>
                    <section className="card p-6">
                        <h2 className="section-title">Work-order history</h2>
                        <div className="mt-5 space-y-4">
                            {workOrder.events.map((event) => (
                                <div key={event.id} className="flex gap-3 border-s border-line ps-4">
                                    <div><p className="text-sm font-semibold capitalize">{event.event_type.replace('_', ' ')}</p><p className="mt-1 text-xs text-muted">{event.actor ?? 'System'} · {formatDate(event.created_at)}</p>{event.from_status && <p className="mt-1 text-xs text-muted">{event.from_status.replace('_', ' ')} → {event.to_status?.replace('_', ' ')}</p>}</div>
                                </div>
                            ))}
                            {workOrder.events.length === 0 && <p className="text-sm text-muted">No work-order events yet.</p>}
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
