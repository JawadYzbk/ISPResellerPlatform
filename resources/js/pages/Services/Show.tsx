import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleAlert, Network, RefreshCw, Wifi, WifiOff, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import { StatusBadge, type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatBytes, formatDate, formatDuration, formatMoney } from '@/lib/format';
import type { PageProps } from '@/types';

type ServiceDetails = {
    public_id: string;
    username: string;
    status: Status;
    network_state: Status;
    provisioning_mode: string;
    expires_at: string | null;
    billing_anchor_day: number | null;
    suspension_reason: string | null;
    paused_until: string | null;
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
    pending_plan_change: {
        plan: { public_id: string; name: string; download_kbps: number; upload_kbps: number; duration_days: number };
        requested_at: string | null;
        apply_at: string | null;
    } | null;
    pending_billing_cycle: BillingCycleQuote | null;
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

type PlanPreview = {
    effective: 'immediate' | 'next_cycle';
    apply_at: string | null;
    currency: string;
    old_credit_amount: number;
    new_charge_amount: number;
    net_amount: number;
    remaining_seconds: number;
};

type BillingCycleQuote = {
    anchor_day: number;
    starts_at: string | null;
    ends_at: string;
    billable_days: number;
    cycle_days: number;
    full_amount: number;
    prorated_amount: number;
    currency: string;
    requested_at?: string | null;
};

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
    canPause?: boolean;
    canTerminate?: boolean;
    canChangePlan?: boolean;
    canChangeBillingCycle?: boolean;
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
    canPause = false,
    canTerminate = false,
    canChangePlan = false,
    canChangeBillingCycle = false,
    canDisconnectSession = false,
    plans,
}: Props) {
    const planForm = useForm({ plan_id: plans[0]?.id.toString() ?? '', effective: 'next_cycle' });
    const [planPreview, setPlanPreview] = useState<PlanPreview | null>(null);
    const [planPreviewError, setPlanPreviewError] = useState<string | null>(null);
    const cycleForm = useForm({
        anchor_day: (service.pending_billing_cycle?.anchor_day ?? service.billing_anchor_day ?? 1).toString(),
    });
    const [cyclePreview, setCyclePreview] = useState<BillingCycleQuote | null>(null);
    const [cyclePreviewError, setCyclePreviewError] = useState<string | null>(null);

    const setPlanSelection = (field: 'plan_id' | 'effective', value: string) => {
        setPlanPreview(null);
        setPlanPreviewError(null);
        planForm.setData(field, value);
    };

    const submitPlanChange = () => {
        planForm.transform((data) => ({ ...data, plan_id: Number(data.plan_id) }));
        planForm.post(`/services/${service.public_id}/change-plan`);
    };

    useEffect(() => {
        if (!canChangePlan || !planForm.data.plan_id || !planForm.data.effective) {
            return;
        }

        const controller = new AbortController();
        fetch(
            `/services/${service.public_id}/plan-change-preview?plan_id=${encodeURIComponent(planForm.data.plan_id)}&effective=${encodeURIComponent(planForm.data.effective)}`,
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                const payload = (await response.json()) as PlanPreview | { message?: string };
                if (!response.ok || !('effective' in payload)) {
                    throw new Error(
                        'message' in payload && payload.message ? payload.message : 'The plan quote is unavailable.',
                    );
                }
                setPlanPreviewError(null);
                setPlanPreview(payload);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setPlanPreview(null);
                setPlanPreviewError(error instanceof Error ? error.message : 'The plan quote is unavailable.');
            });

        return () => controller.abort();
    }, [canChangePlan, planForm.data.effective, planForm.data.plan_id, service.public_id]);

    useEffect(() => {
        if (!canChangeBillingCycle || !cycleForm.data.anchor_day) return;

        const controller = new AbortController();
        fetch(
            `/services/${service.public_id}/billing-cycle-preview?anchor_day=${encodeURIComponent(cycleForm.data.anchor_day)}`,
            {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                const payload = (await response.json()) as BillingCycleQuote | { message?: string };
                if (!response.ok || !('anchor_day' in payload)) {
                    throw new Error(
                        'message' in payload && payload.message
                            ? payload.message
                            : 'The billing-cycle quote is unavailable.',
                    );
                }
                setCyclePreviewError(null);
                setCyclePreview(payload);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setCyclePreview(null);
                setCyclePreviewError(
                    error instanceof Error ? error.message : 'The billing-cycle quote is unavailable.',
                );
            });

        return () => controller.abort();
    }, [canChangeBillingCycle, cycleForm.data.anchor_day, service.public_id]);

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
                        <ConfirmDialog
                            title="Suspend this service?"
                            description="The service will be suspended and its network access will be restricted."
                            confirmLabel="Suspend service"
                            destructive
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/suspend`, { reason: 'manual_operator' })
                            }
                        >
                            <button type="button" className="button-secondary text-coral">
                                Suspend
                            </button>
                        </ConfirmDialog>
                    )}
                    {service.status === 'active' && canPause && (
                        <ConfirmDialog
                            title="Pause this service?"
                            description="The service will pause without closing the account or removing its plan."
                            confirmLabel="Pause service"
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/pause`, { reason: 'customer_requested' })
                            }
                        >
                            <button type="button" className="button-secondary text-violet-700">
                                Pause
                            </button>
                        </ConfirmDialog>
                    )}
                    {((service.status === 'suspended' && canActivate) ||
                        (service.status === 'paused' && canActivate)) && (
                        <ConfirmDialog
                            title={
                                service.status === 'paused' ? 'Resume this service from pause?' : 'Resume this service?'
                            }
                            description="The service will be active again and network provisioning will resume."
                            confirmLabel="Resume service"
                            onConfirm={() => router.post(`/services/${service.public_id}/resume`)}
                        >
                            <button type="button" className="button-primary">
                                Resume
                            </button>
                        </ConfirmDialog>
                    )}
                    {canTerminate && service.status !== 'terminated' && (
                        <ConfirmDialog
                            title="Terminate this service?"
                            description="Equipment will be marked for recovery and this service cannot be reactivated."
                            confirmLabel="Terminate service"
                            destructive
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/terminate`, { reason: 'manual_operator' })
                            }
                        >
                            <button type="button" className="button-secondary text-coral">
                                Terminate
                            </button>
                        </ConfirmDialog>
                    )}
                    {service.status !== 'terminated' && (canActivate || canSuspend || canPause) && (
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
                                            <ConfirmDialog
                                                title="Disconnect the current network session?"
                                                description="The active network session will be disconnected immediately."
                                                confirmLabel="Disconnect session"
                                                destructive
                                                onConfirm={() =>
                                                    router.post(`/services/${service.public_id}/disconnect-session`)
                                                }
                                            >
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                >
                                                    <WifiOff size={14} /> Disconnect
                                                </button>
                                            </ConfirmDialog>
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
                    {service.pending_plan_change && (
                        <div className="card border-brand/20 bg-brand-soft/20 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">Scheduled plan change</p>
                                    <h2 className="mt-1 text-base font-semibold">
                                        {service.pending_plan_change.plan.name} at next renewal
                                    </h2>
                                    <p className="mt-1 text-sm text-muted">
                                        Applies {formatDate(service.pending_plan_change.apply_at)} · Requested{' '}
                                        {formatDate(service.pending_plan_change.requested_at)}
                                    </p>
                                </div>
                                {canChangePlan && (
                                    <ConfirmDialog
                                        title="Cancel this scheduled plan change?"
                                        description="The customer will keep the current plan at the next renewal. No ledger entry will be posted."
                                        confirmLabel="Cancel scheduled change"
                                        destructive
                                        onConfirm={() => router.delete(`/services/${service.public_id}/change-plan`)}
                                    >
                                        <button
                                            type="button"
                                            className="button-secondary inline-flex items-center gap-1.5 text-coral"
                                        >
                                            <X size={15} /> Cancel
                                        </button>
                                    </ConfirmDialog>
                                )}
                            </div>
                        </div>
                    )}
                    {service.pending_billing_cycle && (
                        <div className="card border-brand/20 bg-brand-soft/20 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">Scheduled billing cycle</p>
                                    <h2 className="mt-1 text-base font-semibold text-balance">
                                        Move to day {service.pending_billing_cycle.anchor_day}
                                    </h2>
                                    <p className="mt-1 text-sm text-pretty text-muted">
                                        The transition invoice is{' '}
                                        {formatMoney(
                                            service.pending_billing_cycle.prorated_amount,
                                            service.pending_billing_cycle.currency,
                                        )}{' '}
                                        for {service.pending_billing_cycle.billable_days} days, through{' '}
                                        {formatDate(service.pending_billing_cycle.ends_at)}.
                                    </p>
                                </div>
                                {canChangeBillingCycle && (
                                    <ConfirmDialog
                                        title="Cancel this scheduled billing-cycle change?"
                                        description="The current anchor stays in place. Cancellation is blocked after its renewal invoice is created."
                                        confirmLabel="Cancel scheduled change"
                                        destructive
                                        onConfirm={() =>
                                            router.delete(`/services/${service.public_id}/billing-cycle`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <button type="button" className="button-secondary text-coral">
                                            <X size={15} /> Cancel
                                        </button>
                                    </ConfirmDialog>
                                )}
                            </div>
                        </div>
                    )}
                    {canChangeBillingCycle && service.status !== 'terminated' && (
                        <div className="card p-6">
                            <h2 className="section-title text-balance">Billing cycle</h2>
                            <p className="mt-1 text-sm text-pretty text-muted">
                                {service.billing_anchor_day
                                    ? `Invoices currently renew on day ${service.billing_anchor_day} of each month.`
                                    : 'This service currently follows the plan duration.'}
                            </p>
                            <form onSubmit={(event) => event.preventDefault()} className="mt-5 space-y-4">
                                <label>
                                    <span className="field-label">Monthly anchor day</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={cycleForm.data.anchor_day}
                                        onChange={(event) => cycleForm.setData('anchor_day', event.target.value)}
                                    >
                                        {Array.from({ length: 31 }, (_, index) => index + 1).map((day) => (
                                            <option key={day} value={day}>
                                                Day {day}
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {cycleForm.errors.anchor_day && (
                                        <p className="field-error">{cycleForm.errors.anchor_day}</p>
                                    )}
                                </label>
                                {cyclePreviewError && <p className="field-error">{cyclePreviewError}</p>}
                                {cyclePreview && (
                                    <div className="rounded-xl border border-line bg-sand/60 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-sm font-semibold">Transition quote</p>
                                            <p className="text-sm font-semibold text-brand tabular-nums">
                                                {formatMoney(cyclePreview.prorated_amount, cyclePreview.currency)}
                                            </p>
                                        </div>
                                        <p className="mt-2 text-xs text-pretty text-muted">
                                            {cyclePreview.billable_days} of {cyclePreview.cycle_days} days ·{' '}
                                            {formatDate(cyclePreview.starts_at)} through{' '}
                                            {formatDate(cyclePreview.ends_at)}. The normal monthly price is{' '}
                                            {formatMoney(cyclePreview.full_amount, cyclePreview.currency)}.
                                        </p>
                                    </div>
                                )}
                                <ConfirmDialog
                                    title="Schedule this billing-cycle change?"
                                    description="The displayed prorated amount will be used for the transition invoice. Once that invoice exists, settle or void it before changing the schedule."
                                    confirmLabel={service.expires_at ? 'Schedule change' : 'Set billing anchor'}
                                    onConfirm={() => {
                                        cycleForm.transform((data) => ({ anchor_day: Number(data.anchor_day) }));
                                        cycleForm.post(`/services/${service.public_id}/billing-cycle`, {
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <button
                                        type="button"
                                        className="button-primary w-full justify-center"
                                        disabled={
                                            cycleForm.processing ||
                                            !cyclePreview ||
                                            (!service.pending_billing_cycle &&
                                                service.billing_anchor_day === Number(cycleForm.data.anchor_day))
                                        }
                                    >
                                        <CalendarDays size={16} />
                                        {service.expires_at ? 'Schedule billing cycle' : 'Set billing anchor'}
                                    </button>
                                </ConfirmDialog>
                            </form>
                        </div>
                    )}
                    {canChangePlan && service.status !== 'terminated' && plans.length > 0 && (
                        <div className="card p-6">
                            <h2 className="section-title">Change plan</h2>
                            <p className="mt-1 text-sm text-muted">
                                Schedule the next cycle or apply a prorated change now.
                            </p>
                            <form onSubmit={(event) => event.preventDefault()} className="mt-5 space-y-4">
                                <label>
                                    <span className="field-label">New plan</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={planForm.data.plan_id}
                                        onChange={(event) => setPlanSelection('plan_id', event.target.value)}
                                    >
                                        {plans.map((plan) => (
                                            <option key={plan.id} value={plan.id}>
                                                {plan.name} · {plan.download_kbps / 1000}/{plan.upload_kbps / 1000} Mbps
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {planForm.errors.plan_id && (
                                        <p className="field-error">{planForm.errors.plan_id}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">Effective</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={planForm.data.effective}
                                        onChange={(event) => setPlanSelection('effective', event.target.value)}
                                    >
                                        <option value="next_cycle">At next renewal</option>
                                        <option value="immediate">Immediately with proration</option>
                                    </ResponsiveSelect>
                                </label>
                                {planForm.data.effective === 'immediate' && (
                                    <p className="rounded-lg bg-sand px-3 py-2 text-xs text-muted">
                                        The unused part of the current plan is credited and the remainder of the new
                                        plan is charged in the customer ledger currency.
                                    </p>
                                )}
                                {planPreviewError && <p className="field-error">{planPreviewError}</p>}
                                {planPreview && (
                                    <div className="rounded-xl border border-line bg-sand/60 p-4 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-semibold">
                                                {planPreview.effective === 'immediate'
                                                    ? 'Immediate quote'
                                                    : 'Scheduled change'}
                                            </span>
                                            <span className="text-xs font-semibold text-brand">
                                                {planPreview.effective === 'immediate'
                                                    ? 'Now'
                                                    : `At ${formatDate(planPreview.apply_at)}`}
                                            </span>
                                        </div>
                                        {planPreview.effective === 'immediate' ? (
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <p className="text-xs text-muted">Unused credit</p>
                                                    <p className="mt-1 font-semibold text-emerald-700">
                                                        {formatMoney(
                                                            planPreview.old_credit_amount,
                                                            planPreview.currency,
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted">New plan charge</p>
                                                    <p className="mt-1 font-semibold">
                                                        {formatMoney(
                                                            planPreview.new_charge_amount,
                                                            planPreview.currency,
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted">Net ledger impact</p>
                                                    <p className="mt-1 font-semibold">
                                                        {formatMoney(planPreview.net_amount, planPreview.currency)}
                                                    </p>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="mt-2 text-xs text-muted">
                                                No charge is posted until renewal. The new plan will be applied when
                                                this service expires.
                                            </p>
                                        )}
                                    </div>
                                )}
                                <ConfirmDialog
                                    title={
                                        planForm.data.effective === 'immediate'
                                            ? 'Apply this plan change now?'
                                            : 'Schedule this plan change?'
                                    }
                                    description={
                                        planForm.data.effective === 'immediate'
                                            ? 'The current plan credit and new plan charge will be posted to the customer ledger immediately.'
                                            : 'The current plan remains active until renewal, then the selected plan will be applied.'
                                    }
                                    confirmLabel={
                                        planForm.data.effective === 'immediate' ? 'Apply now' : 'Schedule change'
                                    }
                                    onConfirm={submitPlanChange}
                                >
                                    <button
                                        type="button"
                                        className="button-secondary w-full"
                                        disabled={planForm.processing || !planPreview}
                                    >
                                        {planForm.processing
                                            ? 'Applying…'
                                            : planForm.data.effective === 'immediate'
                                              ? 'Apply plan change'
                                              : 'Schedule plan change'}
                                    </button>
                                </ConfirmDialog>
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
