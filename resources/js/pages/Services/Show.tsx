import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleAlert, Network, RefreshCw, Wifi, WifiOff } from 'lucide-react';
import { useEffect } from 'react';

import { StatusBadge, type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatBytes, formatDate, formatDuration } from '@/lib/format';
import type { PageProps } from '@/types';

type ServiceDetails = {
    public_id: string;
    username: string;
    status: Status;
    network_state: Status;
    provisioning_mode: string;
    expires_at: string | null;
    customer: { public_id: string; code: string; first_name: string; last_name: string | null } | null;
    plan: {
        id: number;
        public_id: string;
        name: string;
        download_kbps: number;
        upload_kbps: number;
        amount_minor: number;
        currency: string;
    } | null;
    router: { public_id: string; name: string; status: Status } | null;
    usage: { used_bytes: number; quota_bytes: number };
    equipment: { serial_number: string; assigned_at: string | null; item: { sku: string; name: string } | null }[];
};

type PlanOption = {
    id: number;
    public_id: string;
    name: string;
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    amount_minor: number;
    currency: string;
};

type LiveSession = {
    username: string;
    acct_session_id: string;
    nasname: string | null;
    framed_ip: string | null;
    started_at: string | null;
    last_seen_at: string | null;
    input_octets: number;
    output_octets: number;
} | null;

type Props = PageProps & {
    service: ServiceDetails;
    liveSession: LiveSession;
    usageLast24h: { usage_date: string; input_octets: number; output_octets: number; total_octets: number }[];
    routerHealth: { status: string; latency_ms: number | null; observed_at: string }[];
    recentCommands: {
        id: string;
        action: string;
        status: Status;
        attempts: number;
        last_error: string | null;
        completed_at: string | null;
    }[];
    canActivate?: boolean;
    canSuspend?: boolean;
    canTerminate?: boolean;
    canChangePlan?: boolean;
    canDisconnectSession?: boolean;
    plans: PlanOption[];
};

