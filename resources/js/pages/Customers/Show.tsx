import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    CreditCard,
    Download,
    Edit3,
    FileText,
    MapPin,
    MessageCircle,
    MessageSquare,
    Phone,
    Plus,
    Pause,
    Play,
    RefreshCw,
    ReceiptText,
    ShieldOff,
    Upload,
    Wifi,
    WifiOff,
} from 'lucide-react';

import { StatusBadge } from '@/components/StatusBadge';
import PublicLinkCreator, { type PublicLinkSummary } from '@/components/PublicLinkCreator';
import MapView from '@/components/MapView';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatBytes, formatDate, formatDuration, formatExpiryCountdown, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { Customer, PageProps } from '@/types';

type Props = PageProps & {
    customer: Customer;
    paymentGrid: {
        year: number;
        years: number[];
        months: {
            month: number;
            status: 'paid' | 'partial' | 'due' | 'no_invoice';
            invoice_count: number;
            payment_count: number;
            totals: {
                currency: string;
                billed_amount: number;
                paid_amount: number;
                outstanding_amount: number;
            }[];
        }[];
    };
    canAnonymize?: boolean;
    canCreateService?: boolean;
    canEdit?: boolean;
    canCollectPayment?: boolean;
    canShareBilling?: boolean;
    publicLinks: PublicLinkSummary[];
    canCreateTicket?: boolean;
    canResyncServices?: boolean;
    canActivateServices?: boolean;
    canSuspendServices?: boolean;
    canPauseServices?: boolean;
    canTerminateServices?: boolean;
    canDisconnectSessions?: boolean;
    canForceResumeServices?: boolean;
    canManageEquipment?: boolean;
};

