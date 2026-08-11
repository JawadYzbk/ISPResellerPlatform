import { Head, Link } from '@inertiajs/react';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { AlertTriangle, Check, CreditCard, LogOut, RefreshCw, Send, UserRound, Wifi } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import { formatDate } from '@/lib/format';
import type { Customer, PortalBalance, PortalBilling, PortalNotice, PortalTicket, PublicTenant } from '@/types';

type Props = { tenant: PublicTenant };
type StripeIntent = { clientSecret: string; publishableKey: string; invoiceId: string };

export default function PortalDashboard({ tenant }: Props) {
    const [customer, setCustomer] = useState<Customer | null>(null);
    const [balance, setBalance] = useState<PortalBalance | null>(null);
    const [billing, setBilling] = useState<PortalBilling | null>(null);
    const [notices, setNotices] = useState<PortalNotice[]>([]);
    const [tickets, setTickets] = useState<PortalTicket[]>([]);
    const [ticketForm, setTicketForm] = useState({ category: 'other', subject: '', description: '' });
    const [profileForm, setProfileForm] = useState({ email: '', address: '' });
    const [ticketBusy, setTicketBusy] = useState(false);
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
            .catch(() => setError('The portal could not be loaded.'));
    }, [tenant.slug, tokenKey]);

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
            setError(payload.detail ?? payload.message ?? 'We could not update your profile.');
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
                'X-Idempotency-Key': `portal-restart-${crypto.randomUUID()}`,
            },
        });
        if (!response.ok) {
            const payload = await response.json();
            setError(payload.detail ?? payload.message ?? 'We could not restart this connection.');
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
                'X-Idempotency-Key': `portal-payment-${crypto.randomUUID()}`,
            },
            body: JSON.stringify({ invoice_id: invoice.id, amount: invoice.outstanding_amount }),
        });
        const payload = await response.json();
        if (!response.ok) {
            setPaymentMessage(payload.detail ?? payload.message ?? 'We could not start the payment.');
            setPaymentBusy(false);
            return;
        }
        const clientSecret = payload.payload?.client_secret;
        const publishableKey = payload.payload?.publishable_key;
        if (typeof clientSecret !== 'string' || typeof publishableKey !== 'string') {
            setPaymentMessage('The payment provider returned an incomplete checkout session.');
            setPaymentBusy(false);
            return;
        }
        setPaymentIntent({ clientSecret, publishableKey, invoiceId: invoice.id });
        setPaymentBusy(false);
    };

    const paymentSubmitted = async () => {
        setPaymentMessage('Payment submitted. Your balance will update after provider confirmation.');
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
            setError(payload.detail ?? 'We could not open your ticket.');
        }
        setTicketBusy(false);
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-8 text-ink">
            <Head title="Customer portal" />
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
                            <p className="text-sm text-muted">Customer portal</p>
                        </div>
                    </div>
                    <button onClick={signOut} className="button-secondary">
                        <LogOut size={16} />
                        Sign out
                    </button>
                </header>
                {error && <p className="mt-8 field-error">{error}</p>}
                {customer && (
                    <>
                        <div className="mt-12">
                            <p className="eyebrow">Welcome back</p>
                            <h1 className="page-title">
                                {customer.first_name} {customer.last_name ?? ''}
                            </h1>
                            <p className="page-subtitle">Your connections and service status at a glance.</p>
                        </div>
                        <section className="mt-8 grid gap-4 sm:grid-cols-2" aria-label="Account summary">
                            <div className="card p-6">
                                <p className="eyebrow">Current balance</p>
                                <p
                                    className={`mt-3 text-3xl font-semibold ${customer.balance_amount > 0 ? 'text-rose-700' : 'text-ink'}`}
                                >
                                    {(customer.balance_amount / 100).toFixed(2)} {customer.balance_currency}
                                </p>
                                <p className="mt-2 text-sm text-muted">
                                    {balance?.next_due
                                        ? `Next due ${formatDate(balance.next_due.due_at)}`
                                        : 'No outstanding balance'}
                                </p>
                            </div>
                            <div className="card p-6">
                                <p className="eyebrow">Account</p>
                                <p className="mt-3 text-3xl font-semibold">{customer.services.length}</p>
                                <p className="mt-2 text-sm text-muted">
                                    {customer.services.length === 1
                                        ? 'active connection'
                                        : 'connections linked to this account'}
                                </p>
                            </div>
                        </section>
                        {notices.length > 0 && (
                            <section className="mt-8 space-y-3" aria-labelledby="notices-heading">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle size={17} className="text-amber-600" />
                                    <h2 id="notices-heading" className="section-title">
                                        Service notices
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
                                                <span className="text-muted">Usage this period</span>
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
                                                Expires {formatDate(service.expires_at)}
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
                                                        ? 'Restarting…'
                                                        : 'Restart connection'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </article>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="card p-10 text-center">
                                    <p className="font-semibold">No services are linked to this account.</p>
                                    <p className="mt-1 text-sm text-muted">
                                        Contact your provider if this looks incorrect.
                                    </p>
                                </div>
                            )}
                        </section>
                        {billing && (
                            <section className="mt-8 grid gap-6 md:grid-cols-2">
                                <div className="card p-6">
                                    <h2 className="section-title">Invoices</h2>
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
                                                    {(invoice.total_amount / 100).toFixed(2)} {invoice.currency}
                                                </span>
                                            </div>
                                        ))}
                                        {billing.invoices.length === 0 && (
                                            <p className="py-3 text-sm text-muted">No invoices yet.</p>
                                        )}
                                    </div>
                                </div>
                                <div className="card p-6">
                                    <h2 className="section-title">Payment history</h2>
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
                                                    {(payment.amount / 100).toFixed(2)} {payment.currency}
                                                </span>
                                            </div>
                                        ))}
                                        {billing.payments.length === 0 && (
                                            <p className="py-3 text-sm text-muted">No payments yet.</p>
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
                                            Pay an invoice
                                        </h2>
                                    </div>
                                    <p className="mt-2 text-sm text-muted">
                                        Payments are confirmed by the provider before your account is updated.
                                    </p>
                                    {!paymentIntent ? (
                                        <div className="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                                            <label className="block flex-1">
                                                <span className="field-label">Invoice</span>
                                                <select
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
                                                                {(invoice.outstanding_amount / 100).toFixed(2)}{' '}
                                                                {invoice.currency}
                                                            </option>
                                                        ))}
                                                </select>
                                            </label>
                                            <button
                                                type="button"
                                                disabled={paymentBusy}
                                                onClick={startOnlinePayment}
                                                className="button-primary"
                                            >
                                                <CreditCard size={16} />
                                                {paymentBusy ? 'Opening checkout…' : 'Continue to payment'}
                                            </button>
                                        </div>
                                    ) : (
                                        <StripeCheckout
                                            clientSecret={paymentIntent.clientSecret}
                                            publishableKey={paymentIntent.publishableKey}
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
                                    Contact details
                                </h2>
                            </div>
                            <div className="grid gap-5 sm:grid-cols-2">
                                <label className="block">
                                    <span className="field-label">Email</span>
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
                                    <span className="field-label">Address</span>
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
                                <p className="text-sm text-muted">Phone: {customer.phone}</p>
                                <button disabled={profileBusy} className="button-primary">
                                    {profileSaved ? <Check size={16} /> : null}
                                    {profileBusy ? 'Saving…' : profileSaved ? 'Saved' : 'Save details'}
                                </button>
                            </div>
                        </form>
                        <section className="mt-8 grid gap-6 md:grid-cols-[1fr_0.9fr]" aria-labelledby="support-heading">
                            <div className="card p-6">
                                <h2 id="support-heading" className="section-title">
                                    Support tickets
                                </h2>
                                <div className="mt-4 divide-y divide-line">
                                    {tickets.map((ticket) => (
                                        <div
                                            key={ticket.uuid}
                                            className="flex items-center justify-between gap-4 py-3 text-sm"
                                        >
                                            <span>
                                                <b>{ticket.subject}</b>
                                                <small className="mt-1 block text-muted">
                                                    {ticket.number} · {ticket.status}
                                                </small>
                                            </span>
                                            <span className="text-xs text-muted">{ticket.message_count} messages</span>
                                        </div>
                                    ))}
                                    {tickets.length === 0 && (
                                        <p className="py-3 text-sm text-muted">No support tickets yet.</p>
                                    )}
                                </div>
                            </div>
                            <form onSubmit={submitTicket} className="card space-y-4 p-6">
                                <h2 className="section-title">Open a ticket</h2>
                                <label className="block">
                                    <span className="field-label">Category</span>
                                    <select
                                        className="field"
                                        value={ticketForm.category}
                                        onChange={(event) =>
                                            setTicketForm({ ...ticketForm, category: event.target.value })
                                        }
                                    >
                                        <option value="no_service">No service</option>
                                        <option value="slow">Slow connection</option>
                                        <option value="billing">Billing</option>
                                        <option value="relocation">Relocation</option>
                                        <option value="other">Other</option>
                                    </select>
                                </label>
                                <label className="block">
                                    <span className="field-label">Subject</span>
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
                                    <span className="field-label">What happened?</span>
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
                                    {ticketBusy ? 'Sending…' : 'Send ticket'}
                                </button>
                            </form>
                        </section>
                    </>
                )}
            </main>
            <Link href={`/portal/${tenant.slug}`} className="sr-only">
                Return to portal sign in
            </Link>
        </div>
    );
}

function StripeCheckout({
    clientSecret,
    publishableKey,
    onSubmitted,
    onError,
}: {
    clientSecret: string;
    publishableKey: string;
    onSubmitted: () => void;
    onError: (message: string) => void;
}) {
    const stripePromise = useMemo(() => loadStripe(publishableKey), [publishableKey]);

    return (
        <div className="mt-5 rounded-2xl border border-line p-4">
            <Elements stripe={stripePromise} options={{ clientSecret }}>
                <StripePaymentForm onSubmitted={onSubmitted} onError={onError} />
            </Elements>
        </div>
    );
}

function StripePaymentForm({ onSubmitted, onError }: { onSubmitted: () => void; onError: (message: string) => void }) {
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
            onError(result.error.message ?? 'The payment could not be confirmed.');
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
                {busy ? 'Confirming payment…' : 'Pay securely'}
            </button>
        </form>
    );
}
