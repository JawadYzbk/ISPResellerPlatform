import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link } from '@inertiajs/react';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { AlertTriangle, Check, CreditCard, LogOut, RefreshCw, Send, UserRound, Wifi } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import { createIdempotencyKey } from '@/lib/idempotency';
import type { Customer, PortalBalance, PortalBilling, PortalNotice, PortalTicket, PublicTenant } from '@/types';

type Props = { tenant: PublicTenant };
type StripeIntent = { clientSecret: string; publishableKey: string; invoiceId: string };

export default function PortalDashboard({ tenant }: Props) {
    const t = useMemo(() => createTranslator(tenant.locale), [tenant.locale]);

    useEffect(() => {
        document.documentElement.lang = tenant.locale;
        document.documentElement.dir = tenant.locale === 'ar' ? 'rtl' : 'ltr';
    }, [tenant.locale]);
   const [customer, setCustomer] = useState<Customer | null>(null);
    const [balance, setBalance] = useState<PortalBalance | null>(null);
    const [billing, setBilling] = useState<PortalBilling | null>(null);
    const [notices, setNotices] = useState<PortalNotice[]>([]);
    const [tickets, setTickets] = useState<PortalTicket[]>([]);
    const [ticketForm, setTicketForm] = useState({ category: 'other', subject: '', description: '' });
    const [profileForm, setProfileForm] = useState({ email: '', address: '' });
    const [ticketBusy, setTicketBusy] = useState(false);
    const [ratingBusy, setRatingBusy] = useState<string | null>(null);
    const [supportMessage, setSupportMessage] = useState<string | null>(null);
    const [profileBusy, setProfileBusy] = useState(false);
    const [profileSaved, setProfileSaved] = useState(false);
    const [restartBusy, setRestartBusy] = useState<string | null>(null);
    const [selectedInvoiceId, setSelectedInvoiceId] = useState('');
    const [paymentIntent, setPaymentIntent] = useState<StripeIntent | null>(null);
    const [paymentBusy, setPaymentBusy] = useState(false);
    const [paymentMessage, setPaymentMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const tokenKey = `portal_token:${tenant.slug}`;

    useEffect(() => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) {
            window.location.assign(`/portal/${tenant.slug}`);
            return;
        }
        Promise.all([
            fetch(`/api/v1/portal/${tenant.slug}/me`, { headers: { Authorization: `Bearer ${token}` } }),
            fetch(`/api/v1/portal/${tenant.slug}/me/balance`, { headers: { Authorization: `Bearer ${token}` } }),
            fetch(`/api/v1/portal/${tenant.slug}/billing`, { headers: { Authorization: `Bearer ${token}` } }),
            fetch(`/api/v1/portal/${tenant.slug}/me/notices`, { headers: { Authorization: `Bearer ${token}` } }),
            fetch(`/api/v1/portal/${tenant.slug}/me/tickets`, { headers: { Authorization: `Bearer ${token}` } }),
        ])
            .then(async ([customerResponse, balanceResponse, billingResponse, noticesResponse, ticketsResponse]) => {
                if (
                    !customerResponse.ok ||
                    !balanceResponse.ok ||
                    !billingResponse.ok ||
                    !noticesResponse.ok ||
                    !ticketsResponse.ok
                ) {
                    window.location.assign(`/portal/${tenant.slug}`);
                    return;
                }
                const customerPayload = await customerResponse.json();
                setCustomer(customerPayload);
                setProfileForm({ email: customerPayload.email ?? '', address: customerPayload.address ?? '' });
                setBalance(await balanceResponse.json());
                setBilling(await billingResponse.json());
                setNotices((await noticesResponse.json()).data ?? []);
                setTickets((await ticketsResponse.json()).data ?? []);
            })
            .catch(() => setError(t('portal.dashboard.load_error')));
    }, [t, tenant.slug, tokenKey]);

    const signOut = async () => {
        const token = sessionStorage.getItem(tokenKey);
        if (token) {
            await fetch(`/api/v1/portal/${tenant.slug}/logout`, {
                method: 'POST',
                headers: { Authorization: `Bearer ${token}` },
            }).catch(() => undefined);
        }
        sessionStorage.removeItem(tokenKey);
        window.location.assign(`/portal/${tenant.slug}`);
    };

    const saveProfile = async (event: React.FormEvent) => {
        event.preventDefault();
        const token = sessionStorage.getItem(tokenKey);
        if (!token) return;
        setProfileBusy(true);
        setProfileSaved(false);
        setError(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/me/profile`, {
            method: 'PATCH',
            headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(profileForm),
        });
        if (response.ok) {
            const payload = await response.json();
            setCustomer((current) => (current ? { ...current, ...payload.data } : current));
            setProfileSaved(true);
        } else {
            const payload = await response.json();
            setError(payload.detail ?? payload.message ?? t('portal.dashboard.profile_error'));
        }
        setProfileBusy(false);
    };

    const restartService = async (serviceId: string) => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token) return;
        setRestartBusy(serviceId);
        setError(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/me/services/${serviceId}/restart-session`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
                'X-Idempotency-Key': createIdempotencyKey('portal-restart'),
            },
        });
        if (!response.ok) {
            const payload = await response.json();
            setError(payload.detail ?? payload.message ?? t('portal.dashboard.restart_error'));
        }
        setRestartBusy(null);
    };

    const startOnlinePayment = async () => {
        const token = sessionStorage.getItem(tokenKey);
        const payableInvoices =
            billing?.invoices.filter((invoice) => invoice.status === 'issued' && invoice.outstanding_amount > 0) ?? [];
        const invoice = payableInvoices.find((item) => item.id === selectedInvoiceId) ?? payableInvoices[0];
        if (!token || !invoice) return;
        setPaymentBusy(true);
        setPaymentMessage(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/payments/intent`, {
            method: 'POST',
            headers: {
                Authorization: `Bearer ${token}`,
                'Content-Type': 'application/json',
                'X-Idempotency-Key': createIdempotencyKey('portal-payment'),
            },
            body: JSON.stringify({ invoice_id: invoice.id, amount: invoice.outstanding_amount }),
        });
        const payload = await response.json();
        if (!response.ok) {
            setPaymentMessage(payload.detail ?? payload.message ?? t('portal.dashboard.payment_start_error'));
            setPaymentBusy(false);
            return;
        }
        const clientSecret = payload.payload?.client_secret;
        const publishableKey = payload.payload?.publishable_key;
        if (typeof clientSecret !== 'string' || typeof publishableKey !== 'string') {
            setPaymentMessage(t('portal.dashboard.incomplete_checkout'));
            setPaymentBusy(false);
            return;
        }
        setPaymentIntent({ clientSecret, publishableKey, invoiceId: invoice.id });
        setPaymentBusy(false);
    };

    const paymentSubmitted = async () => {
        setPaymentMessage(t('portal.dashboard.payment_submitted'));
        setPaymentIntent(null);
        const token = sessionStorage.getItem(tokenKey);
        if (!token) return;
        const response = await fetch(`/api/v1/portal/${tenant.slug}/billing`, {
            headers: { Authorization: `Bearer ${token}` },
        });
        if (response.ok) setBilling(await response.json());
    };

    const submitTicket = async (event: React.FormEvent) => {
        event.preventDefault();
        const token = sessionStorage.getItem(tokenKey);
        if (!token || !ticketForm.subject.trim() || !ticketForm.description.trim()) return;
        setTicketBusy(true);
        setError(null);
        setSupportMessage(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/me/tickets`, {
            method: 'POST',
            headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify(ticketForm),
        });
        if (response.ok) {
            const refreshed = await fetch(`/api/v1/portal/${tenant.slug}/me/tickets`, {
                headers: { Authorization: `Bearer ${token}` },
            });
            setTickets((await refreshed.json()).data ?? []);
            setTicketForm({ category: 'other', subject: '', description: '' });
        } else {
            const payload = await response.json();
            setError(payload.detail ?? t('portal.dashboard.ticket_error'));
        }
        setTicketBusy(false);
    };

    const rateTicket = async (ticketId: string, rating: number) => {
        const token = sessionStorage.getItem(tokenKey);
        if (!token || rating < 1 || rating > 5) return;
        setRatingBusy(ticketId);
        setError(null);
        setSupportMessage(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/me/tickets/${ticketId}/rating`, {
            method: 'POST',
            headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ rating }),
        });
        const payload = await response.json();
        if (response.ok) {
            setTickets((current) =>
                current.map((ticket) =>
                    ticket.uuid === ticketId
                        ? { ...ticket, satisfaction_rating: payload.data.satisfaction_rating }
                        : ticket,
                ),
            );
            setSupportMessage(t('portal.dashboard.rating_thanks'));
        } else {
            setError(payload.detail ?? payload.message ?? t('portal.dashboard.rating_error'));
        }
        setRatingBusy(null);
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-8 text-ink">
            <Head title={t('portal.dashboard.title')} />
            <main className="mx-auto max-w-3xl">
                <header className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center overflow-hidden rounded-xl bg-brand text-white">
                            {tenant.logo_url ? (
                                <img src={tenant.logo_url} alt="" className="size-full object-cover" />
                            ) : (
                                <Wifi size={19} />
                            )}
                        </div>
                        <div>
                            <p className="font-display font-bold">{tenant.name}</p>
                        <p className="text-sm text-muted">{t('portal.dashboard.customer_portal')}</p>
                        </div>
                    </div>
                    <button onClick={signOut} className="button-secondary">
                        <LogOut size={16} />
                        {t('portal.dashboard.sign_out')}
                    </button>
                </header>
                {error && <p className="mt-8 field-error">{error}</p>}
                {customer && (
                    <>
                        <div className="mt-12">
                            <p className="eyebrow">{t('portal.dashboard.welcome_back')}</p>
                            <h1 className="page-title">
                                {customer.first_name} {customer.last_name ?? ''}
                            </h1>
                            <p className="page-subtitle">{t('portal.dashboard.subtitle')}</p>
                        </div>
                        <section className="mt-8 grid gap-4 sm:grid-cols-2" aria-label={t('portal.dashboard.account_summary')}>
                            <div className="card p-6">
                                <p className="eyebrow">{t('portal.dashboard.current_balance')}</p>
                                <p
                                    className={`mt-3 text-3xl font-semibold ${customer.balance_amount > 0 ? 'text-rose-700' : 'text-ink'}`}
                                >
                                    {formatMoney(customer.balance_amount, customer.balance_currency)}
                                </p>
                                <p className="mt-2 text-sm text-muted">
                                    {balance?.next_due
                                        ? `${t('portal.dashboard.next_due')} ${formatDate(balance.next_due.due_at)}`
                                        : t('portal.dashboard.no_outstanding_balance')}
                                </p>
                            </div>
                            <div className="card p-6">
                                <p className="eyebrow">{t('portal.dashboard.account')}</p>
                                <p className="mt-3 text-3xl font-semibold">{customer.services.length}</p>
                                <p className="mt-2 text-sm text-muted">
                                    {customer.services.length === 1
                                        ? t('portal.dashboard.active_connection')
                                        : t('portal.dashboard.connections_linked')}
                                </p>
                            </div>
                        </section>
                        {notices.length > 0 && (
                            <section className="mt-8 space-y-3" aria-labelledby="notices-heading">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle size={17} className="text-amber-600" />
                                    <h2 id="notices-heading" className="section-title">
                                        {t('portal.dashboard.service_notices')}
                                    </h2>
                                </div>
                                {notices.map((notice) => (
                                    <article
                                        key={notice.uuid}
                                        className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-amber-950"
                                    >
                                        <p className="text-xs font-bold uppercase tracking-[0.16em]">
                                            {notice.severity}
                                        </p>
                                        <h3 className="mt-1 font-semibold">{notice.title}</h3>
                                        {notice.description && (
                                            <p className="mt-1 text-sm text-amber-900/80">{notice.description}</p>
                                        )}
                                    </article>
                                ))}
                            </section>
                        )}
                        <section className="mt-8 space-y-4">
                            {customer.services.map((service) => (
                                <article key={service.public_id} className="card p-6">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex items-start gap-3">
                                            <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                                <Wifi size={18} />
                                            </div>
                                            <div>
                                                <h2 className="font-semibold">{service.plan.name}</h2>
                                                <p className="mt-1 text-sm text-muted">
                                                    {service.plan.download_kbps / 1000} Mbps down ·{' '}
                                                    {service.plan.upload_kbps / 1000} Mbps up
                                                </p>
                                            </div>
                                        </div>
                                        <StatusBadge status={service.status} />
                                    </div>
                                    <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-[1fr_auto] sm:items-center">
                                        <div>
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted">{t('portal.dashboard.usage_this_period')}</span>
                                                <span className="font-semibold">
                                                    {Math.round((service.usage.used_bytes / 1_000_000_000) * 10) / 10} /{' '}
                                                    {service.usage.quota_bytes > 0
                                                        ? Math.round((service.usage.quota_bytes / 1_000_000_000) * 10) /
                                                          10
                                                        : '∞'}{' '}
                                                    GB
                                                </span>
                                            </div>
                                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-sand">
                                                <div
                                                    className="h-full rounded-full bg-brand"
                                                    style={{
                                                        width: `${Math.min(100, service.usage.quota_bytes > 0 ? (service.usage.used_bytes / service.usage.quota_bytes) * 100 : 0)}%`,
                                                    }}
                                                />
                                            </div>
                                            <p className="mt-2 text-sm text-muted">
                                                    {t('portal.dashboard.expires')} {formatDate(service.expires_at)}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3 sm:justify-end">
                                            <span className="inline-flex items-center gap-1.5 text-sm text-muted">
                                                <RefreshCw size={14} />
                                                {service.network_state.replace('_', ' ')}
                                            </span>
                                            {service.status === 'active' && (
                                                <button
                                                    type="button"
                                                    disabled={restartBusy === service.public_id}
                                                    onClick={() => restartService(service.public_id)}
                                                    className="button-secondary"
                                                >
                                                    <RefreshCw size={15} />
                                                    {restartBusy === service.public_id
                                                        ? t('portal.dashboard.restarting')
                                                        : t('portal.dashboard.restart_connection')}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="card p-10 text-center">
                                    <p className="font-semibold">{t('portal.dashboard.no_services')}</p>
                                    <p className="mt-1 text-sm text-muted">
                                        {t('portal.dashboard.contact_provider')}
                                    </p>
                                </div>
                            )}
                        </section>
                        {billing && (
                            <section className="mt-8 grid gap-6 md:grid-cols-2">
                                <div className="card p-6">
                                    <h2 className="section-title">{t('portal.dashboard.invoices')}</h2>
                                    <div className="mt-4 divide-y divide-line">
                                        {billing.invoices.map((invoice) => (
                                            <div
                                                key={invoice.id}
                                                className="flex items-center justify-between gap-4 py-3 text-sm"
                                            >
                                                <span>
                                                    <b>{invoice.number}</b>
                                                    <small className="mt-1 block text-muted">{invoice.status}</small>
                                                </span>
                                                <span className="font-semibold">
                                                    {formatMoney(invoice.total_amount, invoice.currency)}
                                                </span>
                                            </div>
                                        ))}
                                        {billing.invoices.length === 0 && (
                                            <p className="py-3 text-sm text-muted">{t('portal.dashboard.no_invoices')}</p>
                                        )}
                                    </div>
                                </div>
                                <div className="card p-6">
                                    <h2 className="section-title">{t('portal.dashboard.payment_history')}</h2>
                                    <div className="mt-4 divide-y divide-line">
                                        {billing.payments.map((payment) => (
                                            <div
                                                key={payment.id}
                                                className="flex items-center justify-between gap-4 py-3 text-sm"
                                            >
                                                <span>
                                                    <b>{payment.number}</b>
                                                    <small className="mt-1 block text-muted">{payment.status}</small>
                                                </span>
                                                <span className="font-semibold">
                                                    {formatMoney(payment.amount, payment.currency)}
                                                </span>
                                            </div>
                                        ))}
                                        {billing.payments.length === 0 && (
                                            <p className="py-3 text-sm text-muted">{t('portal.dashboard.no_payments')}</p>
                                        )}
                                    </div>
                                </div>
                            </section>
                        )}
                        {billing?.online_payments.enabled &&
                            billing.invoices.some(
                                (invoice) => invoice.status === 'issued' && invoice.outstanding_amount > 0,
                            ) && (
                                <section className="card mt-8 p-6" aria-labelledby="online-payment-heading">
                                    <div className="flex items-center gap-2">
                                        <CreditCard size={17} className="text-brand" />
                                        <h2 id="online-payment-heading" className="section-title">
                                            {t('portal.dashboard.pay_invoice')}
                                        </h2>
                                    </div>
                                    <p className="mt-2 text-sm text-muted">
                                        {t('portal.dashboard.payment_confirmation_note')}
                                    </p>
                                    {!paymentIntent ? (
                                        <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                                            <label className="block flex-1">
                                                <span className="field-label">{t('portal.dashboard.invoice')}</span>
                                                <ResponsiveSelect
                                                    className="field"
                                                    value={
                                                        selectedInvoiceId ||
                                                        billing.invoices.find(
                                                            (invoice) =>
                                                                invoice.status === 'issued' &&
                                                                invoice.outstanding_amount > 0,
                                                        )?.id ||
                                                        ''
                                                    }
                                                    onChange={(event) => {
                                                        setSelectedInvoiceId(event.target.value);
                                                        setPaymentMessage(null);
                                                    }}
                                                >
                                                    {billing.invoices
                                                        .filter(
                                                            (invoice) =>
                                                                invoice.status === 'issued' &&
                                                                invoice.outstanding_amount > 0,
                                                        )
                                                        .map((invoice) => (
                                                            <option key={invoice.id} value={invoice.id}>
                                                                {invoice.number} ·{' '}
                                                                {formatMoney(
                                                                    invoice.outstanding_amount,
                                                                    invoice.currency,
                                                                )}
                                                            </option>
                                                        ))}
                                                </ResponsiveSelect>
                                            </label>
                                            <button
                                                type="button"
                                                disabled={paymentBusy}
                                                onClick={startOnlinePayment}
                                                className="button-primary"
                                            >
                                                <CreditCard size={16} />
                                                {paymentBusy ? t('portal.dashboard.opening_checkout') : t('portal.dashboard.continue_payment')}
                                            </button>
                                        </div>
                                    ) : (
                                        <StripeCheckout
                                            clientSecret={paymentIntent.clientSecret}
                                           publishableKey={paymentIntent.publishableKey}
                                            t={t}
                                           onSubmitted={paymentSubmitted}
                                            onError={setPaymentMessage}
                                        />
                                    )}
                                    {paymentMessage && <p className="mt-4 text-sm text-muted">{paymentMessage}</p>}
                                </section>
                            )}
                        <form
                            onSubmit={saveProfile}
                            className="card mt-8 space-y-5 p-6"
                            aria-labelledby="profile-heading"
                        >
                            <div className="flex items-center gap-2">
                                <UserRound size={17} className="text-brand" />
                                <h2 id="profile-heading" className="section-title">
                                    {t('portal.dashboard.contact_details')}
                                </h2>
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="block">
                                    <span className="field-label">{t('Email')}</span>
                                    <input
                                        type="email"
                                        className="field"
                                        value={profileForm.email}
                                        onChange={(event) =>
                                            setProfileForm({ ...profileForm, email: event.target.value })
                                        }
                                    />
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('Address')}</span>
                                    <input
                                        className="field"
                                        value={profileForm.address}
                                        onChange={(event) =>
                                            setProfileForm({ ...profileForm, address: event.target.value })
                                        }
                                    />
                                </label>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <p className="text-sm text-muted">{t('Phone')}: {customer.phone}</p>
                                <button disabled={profileBusy} className="button-primary">
                                    {profileSaved ? <Check size={16} /> : null}
                                    {profileBusy ? t('portal.dashboard.saving') : profileSaved ? t('portal.dashboard.saved') : t('portal.dashboard.save_details')}
                                </button>
                            </div>
                        </form>
                        <section className="mt-8 grid gap-6 md:grid-cols-[1fr_0.9fr]" aria-labelledby="support-heading">
                            <div className="card p-6">
                                <h2 id="support-heading" className="section-title">
                                    {t('portal.dashboard.support_tickets')}
                                </h2>
                                <div className="mt-4 divide-y divide-line">
                                    {tickets.map((ticket) => (
                                        <div key={ticket.uuid} className="space-y-3 py-3 text-sm">
                                            <div className="flex items-center justify-between gap-4">
                                                <span>
                                                    <b>{ticket.subject}</b>
                                                    <small className="mt-1 block text-muted">
                                                        {ticket.number} · {ticket.status}
                                                    </small>
                                                </span>
                                                    <span className="text-xs text-muted">
                                                        {ticket.message_count} {t('portal.dashboard.messages')}
                                                </span>
                                            </div>
                                            {(ticket.status === 'resolved' || ticket.status === 'closed') && (
                                                <label className="block max-w-xs">
                                                    <span className="field-label">{t('portal.dashboard.rate_support')}</span>
                                                    <ResponsiveSelect
                                                        className="field"
                                                        value={ticket.satisfaction_rating?.toString() ?? ''}
                                                        disabled={ratingBusy === ticket.uuid}
                                                        onChange={(event) =>
                                                            rateTicket(ticket.uuid, Number(event.target.value))
                                                        }
                                                    >
                                                        <option value="">{t('portal.dashboard.choose_rating')}</option>
                                                        {[1, 2, 3, 4, 5].map((rating) => (
                                                            <option key={rating} value={rating}>
                                                                {rating}/5
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                </label>
                                            )}
                                        </div>
                                    ))}
                                    {tickets.length === 0 && (
                                        <p className="py-3 text-sm text-muted">{t('portal.dashboard.no_tickets')}</p>
                                    )}
                                </div>
                                {supportMessage && <p className="mt-3 text-sm text-brand">{supportMessage}</p>}
                            </div>
                            <form onSubmit={submitTicket} className="card space-y-4 p-6">
                                <h2 className="section-title">{t('portal.dashboard.open_ticket')}</h2>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.category')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={ticketForm.category}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, category: event.target.value })
                                        }
                                    >
                                        <option value="no_service">{t('portal.category.no_service')}</option>
                                        <option value="slow">{t('portal.category.slow')}</option>
                                        <option value="billing">{t('portal.category.billing')}</option>
                                        <option value="relocation">{t('portal.category.relocation')}</option>
                                        <option value="other">{t('portal.category.other')}</option>
                                    </ResponsiveSelect>
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.subject')}</span>
                                    <input
                                        required
                                        className="field"
                                        value={ticketForm.subject}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, subject: event.target.value })
                                        }
                                    />
                                </label>
                                <label className="block">
                                    <span className="field-label">{t('portal.dashboard.what_happened')}</span>
                                    <textarea
                                        required
                                        rows={4}
                                        className="field"
                                        value={ticketForm.description}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, description: event.target.value })
                                        }
                                    />
                                </label>
                                <button disabled={ticketBusy} className="button-primary w-full justify-center">
                                    <Send size={16} />
                                    {ticketBusy ? t('portal.dashboard.sending') : t('portal.dashboard.send_ticket')}
                                </button>
                            </form>
                        </section>
                    </>
                )}
            </main>
            <Link href={`/portal/${tenant.slug}`} className="sr-only">
                {t('portal.dashboard.return_to_sign_in')}
            </Link>
        </div>
    );
}

function StripeCheckout({
    clientSecret,
   publishableKey,
    t,
   onSubmitted,
    onError,
}: {
    clientSecret: string;
   publishableKey: string;
    t: (key: string) => string;
    onSubmitted: () => void;
    onError: (message: string) => void;
}) {
    const stripePromise = useMemo(() => loadStripe(publishableKey), [publishableKey]);

    return (
        <div className="mt-5 rounded-2xl border border-line p-4">
            <Elements stripe={stripePromise} options={{ clientSecret }}>
                <StripePaymentForm t={t} onSubmitted={onSubmitted} onError={onError} />
            </Elements>
        </div>
    );
}

function StripePaymentForm({ t, onSubmitted, onError }: { t: (key: string) => string; onSubmitted: () => void; onError: (message: string) => void }) {
    const stripe = useStripe();
    const elements = useElements();
    const [busy, setBusy] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        if (!stripe || !elements) return;
        setBusy(true);
        const result = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: window.location.href },
            redirect: 'if_required',
        });
        if (result.error) {
            onError(result.error.message ?? t('portal.dashboard.payment_confirm_error'));
        } else {
            onSubmitted();
        }
        setBusy(false);
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <PaymentElement />
            <button
                type="submit"
                disabled={busy || !stripe || !elements}
                className="button-primary w-full justify-center"
            >
                <CreditCard size={16} />
                {busy ? t('portal.dashboard.confirming_payment') : t('portal.dashboard.pay_securely')}
            </button>
        </form>
    );
}