export default function ServiceShow({
    service,
    liveSession,
    usageLast24h,
    routerHealth,
    recentCommands,
    canActivate = false,
    canSuspend = false,
    canTerminate = false,
    canChangePlan = false,
    canDisconnectSession = false,
    plans,
}: Props) {
    const planForm = useForm({ plan_id: plans[0]?.id.toString() ?? '', effective: 'next_cycle' });

    const submitPlanChange = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        planForm.transform((data) => ({ ...data, plan_id: Number(data.plan_id) }));
        planForm.post(`/services/${service.public_id}/change-plan`);
    };

    useEffect(() => {
        const reloadWhenVisible = () => {
            if (document.visibilityState !== 'visible') return;
            router.reload({
                only: ['service', 'liveSession', 'usageLast24h', 'routerHealth', 'recentCommands'],
            });
        };
        const interval = window.setInterval(reloadWhenVisible, 10_000);
        document.addEventListener('visibilitychange', reloadWhenVisible);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', reloadWhenVisible);
        };
    }, []);

    return (
        <AppLayout>
            <Head title={service.username} />
            <Link
                href="/services"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to services
            </Link>
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p className="eyebrow">Subscriber service</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="page-title">{service.username}</h1>
                        <StatusBadge status={service.status} />
                        <StatusBadge status={service.network_state} />
                    </div>
                    {service.customer && (
                        <Link
                            href={`/customers/${service.customer.public_id}`}
                            className="mt-2 inline-block text-sm font-semibold text-brand"
                        >
                            {service.customer.first_name} {service.customer.last_name ?? ''} · {service.customer.code}
                        </Link>
                    )}
                </div>
                <div className="flex flex-wrap gap-2">
                    {service.status === 'active' && canSuspend && (
                        <button
                            type="button"
                            className="button-secondary text-coral"
                            onClick={() =>
                                window.confirm('Suspend this service?') &&
                                router.post(`/services/${service.public_id}/suspend`, { reason: 'manual_operator' })
                            }
                        >
                            Suspend
                        </button>
                    )}
                    {service.status === 'suspended' && canActivate && (
                        <button
                            type="button"
                            className="button-primary"
                            onClick={() =>
                                window.confirm('Resume this service?') &&
                                router.post(`/services/${service.public_id}/resume`)
                            }
                        >
                            Resume
                        </button>
                    )}
                    {canTerminate && service.status !== 'terminated' && (
                        <button
                            type="button"
                            className="button-secondary text-coral"
                            onClick={() =>
                                window.confirm('Terminate this service?') &&
                                router.post(`/services/${service.public_id}/terminate`, { reason: 'manual_operator' })
                            }
                        >
                            Terminate
                        </button>
                    )}
                    {service.status !== 'terminated' && (canActivate || canSuspend) && (
                        <button
                            type="button"
                            className="button-secondary"
                            onClick={() => router.post(`/services/${service.public_id}/resync`)}
                        >
                            <RefreshCw size={16} /> Re-sync
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="card p-5">
                            <p className="text-xs text-muted">Plan</p>
                            <p className="mt-2 font-semibold">{service.plan?.name ?? 'No plan'}</p>
                            <p className="mt-1 text-xs text-muted">
                                {service.plan
                                    ? `${service.plan.download_kbps / 1000} / ${service.plan.upload_kbps / 1000} Mbps`
                                    : 'Unassigned'}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">Router</p>
                            <p className="mt-2 font-semibold">{service.router?.name ?? 'No router'}</p>
                            <p className="mt-1 text-xs capitalize text-muted">
                                {service.router?.status ?? 'Unassigned'}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">Expires</p>
                            <p className="mt-2 flex items-center gap-1.5 font-semibold">
                                <CalendarDays size={14} className="text-muted" /> {formatDate(service.expires_at)}
                            </p>
                            <p className="mt-1 text-xs capitalize text-muted">
                                {service.provisioning_mode.replace('_', ' ')}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">Quota</p>
                            <p className="mt-2 font-semibold">
                                {service.usage.quota_bytes > 0
                                    ? `${formatBytes(service.usage.used_bytes)} / ${formatBytes(service.usage.quota_bytes)}`
                                    : 'Unlimited'}
                            </p>
                            {service.usage.quota_bytes > 0 && (
                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-sand">
                                    <div
                                        className="h-full rounded-full bg-brand"
                                        style={{
                                            width: `${Math.min(100, (service.usage.used_bytes / service.usage.quota_bytes) * 100)}%`,
                                        }}
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <Wifi size={18} className="text-brand" />
                                <h2 className="section-title">Current session</h2>
                            </div>
                            <p className="mt-1 text-sm text-muted">
                                Live accounting state from the latest interim update.
                            </p>
                        </div>
                        <div className="p-6">
                            {liveSession ? (
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <p className="text-xs text-muted">Status</p>
                                        <p className="mt-1 font-semibold text-emerald-700">Online</p>
                                        <p className="mt-1 text-xs text-muted">
                                            Uptime {formatDuration(liveSession.started_at, liveSession.last_seen_at)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted">Address</p>
                                        <p className="mt-1 font-semibold">{liveSession.framed_ip ?? 'Not reported'}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            NAS {liveSession.nasname ?? 'Not reported'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted">Traffic</p>
                                        <p className="mt-1 font-semibold">↓ {formatBytes(liveSession.input_octets)}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            ↑ {formatBytes(liveSession.output_octets)}
                                        </p>
                                    </div>
                                    <div className="flex items-end justify-start sm:justify-end">
                                        {canDisconnectSession && (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                onClick={() =>
                                                    window.confirm('Disconnect the current network session?') &&
                                                    router.post(`/services/${service.public_id}/disconnect-session`)
                                                }
                                            >
                                                <WifiOff size={14} /> Disconnect
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-center gap-3 text-sm text-muted">
                                    <WifiOff size={18} /> No active session is currently reported.
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <Network size={18} className="text-brand" />
                                <h2 className="section-title">Usage, last 24 hours</h2>
                            </div>
                        </div>
                        <div className="divide-y divide-line">
                            {usageLast24h.map((row) => (
                                <div
                                    key={row.usage_date}
                                    className="flex items-center justify-between gap-4 px-6 py-4 text-sm"
                                >
                                    <span className="text-muted">{formatDate(row.usage_date)}</span>
                                    <span className="font-semibold">{formatBytes(row.total_octets)}</span>
                                </div>
                            ))}
                            {usageLast24h.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">No daily usage has been rolled up yet.</p>
                            )}
                        </div>
                    </div>
                </div>

                <aside className="space-y-6">
                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <RefreshCw size={18} className="text-brand" />
                                <h2 className="section-title">Recent commands</h2>
                            </div>
                        </div>
                        <div className="divide-y divide-line">
                            {recentCommands.map((command) => (
                                <div key={command.id} className="px-6 py-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-sm font-semibold capitalize">{command.action}</p>
                                        <StatusBadge status={command.status} />
                                    </div>
                                    <p className="mt-1 text-xs text-muted">
                                        {command.attempts} attempt(s) · {formatDate(command.completed_at)}
                                    </p>
                                    {command.last_error && (
                                        <p className="mt-2 flex items-start gap-1.5 text-xs text-coral">
                                            <CircleAlert size={13} className="mt-0.5 shrink-0" /> {command.last_error}
                                        </p>
                                    )}
                                </div>
                            ))}
                            {recentCommands.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">No network commands have been queued.</p>
                            )}
                        </div>
                    </div>
                    {canChangePlan && service.status !== 'terminated' && plans.length > 0 && (
                        <div className="card p-6">
                            <h2 className="section-title">Change plan</h2>
                            <p className="mt-1 text-sm text-muted">
                                Schedule the next cycle or apply a prorated change now.
                            </p>
                            <form onSubmit={submitPlanChange} className="mt-5 space-y-4">
                                <label>
                                    <span className="field-label">New plan</span>
                                    <select
                                        className="field"
                                        value={planForm.data.plan_id}
                                        onChange={(event) => planForm.setData('plan_id', event.target.value)}
                                    >
                                        {plans.map((plan) => (
                                            <option key={plan.id} value={plan.id}>
                                                {plan.name} · {plan.download_kbps / 1000}/{plan.upload_kbps / 1000} Mbps
                                            </option>
                                        ))}
                                    </select>
                                    {planForm.errors.plan_id && (
                                        <p className="field-error">{planForm.errors.plan_id}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">Effective</span>
                                    <select
                                        className="field"
                                        value={planForm.data.effective}
                                        onChange={(event) => planForm.setData('effective', event.target.value)}
                                    >
                                        <option value="next_cycle">At next renewal</option>
                                        <option value="immediate">Immediately with proration</option>
                                    </select>
                                </label>
                                {planForm.data.effective === 'immediate' && (
                                    <p className="rounded-lg bg-sand px-3 py-2 text-xs text-muted">
                                        The unused part of the current plan is credited and the remainder of the new
                                        plan is charged in the customer ledger currency.
                                    </p>
                                )}
                                <button
                                    type="submit"
                                    className="button-secondary w-full"
                                    disabled={planForm.processing}
                                >
                                    Apply plan change
                                </button>
                            </form>
                        </div>
                    )}
                    <div className="card p-6">
                        <h2 className="section-title">Assigned equipment</h2>
                        <div className="mt-4 space-y-4">
                            {service.equipment.map((unit) => (
                                <div key={unit.serial_number}>
                                    <p className="text-sm font-semibold">{unit.item?.name ?? 'Serialized equipment'}</p>
                                    <p className="mt-1 text-xs text-muted">
                                        {unit.serial_number} · Assigned {formatDate(unit.assigned_at)}
                                    </p>
                                </div>
                            ))}
                            {service.equipment.length === 0 && (
                                <p className="text-sm text-muted">No equipment is assigned to this service.</p>
                            )}
                        </div>
                    </div>
                    <div className="card p-6">
                        <h2 className="section-title">Router health</h2>
                        <div className="mt-4 space-y-3">
                            {routerHealth.map((metric, index) => (
                                <div
                                    key={`${metric.observed_at}-${index}`}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span className="capitalize text-muted">{metric.status}</span>
                                    <span className="font-semibold">
                                        {metric.latency_ms === null ? '—' : `${metric.latency_ms} ms`}
                                    </span>
                                </div>
                            ))}
                            {routerHealth.length === 0 && (
                                <p className="text-sm text-muted">No router observations available.</p>
                            )}
                        </div>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