export default function CustomerShow({
    customer,
    paymentGrid,
    canAnonymize = false,
    canCreateService = false,
    canEdit = false,
    canCollectPayment = false,
    canShareBilling = false,
    publicLinks,
    canCreateTicket = false,
    canResyncServices = false,
    canActivateServices = false,
    canSuspendServices = false,
    canPauseServices = false,
    canTerminateServices = false,
    canDisconnectSessions = false,
    canForceResumeServices = false,
    canManageEquipment = false,
}: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
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
    const fullName = `${customer.first_name} ${customer.last_name ?? ''}`.trim();
    const nextExpiry =
        customer.services
            .map((service) => service.expires_at)
            .filter((expiresAt): expiresAt is string => expiresAt !== null)
            .sort((left, right) => new Date(left).getTime() - new Date(right).getTime())[0] ?? null;
    const documentForm = useForm<{ file: File | null; document_type: string; retention_until: string }>({
        file: null,
        document_type: 'contract',
        retention_until: '',
    });
    const submitDocument = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        documentForm.post('/customers/' + customer.public_id + '/documents', {
            forceFormData: true,
            onSuccess: () => documentForm.reset(),
        });
    };
    const paymentMonthLabel = (month: number) =>
        new Intl.DateTimeFormat(app.locale, { month: 'short' }).format(new Date(paymentGrid.year, month - 1, 1));
    const changePaymentYear = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(window.location.pathname, { year: event.target.value }, { preserveScroll: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={fullName} />
            <Link
                href="/customers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} />
                {t('Back to customers')}
            </Link>
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div className="flex items-center gap-4">
                    <div className="grid size-14 place-items-center rounded-2xl bg-brand text-lg font-bold text-white">
                        {customer.first_name.slice(0, 1)}
                        {customer.last_name?.slice(0, 1) ?? ''}
                    </div>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="page-title">{fullName}</h1>
                            <StatusBadge status={customer.status} />
                        </div>
                        <p className="mt-1 text-sm text-muted">
                            {customer.code} · {customer.zone?.name ?? t('Zone unassigned')}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={`tel:${customer.phone}`} className="button-secondary">
                        <Phone size={16} />
                        {t('Call')}
                    </a>
                    <a href={`https://wa.me/${customer.phone.replace(/\D/g, '')}`} className="button-secondary">
                        <MessageCircle size={16} />
                        {t('WhatsApp')}
                    </a>
                    {canCollectPayment && (
                        <Link href={`/customers/${customer.public_id}/payments/create`} className="button-primary">
                            <CreditCard size={16} />
                            {t('Take payment')}
                        </Link>
                    )}
                    {canCollectPayment && customer.services.some((service) => service.status !== 'terminated') && (
                        <Link href={`/customers/${customer.public_id}/renew`} className="button-secondary">
                            <RefreshCw size={16} />
                            {t('Renew')}
                        </Link>
                    )}
                    {canCreateTicket && (
                        <Link href={`/customers/${customer.public_id}/tickets/create`} className="button-secondary">
                            <MessageSquare size={16} />
                            {t('Open ticket')}
                        </Link>
                    )}
                    {canAnonymize && !customer.anonymized_at && (
                        <ConfirmDialog
                            title={t('Anonymize this customer record?')}
                            description={t('Personal data cannot be recovered after anonymization.')}
                            confirmLabel={t('Anonymize customer')}
                            destructive
                            onConfirm={() => router.post(`/customers/${customer.public_id}/anonymize`)}
                        >
                            <button type="button" className="button-secondary text-coral">
                                <ShieldOff size={16} />
                                {t('Anonymize')}
                            </button>
                        </ConfirmDialog>
                    )}
                </div>
            </div>
            {canShareBilling && (
                <div className="mt-6">
                    <PublicLinkCreator
                        endpoint={`/customers/${customer.public_id}/statement-links`}
                        types={[{ value: 'statement', label: t('Account statement') }]}
                        title={t('Share account statement')}
                        existingLinks={publicLinks}
                    />
                </div>
            )}
            <div className="mt-8 grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Balance')}</p>
                            <p
                                className={`mt-3 font-display text-2xl font-semibold ${customer.balance_amount > 0 ? 'text-coral' : ''}`}
                            >
                                {formatMoney(customer.balance_amount, customer.balance_currency)}
                            </p>
                            <p className="mt-1 text-xs text-muted">
                                {customer.balance_amount > 0 ? t('Amount owing') : t('Account balance')}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Services')}</p>
                            <p className="mt-3 font-display text-2xl font-semibold">{customer.services.length}</p>
                            <p className="mt-1 text-xs text-muted">{t('Across this customer')}</p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Contact')}</p>
                            <p className="mt-3 truncate text-sm font-semibold">{customer.phone}</p>
                            <p className="mt-1 truncate text-xs text-muted">
                                {customer.email ?? t('No email on file')}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Expiry')}</p>
                            <p
                                className={`mt-3 text-sm font-semibold ${nextExpiry !== null && new Date(nextExpiry) < new Date() ? 'text-coral' : ''}`}
                            >
                                {formatExpiryCountdown(nextExpiry, t)}
                            </p>
                            <p className="mt-1 text-xs text-muted">{t('Earliest service expiry')}</p>
                        </div>
                    </div>
                    <div className="card overflow-hidden" data-testid="customer-payment-grid">
                        <div className="flex flex-col gap-4 border-b border-line px-6 py-5 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <ReceiptText size={18} className="text-brand" />
                                    <h2 className="section-title">{t('Monthly payment grid')}</h2>
                                </div>
                                <p className="mt-1 text-sm text-muted">
                                    {t('See which billing months are paid, partial, or still due.')}
                                </p>
                            </div>
                            <label className="block w-full sm:w-32">
                                <span className="sr-only">{t('Payment year')}</span>
                                <ResponsiveSelect
                                    aria-label={t('Payment year')}
                                    className="field"
                                    value={paymentGrid.year}
                                    onChange={changePaymentYear}
                                >
                                    {paymentGrid.years.map((year) => (
                                        <option key={year} value={year}>
                                            {year}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                        </div>
                        <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            {paymentGrid.months.map((month) => {
                                const statusLabel = {
                                    paid: t('Paid'),
                                    partial: t('Partial'),
                                    due: t('Due'),
                                    no_invoice: t('No invoice'),
                                }[month.status];
                                const statusClass = {
                                    paid: 'border-emerald-200 bg-emerald-50 text-emerald-800',
                                    partial: 'border-amber-200 bg-amber-50 text-amber-800',
                                    due: 'border-rose-200 bg-rose-50 text-rose-800',
                                    no_invoice: 'border-line bg-sand/30 text-muted',
                                }[month.status];

                                return (
                                    <div
                                        key={month.month}
                                        data-testid={`payment-month-${month.month}`}
                                        className={`rounded-xl border p-4 ${statusClass}`}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="font-semibold">{paymentMonthLabel(month.month)}</p>
                                            {month.status === 'paid' ? (
                                                <CheckCircle2 size={16} aria-label={t('Paid')} />
                                            ) : month.status === 'no_invoice' ? (
                                                <ReceiptText size={16} aria-label={t('No invoice')} />
                                            ) : (
                                                <CircleAlert size={16} aria-label={statusLabel} />
                                            )}
                                        </div>
                                        <p className="mt-1 text-xs font-semibold uppercase tracking-wide opacity-80">
                                            {statusLabel}
                                        </p>
                                        {month.totals.length > 0 ? (
                                            <div className="mt-3 space-y-2 text-xs">
                                                {month.totals.map((total) => (
                                                    <div key={total.currency}>
                                                        <p className="font-semibold">
                                                            {formatMoney(total.paid_amount, total.currency)} {t('paid')}
                                                        </p>
                                                        {total.outstanding_amount > 0 && (
                                                            <p className="mt-0.5 opacity-80">
                                                                {formatMoney(total.outstanding_amount, total.currency)}{' '}
                                                                {t('due')}
                                                            </p>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="mt-3 text-xs opacity-80">{t('Nothing billed')}</p>
                                        )}
                                        {month.invoice_count > 0 && (
                                            <p className="mt-3 text-[11px] opacity-70">
                                                {month.invoice_count}{' '}
                                                {month.invoice_count === 1 ? t('invoice') : t('invoices')} ·{' '}
                                                {month.payment_count}{' '}
                                                {month.payment_count === 1 ? t('payment') : t('payments')}
                                            </p>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div>
                                <h2 className="section-title">{t('Services')}</h2>
                                <p className="mt-1 text-sm text-muted">
                                    {t('Every connection belonging to this customer.')}
                                </p>
                            </div>
                            {canCreateService && (
                                <Link
                                    href={`/customers/${customer.public_id}/services/create`}
                                    className="button-secondary"
                                >
                                    <Plus size={16} />
                                    {t('Add service')}
                                </Link>
                            )}
                        </div>
                        <div className="divide-y divide-line">
                            {customer.services.map((service) => (
                                <div key={service.public_id} className="p-6">
                                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                        <div className="flex items-start gap-3">
                                            <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                                <Wifi size={18} />
                                            </div>
                                            <div>
                                                <p className="font-semibold">
                                                    {service.plan?.name ?? t('Plan unavailable')}
                                                </p>
                                                <p className="mt-1 text-sm text-muted">
                                                    {service.username} ·{' '}
                                                    {service.plan
                                                        ? `${service.plan.download_kbps / 1000} Mbps down / ${service.plan.upload_kbps / 1000} Mbps up`
                                                        : t('No plan assigned')}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={service.status} />
                                            <StatusBadge status={service.network_state} />
                                        </div>
                                    </div>
                                    <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <p className="text-xs text-muted">{t('Expires')}</p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                                <CalendarDays size={14} className="text-muted" />
                                                {formatDate(service.expires_at)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">{t('Provisioning')}</p>
                                            <p className="mt-1 text-sm font-semibold capitalize">
                                                {t(`customer.provisioning.${service.provisioning_mode ?? 'manual'}.label`)}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {service.router?.name ?? t('No router assigned')}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">{t('Session')}</p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                                <span
                                                    className={`size-2 rounded-full ${service.session ? 'bg-emerald-500' : 'bg-slate-300'}`}
                                                />
                                                {service.session ? t('Online') : t('Offline')}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {service.session?.framed_ip ??
                                                    service.session?.nasname ??
                                                    t('No active session')}
                                            </p>
                                            {service.session && (
                                                <p className="mt-1 text-xs text-muted">
                                                    {t('Uptime')}{' '}
                                                    {formatDuration(
                                                        service.session.started_at,
                                                        service.session.last_seen_at,
                                                        t,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">{t('Quota')}</p>
                                            {service.usage.quota_bytes > 0 ? (
                                                <>
                                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-sand">
                                                        <div
                                                            className="h-full rounded-full bg-brand"
                                                            style={{
                                                                width: `${Math.min(100, (service.usage.used_bytes / service.usage.quota_bytes) * 100)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {formatBytes(service.usage.used_bytes)} {t('of')}{' '}
                                                        {formatBytes(service.usage.quota_bytes)}
                                                    </p>
                                                </>
                                            ) : (
                                                <p className="mt-1 text-sm text-muted">{t('No quota set')}</p>
                                            )}
                                        </div>
                                        <div className="lg:col-span-4">
                                            <p className="text-xs text-muted">{t('Equipment')}</p>
                                            {service.equipment.length > 0 ? (
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {service.equipment.map((unit) => (
                                                        <div
                                                            key={unit.serial_number}
                                                            className="inline-flex items-center gap-2 rounded-full bg-sand px-3 py-1.5 text-xs font-semibold"
                                                        >
                                                            {unit.item?.name ?? t('Serialized equipment')} ·{' '}
                                                            {unit.serial_number}
                                                            <span className="font-normal text-muted">
                                                                · {t('Assigned')} {formatDate(unit.assigned_at)}
                                                            </span>
                                                            {canManageEquipment && (
                                                                <ConfirmDialog
                                                                    title={t('Mark this equipment as returned?') + ' ' + unit.serial_number}
                                                                    description={t('The equipment will be removed from this service and made available for recovery.')}
                                                                    confirmLabel={t('Mark returned')}
                                                                    destructive
                                                                    onConfirm={() =>
                                                                        router.post(
                                                                            `/services/${service.public_id}/equipment/${unit.id}/return`,
                                                                        )
                                                                    }
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        className="font-semibold text-coral hover:underline"
                                                                    >
                                                                        {t('Return')}
                                                                    </button>
                                                                </ConfirmDialog>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-1 text-sm text-muted">{t('No equipment assigned.')}</p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3 sm:justify-end">
                                            {service.status === 'pending' && canActivateServices && (
                                                <ConfirmDialog
                                                    title={t('Activate this service?')}
                                                    description={t('The service will be activated and its network provisioning will resume.')}
                                                    confirmLabel={t('Activate service')}
                                                    onConfirm={() =>
                                                        router.post(`/services/${service.public_id}/activate`)
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    >
                                                        <Play size={14} /> {t('Activate')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {service.status === 'active' && canSuspendServices && (
                                                <ConfirmDialog
                                                    title={t('Suspend this service?')}
                                                    description={t('The service will be suspended and its network access will be restricted.')}
                                                    confirmLabel={t('Suspend service')}
                                                    destructive
                                                    onConfirm={() =>
                                                        router.post(`/services/${service.public_id}/suspend`, {
                                                            reason: 'manual_operator',
                                                        })
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                    >
                                                        <Pause size={14} /> {t('Suspend')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {service.status === 'active' && canPauseServices && (
                                                <ConfirmDialog
                                                    title={t('Pause this service?')}
                                                    description={t('The service will pause without closing the account or removing its plan.')}
                                                    confirmLabel={t('Pause service')}
                                                    onConfirm={() =>
                                                        router.post(`/services/${service.public_id}/pause`, {
                                                            reason: 'customer_requested',
                                                        })
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700"
                                                    >
                                                        <Pause size={14} /> {t('Pause')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {((service.status === 'suspended' &&
                                                canActivateServices &&
                                                (service.suspension_reason === 'auto_overdue' ||
                                                    canForceResumeServices)) ||
                                                (service.status === 'paused' && canActivateServices)) && (
                                                <ConfirmDialog
                                                    title={t('Reactivate this service?')}
                                                    description={t('The service will be active again and network provisioning will resume.')}
                                                    confirmLabel={t('Reactivate service')}
                                                    onConfirm={() =>
                                                        router.post(`/services/${service.public_id}/resume`)
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    >
                                                        <Play size={14} /> {t('Resume')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {canTerminateServices && service.status !== 'terminated' && (
                                                <ConfirmDialog
                                                    title={t('Terminate this service?')}
                                                    description={t('Equipment will be marked for recovery and this service cannot be reactivated.')}
                                                    confirmLabel={t('Terminate service')}
                                                    destructive
                                                    onConfirm={() =>
                                                        router.post(`/services/${service.public_id}/terminate`, {
                                                            reason: 'manual_operator',
                                                        })
                                                    }
                                                >
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                    >
                                                        <ShieldOff size={14} /> {t('Terminate')}
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {canResyncServices && service.status !== 'terminated' && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    onClick={() => router.post(`/services/${service.public_id}/resync`)}
                                                >
                                                    <RefreshCw size={14} /> {t('Re-sync')}
                                                </button>
                                            )}
                                            {canDisconnectSessions && service.session && (
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
                                </div>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="p-12 text-center">
                                    <Wifi className="mx-auto text-muted" size={28} />
                                    <p className="mt-3 font-semibold">{t('No services yet')}</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                <aside className="space-y-6">
                    <div className="card p-6">
                        <div className="flex items-center justify-between">
                            <h2 className="section-title">{t('Customer details')}</h2>
                            {canEdit && (
                                <Link
                                    href={`/customers/${customer.public_id}/edit`}
                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                >
                                    <Edit3 size={14} /> {t('Edit')}
                                </Link>
                            )}
                        </div>
                        <dl className="mt-5 space-y-4">
                            <div>
                                <dt className="text-xs text-muted">{t('Phone')}</dt>
                                <dd className="mt-1 text-sm font-medium">{customer.phone}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">{t('Email')}</dt>
                                <dd className="mt-1 text-sm font-medium">{customer.email ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">{t('Address')}</dt>
                                <dd className="mt-1 flex items-start gap-1.5 text-sm font-medium">
                                    <MapPin size={15} className="mt-0.5 shrink-0 text-muted" />
                                    {customer.address ?? t('No address on file')}
                                </dd>
                            </div>
                            {customer.latitude !== null && customer.longitude !== null && (
                                <div>
                                    <dt className="text-xs text-muted">{t('Coordinates')}</dt>
                                    <dd className="mt-1 flex items-center justify-between gap-3 text-sm font-medium">
                                        <span>
                                            {customer.latitude.toFixed(7)}, {customer.longitude.toFixed(7)}
                                        </span>
                                        <a
                                            href={
                                                'https://www.openstreetmap.org/?mlat=' +
                                                customer.latitude +
                                                '&mlon=' +
                                                customer.longitude
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-brand hover:underline"
                                        >
                                            {t('Open map')}
                                        </a>
                                    </dd>
                                    <div className="mt-3">
                                        <MapView latitude={customer.latitude} longitude={customer.longitude} />
                                    </div>
                                </div>
                            )}
                        </dl>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center gap-2 border-b border-line px-6 py-5">
                            <FileText size={18} className="text-brand" />
                            <div>
                                <h2 className="section-title">{t('Documents')}</h2>
                                <p className="mt-1 text-sm text-muted">
                                    {t('Private files attached to this customer.')}
                                </p>
                            </div>
                        </div>
                        {canEdit && (
                            <form onSubmit={submitDocument} className="space-y-3 border-b border-line px-6 py-5">
                                <label>
                                    <span className="field-label">{t('Add PDF or image')}</span>
                                    <input
                                        id="customer-document-file"
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png,image/webp"
                                        className="field"
                                        {...fieldA11y('customer-document-file', documentForm.errors.file)}
                                        onChange={(event) =>
                                            documentForm.setData('file', event.target.files?.[0] ?? null)
                                        }
                                    />
                                    {fieldError('customer-document-file', documentForm.errors.file)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Document type')}</span>
                                    <ResponsiveSelect
                                        id="customer-document-type"
                                        className="field"
                                        {...fieldA11y('customer-document-type', documentForm.errors.document_type)}
                                        value={documentForm.data.document_type}
                                        onChange={(event) => documentForm.setData('document_type', event.target.value)}
                                    >
                                        <option value="contract">{t('Contract')}</option>
                                        <option value="identity">{t('Identity')}</option>
                                        <option value="proof_of_address">{t('Proof of address')}</option>
                                        <option value="other">{t('Other')}</option>
                                    </ResponsiveSelect>
                                    {fieldError('customer-document-type', documentForm.errors.document_type)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Retain until (optional)')}</span>
                                    <input
                                        id="customer-document-retention-until"
                                        type="date"
                                        className="field"
                                        {...fieldA11y(
                                            'customer-document-retention-until',
                                            documentForm.errors.retention_until,
                                        )}
                                        value={documentForm.data.retention_until}
                                        onChange={(event) =>
                                            documentForm.setData('retention_until', event.target.value)
                                        }
                                    />
                                    {fieldError(
                                        'customer-document-retention-until',
                                        documentForm.errors.retention_until,
                                    )}
                                </label>
                                <button
                                    type="submit"
                                    className="button-secondary"
                                    disabled={documentForm.processing || !documentForm.data.file}
                                >
                                    <Upload size={15} /> {t('Upload document')}
                                </button>
                            </form>
                        )}
                        <div className="divide-y divide-line">
                            {customer.documents.map((document) => (
                                <div key={document.id} className="flex items-center justify-between gap-4 px-6 py-4">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">{document.filename}</p>
                                        <p className="mt-1 text-xs capitalize text-muted">
                                            {document.document_type
                                                ? t(document.document_type.replace('_', ' '))
                                                : t('Other')}{' '}
                                            {document.mime_type} · {document.size_bytes} bytes ·{' '}
                                            {formatDate(document.created_at)}
                                        </p>
                                        {document.retention_until && (
                                            <p className="mt-1 text-xs text-muted">
                                                {t('Retained until')} {formatDate(document.retention_until)}
                                            </p>
                                        )}
                                    </div>
                                    <a href={document.download_url} className="button-secondary shrink-0" download>
                                        <Download size={15} /> {t('Download')}
                                    </a>
                                </div>
                            ))}
                            {customer.documents.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">
                                    {t('No customer documents have been uploaded.')}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <MessageSquare size={18} className="text-brand" />
                                <div>
                                    <h2 className="section-title">{t('Support tickets')}</h2>
                                    <p className="mt-1 text-sm text-muted">{t('Recent customer conversations.')}</p>
                                </div>
                            </div>
                            <Link
                                href={`/operations/tickets?search=${encodeURIComponent(customer.code)}`}
                                className="text-sm font-semibold text-brand"
                            >
                                {t('View queue')}
                            </Link>
                        </div>
                        <div className="divide-y divide-line">
                            {customer.tickets.map((ticket) => (
                                <Link
                                    key={ticket.public_id}
                                    href={`/operations/tickets/${ticket.public_id}`}
                                    className="block px-6 py-4 hover:bg-sand/30"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold">{ticket.subject}</p>
                                            <p className="mt-1 text-xs text-muted">
                                            {ticket.number} · {enumLabel(ticket.priority, t)} {t('priority')}
                                            </p>
                                        </div>
                                        <StatusBadge status={ticket.status} />
                                    </div>
                                    <p className="mt-2 text-xs text-muted">
                                        {t('Updated')} {formatDate(ticket.updated_at)}
                                    </p>
                                </Link>
                            ))}
                            {customer.tickets.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">
                                    {t('No support tickets for this customer.')}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="card p-6">
                        <div className="flex items-center gap-2">
                            <CalendarDays size={18} className="text-brand" />
                            <h2 className="section-title">{t('Timeline')}</h2>
                        </div>
                        <div className="mt-5 border-s border-line ps-4">
                            {customer.timeline.map((item, index) => (
                                <div
                                    key={`${item.type}-${item.created_at}-${index}`}
                                    className="relative pb-6 last:pb-0"
                                >
                                    <span
                                        className={`absolute -start-[21px] top-1 size-2 rounded-full ring-4 ${index === 0 ? 'bg-brand ring-brand-soft' : 'bg-line ring-canvas'}`}
                                    />
                                    <p className="text-sm font-semibold">{t(item.title)}</p>
                                    <p className="mt-1 text-xs text-muted">
                                        {t(item.detail)}
                                        {item.amount !== undefined && item.currency
                                            ? ` · ${formatMoney(item.amount, item.currency)}`
                                            : ''}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted">{formatDate(item.created_at)}</p>
                                </div>
                            ))}
                            {customer.timeline.length === 0 && (
                                <p className="text-sm text-muted">{t('No activity recorded yet.')}</p>
                            )}
                        </div>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
