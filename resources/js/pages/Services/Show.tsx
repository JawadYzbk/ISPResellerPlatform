import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CircleAlert, Network, Plus, RefreshCw, Wifi, WifiOff, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { StatusBadge, type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatBytes, formatDate, formatDuration, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
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
    usage: {
        used_bytes: number;
        quota_bytes: number;
        fup_action: string | null;
        fup_applied_at: string | null;
    };
    equipment: { serial_number: string; assigned_at: string | null; item: { sku: string; name: string } | null }[];
    addons: {
        public_id: string;
        name: string | null;
        description: string | null;
        amount_minor: number | null;
        currency: string | null;
        billing_period_days: number | null;
        quantity: number;
        starts_at: string;
        ends_at: string | null;
        status: string;
    }[];
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
    usageHistory: { usage_date: string; input_octets: number; output_octets: number; total_octets: number }[];
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
    availableAddons: {
        public_id: string;
        name: string;
        description: string | null;
        amount_minor: number;
        currency: string;
        billing_period_days: number | null;
    }[];
};

export default function ServiceShow({
    service,
    liveSession,
    usageLast24h,
    usageHistory,
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
    availableAddons,
}: Props) {
    const page = usePage<PageProps>();
    const t = useMemo(() => createTranslator(page.props.app.locale), [page.props.app.locale]);
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;
    const planForm = useForm({ plan_id: plans[0]?.id.toString() ?? '', effective: 'next_cycle' });
    const [planPreview, setPlanPreview] = useState<PlanPreview | null>(null);
    const [planPreviewError, setPlanPreviewError] = useState<string | null>(null);
    const cycleForm = useForm({
        anchor_day: (service.pending_billing_cycle?.anchor_day ?? service.billing_anchor_day ?? 1).toString(),
    });
    const [cyclePreview, setCyclePreview] = useState<BillingCycleQuote | null>(null);
    const [cyclePreviewError, setCyclePreviewError] = useState<string | null>(null);
    const addonForm = useForm({
        addon_id: availableAddons[0]?.public_id ?? '',
        quantity: '1',
        starts_at: new Date().toISOString().slice(0, 10),
        ends_at: '',
    });

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
                            'message' in payload && payload.message ? t(payload.message) : t('The plan quote is unavailable.'),
                    );
                }
                setPlanPreviewError(null);
                setPlanPreview(payload);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setPlanPreview(null);
                setPlanPreviewError(error instanceof Error ? t(error.message) : t('The plan quote is unavailable.'));
            });

        return () => controller.abort();
    }, [canChangePlan, planForm.data.effective, planForm.data.plan_id, service.public_id, t]);

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
                            ? t(payload.message)
                            : t('The billing-cycle quote is unavailable.'),
                    );
                }
                setCyclePreviewError(null);
                setCyclePreview(payload);
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setCyclePreview(null);
                setCyclePreviewError(
                    error instanceof Error ? t(error.message) : t('The billing-cycle quote is unavailable.'),
                );
            });

        return () => controller.abort();
    }, [canChangeBillingCycle, cycleForm.data.anchor_day, service.public_id, t]);

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
                <ArrowLeft size={16} /> {t('Back to services')}
            </Link>
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p className="eyebrow">{t('Subscriber service')}</p>
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
                            title={t('Suspend this service?')}
                            description={t('The service will be suspended and its network access will be restricted.')}
                            confirmLabel={t('Suspend service')}
                            destructive
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/suspend`, { reason: 'manual_operator' })
                            }
                        >
                            <button type="button" className="button-secondary text-coral">
                                {t('Suspend')}
                            </button>
                        </ConfirmDialog>
                    )}
                    {service.status === 'active' && canPause && (
                        <ConfirmDialog
                            title={t('Pause this service?')}
                            description={t('The service will pause without closing the account or removing its plan.')}
                            confirmLabel={t('Pause service')}
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/pause`, { reason: 'customer_requested' })
                            }
                        >
                            <button type="button" className="button-secondary text-violet-700">
                                {t('Pause')}
                            </button>
                        </ConfirmDialog>
                    )}
                    {((service.status === 'suspended' && canActivate) ||
                        (service.status === 'paused' && canActivate)) && (
                        <ConfirmDialog
                            title={
                                service.status === 'paused'
                                    ? t('Resume this service from pause?')
                                    : t('Resume this service?')
                            }
                            description={t('The service will be active again and network provisioning will resume.')}
                            confirmLabel={t('Resume service')}
                            onConfirm={() => router.post(`/services/${service.public_id}/resume`)}
                        >
                            <button type="button" className="button-primary">
                                {t('Resume')}
                            </button>
                        </ConfirmDialog>
                    )}
                    {canTerminate && service.status !== 'terminated' && (
                        <ConfirmDialog
                            title={t('Terminate this service?')}
                            description={t('Equipment will be marked for recovery and this service cannot be reactivated.')}
                            confirmLabel={t('Terminate service')}
                            destructive
                            onConfirm={() =>
                                router.post(`/services/${service.public_id}/terminate`, { reason: 'manual_operator' })
                            }
                        >
                            <button type="button" className="button-secondary text-coral">
                                {t('Terminate')}
                            </button>
                        </ConfirmDialog>
                    )}
                    {service.status !== 'terminated' && (canActivate || canSuspend || canPause) && (
                        <button
                            type="button"
                            className="button-secondary"
                            onClick={() => router.post(`/services/${service.public_id}/resync`)}
                        >
                            <RefreshCw size={16} /> {t('Re-sync')}
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="card p-5">
                            <p className="text-xs text-muted">{t('Plan')}</p>
                            <p className="mt-2 font-semibold">{service.plan?.name ?? t('No plan')}</p>
                            <p className="mt-1 text-xs text-muted">
                                {service.plan
                                    ? `${service.plan.download_kbps / 1000} / ${service.plan.upload_kbps / 1000} Mbps`
                                    : t('Unassigned')}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">{t('Router')}</p>
                            <p className="mt-2 font-semibold">{service.router?.name ?? t('No router')}</p>
                            <p className="mt-1 text-xs capitalize text-muted">
                                {service.router?.status ? enumLabel(service.router.status, t) : t('Unassigned')}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">{t('Expires')}</p>
                            <p className="mt-2 flex items-center gap-1.5 font-semibold">
                                <CalendarDays size={14} className="text-muted" /> {formatDate(service.expires_at)}
                            </p>
                            <p className="mt-1 text-xs capitalize text-muted">
                                {enumLabel(service.provisioning_mode, t)}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs text-muted">{t('Quota')}</p>
                            <p className="mt-2 font-semibold">
                                {service.usage.quota_bytes > 0
                                    ? `${formatBytes(service.usage.used_bytes)} / ${formatBytes(service.usage.quota_bytes)}`
                                    : t('Unlimited')}
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
                                <h2 className="section-title">{t('Current session')}</h2>
                            </div>
                            <p className="mt-1 text-sm text-muted">
                                {t('Live accounting state from the latest interim update.')}
                            </p>
                        </div>
                        <div className="p-6">
                            {liveSession ? (
                                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <p className="text-xs text-muted">{t('Status')}</p>
                                        <p className="mt-1 font-semibold text-emerald-700">{t('Online')}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('Uptime')} {formatDuration(liveSession.started_at, liveSession.last_seen_at, t)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted">{t('Address')}</p>
                                        <p className="mt-1 font-semibold">{liveSession.framed_ip ?? t('Not reported')}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            NAS {liveSession.nasname ?? t('Not reported')}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted">{t('Traffic')}</p>
                                        <p className="mt-1 font-semibold">↓ {formatBytes(liveSession.input_octets)}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            ↑ {formatBytes(liveSession.output_octets)}
                                        </p>
                                    </div>
                                    <div className="flex items-end justify-start sm:justify-end">
                                        {canDisconnectSession && (
                                            <ConfirmDialog
                                                title={t('Disconnect the current network session?')}
                                                description={t('The active network session will be disconnected immediately.')}
                                                confirmLabel={t('Disconnect session')}
                                                destructive
                                                onConfirm={() =>
                                                    router.post(`/services/${service.public_id}/disconnect-session`)
                                                }
                                            >
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                >
                                                    <WifiOff size={14} /> {t('Disconnect')}
                                                </button>
                                            </ConfirmDialog>
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-center gap-3 text-sm text-muted">
                                    <WifiOff size={18} /> {t('No active session is currently reported.')}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <Network size={18} className="text-brand" />
                                <h2 className="section-title">{t('Usage, last 24 hours')}</h2>
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
                                <p className="px-6 py-8 text-sm text-muted">{t('No daily usage has been rolled up yet.')}</p>
                            )}
                        </div>
                    </div>

                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <Network size={18} className="text-brand" />
                                    <h2 className="section-title">{t('Usage history')}</h2>
                                </div>
                                {service.usage.fup_action && (
                                    <span className="status-badge text-amber-700">
                                        FUP {service.usage.fup_action}
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 text-sm text-muted">
                                {t('Daily RADIUS or session totals for the latest 31 days.')}
                               {service.usage.fup_applied_at
                                    ? ` ${t('FUP applied')} ${formatDate(service.usage.fup_applied_at)}.`
                                    : ` ${t('No FUP action is currently applied.')}`}
                            </p>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[520px] text-start text-sm">
                                <thead className="bg-sand/50 text-xs uppercase tracking-wider text-muted">
                                    <tr>
                                        <th className="px-6 py-3 text-start">{t('Date')}</th>
                                        <th className="px-6 py-3 text-end">{t('Download')}</th>
                                        <th className="px-6 py-3 text-end">{t('Upload')}</th>
                                        <th className="px-6 py-3 text-end">{t('Total')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-line">
                                    {usageHistory.map((row) => (
                                        <tr key={row.usage_date}>
                                            <td className="px-6 py-3 text-muted">{formatDate(row.usage_date)}</td>
                                            <td className="px-6 py-3 text-end tabular-nums">{formatBytes(row.input_octets)}</td>
                                            <td className="px-6 py-3 text-end tabular-nums">{formatBytes(row.output_octets)}</td>
                                            <td className="px-6 py-3 text-end font-semibold tabular-nums">{formatBytes(row.total_octets)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            {usageHistory.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">{t('No daily usage history is available yet.')}</p>
                            )}
                        </div>
                    </div>
                </div>

                <aside className="space-y-6">
                    <div className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <RefreshCw size={18} className="text-brand" />
                                <h2 className="section-title">{t('Recent commands')}</h2>
                            </div>
                        </div>
                        <div className="divide-y divide-line">
                            {recentCommands.map((command) => (
                                <div key={command.id} className="px-6 py-4">
                                    <div className="flex items-start justify-between gap-3">
                                        <p className="text-sm font-semibold capitalize">{enumLabel(command.action, t)}</p>
                                        <StatusBadge status={command.status} />
                                    </div>
                                    <p className="mt-1 text-xs text-muted">
                                        {command.attempts} {t('attempt(s)')} · {formatDate(command.completed_at)}
                                    </p>
                                    {command.last_error && (
                                        <p className="mt-2 flex items-start gap-1.5 text-xs text-coral">
                                            <CircleAlert size={13} className="mt-0.5 shrink-0" /> {command.last_error}
                                        </p>
                                    )}
                                </div>
                            ))}
                            {recentCommands.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">{t('No network commands have been queued.')}</p>
                            )}
                        </div>
                    </div>
                    {service.pending_plan_change && (
                        <div className="card border-brand/20 bg-brand-soft/20 p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="eyebrow">{t('Scheduled plan change')}</p>
                                    <h2 className="mt-1 text-base font-semibold">
                                        {service.pending_plan_change.plan.name} {t('at next renewal')}
                                    </h2>
                                    <p className="mt-1 text-sm text-muted">
                                        {t('Applies')} {formatDate(service.pending_plan_change.apply_at)} · {t('Requested')}{' '}
                                        {formatDate(service.pending_plan_change.requested_at)}
                                    </p>
                                </div>
                                {canChangePlan && (
                                    <ConfirmDialog
                                        title={t('Cancel this scheduled plan change?')}
                                        description={t('The customer will keep the current plan at the next renewal. No ledger entry will be posted.')}
                                        confirmLabel={t('Cancel scheduled change')}
                                        destructive
                                        onConfirm={() => router.delete(`/services/${service.public_id}/change-plan`)}
                                    >
                                        <button
                                            type="button"
                                            className="button-secondary inline-flex items-center gap-1.5 text-coral"
                                        >
                                            <X size={15} /> {t('Cancel')}
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
                                    <p className="eyebrow">{t('Scheduled billing cycle')}</p>
                                    <h2 className="mt-1 text-base font-semibold text-balance">
                                        {t('Move to day')} {service.pending_billing_cycle.anchor_day}
                                    </h2>
                                    <p className="mt-1 text-sm text-pretty text-muted">
                                        {t('The transition invoice is')}{' '}
                                        {formatMoney(
                                            service.pending_billing_cycle.prorated_amount,
                                            service.pending_billing_cycle.currency,
                                        )}{' '}
                                        {t('for')} {service.pending_billing_cycle.billable_days} {t('days')}, {t('through')}{' '}
                                        {formatDate(service.pending_billing_cycle.ends_at)}.
                                    </p>
                                </div>
                                {canChangeBillingCycle && (
                                    <ConfirmDialog
                                        title={t('Cancel this scheduled billing-cycle change?')}
                                        description={t('The current anchor stays in place. Cancellation is blocked after its renewal invoice is created.')}
                                        confirmLabel={t('Cancel scheduled change')}
                                        destructive
                                        onConfirm={() =>
                                            router.delete(`/services/${service.public_id}/billing-cycle`, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        <button type="button" className="button-secondary text-coral">
                                            <X size={15} /> {t('Cancel')}
                                        </button>
                                    </ConfirmDialog>
                                )}
                            </div>
                        </div>
                    )}
                    {canChangeBillingCycle && service.status !== 'terminated' && (
                        <div className="card p-6">
                            <h2 className="section-title text-balance">{t('Billing cycle')}</h2>
                           <p className="mt-1 text-sm text-pretty text-muted">
                               {service.billing_anchor_day
                                    ? `${t('Invoices currently renew on day')} ${service.billing_anchor_day} ${t('of each month.')}`
                                   : t('This service currently follows the plan duration.')}
                            </p>
                            <form onSubmit={(event) => event.preventDefault()} className="mt-5 space-y-4">
                                <label>
                                    <span className="field-label">{t('Monthly anchor day')}</span>
                                    <ResponsiveSelect
                                        id="cycle-anchor-day"
                                        className="field"
                                        {...fieldA11y('cycle-anchor-day', cycleForm.errors.anchor_day)}
                                        value={cycleForm.data.anchor_day}
                                        onChange={(event) => cycleForm.setData('anchor_day', event.target.value)}
                                    >
                                        {Array.from({ length: 31 }, (_, index) => index + 1).map((day) => (
                                            <option key={day} value={day}>
                                                {t('Day')} {day}
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {fieldError('cycle-anchor-day', cycleForm.errors.anchor_day)}
                                </label>
                                {cyclePreviewError && (
                                    <p id="cycle-preview-error" className="field-error" role="alert">
                                        {cyclePreviewError}
                                    </p>
                                )}
                                {cyclePreview && (
                                    <div className="rounded-xl border border-line bg-sand/60 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-sm font-semibold">{t('Transition quote')}</p>
                                            <p className="text-sm font-semibold text-brand tabular-nums">
                                                {formatMoney(cyclePreview.prorated_amount, cyclePreview.currency)}
                                            </p>
                                        </div>
                                       <p className="mt-2 text-xs text-pretty text-muted">
                                            {cyclePreview.billable_days} {t('of')} {cyclePreview.cycle_days} {t('days')} ·{' '}
                                            {formatDate(cyclePreview.starts_at)} {t('through')}{' '}
                                            {formatDate(cyclePreview.ends_at)}. {t('The normal monthly price is')}{' '}
                                            {formatMoney(cyclePreview.full_amount, cyclePreview.currency)}.
                                        </p>
                                    </div>
                                )}
                                <ConfirmDialog
                                    title={t('Schedule this billing-cycle change?')}
                                    description={t('The displayed prorated amount will be used for the transition invoice. Once that invoice exists, settle or void it before changing the schedule.')}
                                    confirmLabel={service.expires_at ? t('Schedule change') : t('Set billing anchor')}
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
                                        {service.expires_at ? t('Schedule billing cycle') : t('Set billing anchor')}
                                    </button>
                                </ConfirmDialog>
                            </form>
                        </div>
                    )}
                    {canChangePlan && service.status !== 'terminated' && plans.length > 0 && (
                        <div className="card p-6">
                            <h2 className="section-title">{t('Change plan')}</h2>
                            <p className="mt-1 text-sm text-muted">
                                {t('Schedule the next cycle or apply a prorated change now.')}
                            </p>
                            <form onSubmit={(event) => event.preventDefault()} className="mt-5 space-y-4">
                                <label>
                                    <span className="field-label">{t('New plan')}</span>
                                    <ResponsiveSelect
                                        id="change-plan"
                                        className="field"
                                        {...fieldA11y('change-plan', planForm.errors.plan_id)}
                                        value={planForm.data.plan_id}
                                        onChange={(event) => setPlanSelection('plan_id', event.target.value)}
                                    >
                                        {plans.map((plan) => (
                                            <option key={plan.id} value={plan.id}>
                                                {plan.name} · {plan.download_kbps / 1000}/{plan.upload_kbps / 1000} Mbps
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {fieldError('change-plan', planForm.errors.plan_id)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Effective')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={planForm.data.effective}
                                        onChange={(event) => setPlanSelection('effective', event.target.value)}
                                    >
                                        <option value="next_cycle">{t('At next renewal')}</option>
                                        <option value="immediate">{t('Immediately with proration')}</option>
                                    </ResponsiveSelect>
                                </label>
                                {planForm.data.effective === 'immediate' && (
                                    <p className="rounded-lg bg-sand px-3 py-2 text-xs text-muted">
                                        {t('The unused part of the current plan is credited and the remainder of the new plan is charged in the customer ledger currency.')}
                                    </p>
                                )}
                                {planPreviewError && (
                                    <p id="change-plan-preview-error" className="field-error" role="alert">
                                        {planPreviewError}
                                    </p>
                                )}
                                {planPreview && (
                                    <div className="rounded-xl border border-line bg-sand/60 p-4 text-sm">
                                        <div className="flex items-center justify-between gap-3">
                                            <span className="font-semibold">
                                                {planPreview.effective === 'immediate'
                                                    ? t('Immediate quote')
                                                    : t('Scheduled change')}
                                            </span>
                                           <span className="text-xs font-semibold text-brand">
                                               {planPreview.effective === 'immediate'
                                                    ? t('Now')
                                                    : `${t('At')} ${formatDate(planPreview.apply_at)}`}
                                           </span>
                                        </div>
                                        {planPreview.effective === 'immediate' ? (
                                            <div className="mt-3 grid gap-3 sm:grid-cols-3">
                                                <div>
                                                    <p className="text-xs text-muted">{t('Unused credit')}</p>
                                                    <p className="mt-1 font-semibold text-emerald-700">
                                                        {formatMoney(
                                                            planPreview.old_credit_amount,
                                                            planPreview.currency,
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted">{t('New plan charge')}</p>
                                                    <p className="mt-1 font-semibold">
                                                        {formatMoney(
                                                            planPreview.new_charge_amount,
                                                            planPreview.currency,
                                                        )}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-xs text-muted">{t('Net ledger impact')}</p>
                                                    <p className="mt-1 font-semibold">
                                                        {formatMoney(planPreview.net_amount, planPreview.currency)}
                                                    </p>
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="mt-2 text-xs text-muted">
                                                {t('No charge is posted until renewal. The new plan will be applied when this service expires.')}
                                            </p>
                                        )}
                                    </div>
                                )}
                                <ConfirmDialog
                                    title={
                                        planForm.data.effective === 'immediate'
                                            ? t('Apply this plan change now?')
                                            : t('Schedule this plan change?')
                                    }
                                    description={
                                        planForm.data.effective === 'immediate'
                                            ? t('The current plan credit and new plan charge will be posted to the customer ledger immediately.')
                                            : t('The current plan remains active until renewal, then the selected plan will be applied.')
                                    }
                                    confirmLabel={
                                        planForm.data.effective === 'immediate' ? t('Apply now') : t('Schedule change')
                                    }
                                    onConfirm={submitPlanChange}
                                >
                                    <button
                                        type="button"
                                        className="button-secondary w-full"
                                        disabled={planForm.processing || !planPreview}
                                    >
                                        {planForm.processing
                                            ? t('Applying…')
                                            : planForm.data.effective === 'immediate'
                                              ? t('Apply plan change')
                                              : t('Schedule plan change')}
                                    </button>
                                </ConfirmDialog>
                            </form>
                        </div>
                    )}
                    {canChangePlan && service.status !== 'terminated' && (
                        <div className="card p-6">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="section-title text-balance">{t('Recurring add-ons')}</h2>
                                    <p className="mt-1 text-sm text-muted text-pretty">
                                        {t('Attach optional recurring charges to this service. They are copied to the next renewal invoice with a fixed price snapshot.')}
                                    </p>
                                </div>
                                <Plus className="text-brand" size={18} />
                            </div>
                            <div className="mt-5 space-y-3">
                                {service.addons.map((addon) => (
                                    <div
                                        key={addon.public_id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-sand/40 p-4"
                                    >
                                        <div>
                                            <p className="font-semibold">{addon.name ?? t('Recurring add-on')}</p>
                                            <p className="mt-1 text-xs text-muted">
                                                {addon.quantity} × {addon.amount_minor === null
                                                    ? t('Price unavailable')
                                                    : formatMoney(addon.amount_minor, addon.currency ?? '')}
                                               {addon.billing_period_days
                                                    ? ` ${t('every')} ${addon.billing_period_days} ${t('days')}`
                                                   : ''}
                                               {' · '}{t('Starts')} {formatDate(addon.starts_at)}
                                                {addon.ends_at ? ` · ${t('Ends')} ${formatDate(addon.ends_at)}` : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span className="status-badge">{enumLabel(addon.status, t)}</span>
                                            {addon.status === 'active' && (
                                                <ConfirmDialog
                                                    title={t('Cancel this add-on?')}
                                                    description={t('The add-on will stop being included in future renewal invoices. Existing invoices are unchanged.')}
                                                    confirmLabel={t('Cancel add-on')}
                                                    onConfirm={() =>
                                                        router.delete(
                                                            `/services/${service.public_id}/addons/${addon.public_id}`,
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    <button type="button" className="button-quiet text-danger">
                                                        <X size={15} />
                                                        {t('Cancel')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {service.addons.length === 0 && (
                                    <p className="rounded-lg bg-sand px-3 py-2 text-sm text-muted">
                                        {t('No recurring add-ons are attached to this service.')}
                                    </p>
                                )}
                            </div>
                            {availableAddons.length > 0 ? (
                                <form
                                    className="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        addonForm.post(`/services/${service.public_id}/addons`, {
                                            preserveScroll: true,
                                            onSuccess: () => addonForm.reset('quantity', 'ends_at'),
                                        });
                                    }}
                                >
                                    <label className="sm:col-span-2">
                                        <span className="field-label">{t('Add-on')}</span>
                                        <ResponsiveSelect
                                            id="service-addon"
                                            className="field"
                                            {...fieldA11y('service-addon', addonForm.errors.addon_id)}
                                            value={addonForm.data.addon_id}
                                            onChange={(event) => addonForm.setData('addon_id', event.target.value)}
                                        >
                                            {availableAddons.map((addon) => (
                                                <option key={addon.public_id} value={addon.public_id}>
                                                    {addon.name} · {formatMoney(addon.amount_minor, addon.currency)}
                                                    {addon.billing_period_days
                                                        ? ` / ${addon.billing_period_days} days`
                                                        : ''}
                                                </option>
                                            ))}
                                        </ResponsiveSelect>
                                        {fieldError('service-addon', addonForm.errors.addon_id)}
                                    </label>
                                    <label>
                                        <span className="field-label">{t('Quantity')}</span>
                                        <input
                                            id="service-addon-quantity"
                                            className="field"
                                            type="number"
                                            min="1"
                                            max="1000"
                                            {...fieldA11y('service-addon-quantity', addonForm.errors.quantity)}
                                            value={addonForm.data.quantity}
                                            onChange={(event) => addonForm.setData('quantity', event.target.value)}
                                        />
                                        {fieldError('service-addon-quantity', addonForm.errors.quantity)}
                                    </label>
                                    <label>
                                        <span className="field-label">{t('Starts')}</span>
                                        <input
                                            id="service-addon-starts-at"
                                            className="field"
                                            type="date"
                                            {...fieldA11y('service-addon-starts-at', addonForm.errors.starts_at)}
                                            value={addonForm.data.starts_at}
                                            onChange={(event) => addonForm.setData('starts_at', event.target.value)}
                                        />
                                        {fieldError('service-addon-starts-at', addonForm.errors.starts_at)}
                                    </label>
                                    <label>
                                        <span className="field-label">{t('Ends')} ({t('optional')})</span>
                                        <input
                                            id="service-addon-ends-at"
                                            className="field"
                                            type="date"
                                            {...fieldA11y('service-addon-ends-at', addonForm.errors.ends_at)}
                                            value={addonForm.data.ends_at}
                                            onChange={(event) => addonForm.setData('ends_at', event.target.value)}
                                        />
                                        {fieldError('service-addon-ends-at', addonForm.errors.ends_at)}
                                    </label>
                                    <div className="flex items-end justify-end sm:col-span-2">
                                        <button type="submit" className="button-primary" disabled={addonForm.processing}>
                                            <Plus size={16} />
                                            {addonForm.processing ? t('Adding…') : t('Add recurring add-on')}
                                        </button>
                                    </div>
                                </form>
                            ) : (
                                <p className="mt-5 border-t border-line pt-5 text-sm text-muted">
                                    {t('Create an active add-on in Plans before attaching one to a service.')}
                                </p>
                            )}
                        </div>
                    )}
                    <div className="card p-6">
                        <h2 className="section-title">{t('Assigned equipment')}</h2>
                        <div className="mt-4 space-y-4">
                            {service.equipment.map((unit) => (
                                <div key={unit.serial_number}>
                                    <p className="text-sm font-semibold">{unit.item?.name ?? t('Serialized equipment')}</p>
                                    <p className="mt-1 text-xs text-muted">
                                        {unit.serial_number} · {t('Assigned')} {formatDate(unit.assigned_at)}
                                    </p>
                                </div>
                            ))}
                            {service.equipment.length === 0 && (
                                <p className="text-sm text-muted">{t('No equipment is assigned to this service.')}</p>
                            )}
                        </div>
                    </div>
                    <div className="card p-6">
                        <h2 className="section-title">{t('Router health')}</h2>
                        <div className="mt-4 space-y-3">
                            {routerHealth.map((metric, index) => (
                                <div
                                    key={`${metric.observed_at}-${index}`}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span className="capitalize text-muted">{enumLabel(metric.status, t)}</span>
                                    <span className="font-semibold">
                                        {metric.latency_ms === null ? '—' : `${metric.latency_ms} ms`}
                                    </span>
                                </div>
                            ))}
                            {routerHealth.length === 0 && (
                                <p className="text-sm text-muted">{t('No router observations available.')}</p>
                            )}
                        </div>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
