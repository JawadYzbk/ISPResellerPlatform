import { Head } from '@inertiajs/react';
import { Elements, PaymentElement, useElements, useStripe } from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { CheckCircle2, CreditCard, Download, ExternalLink, Printer, ReceiptText, Wifi } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import StatusBadge, { type Status } from '@/components/StatusBadge';
import { formatDate, formatMoney } from '@/lib/format';
import { createIdempotencyKey } from '@/lib/idempotency';

type Tenant = { name: string; slug: string; logo_url: string | null };
type Customer = { code: string; name: string; balance_amount: number; balance_currency: string };
type Invoice = {
    public_id: string;
    number: string;
    status: Status;
    currency: string;
    subtotal_amount: number;
    tax_amount: number;
    total_amount: number;
    outstanding_amount: number;
    issued_at: string | null;
    due_at: string | null;
    lines: { description: string; quantity: number; amount: number; currency: string }[];
};
type Payment = {
    public_id: string;
    number: string;
    status: Status;
    amount: number;
    currency: string;
    method: string;
    reference: string | null;
    received_at: string | null;
    allocations: { invoice_number: string; amount: number; currency: string }[];
};
type Gateway = { ready: boolean; status: string; detail: string };
type Props = {
    token: string;
    type: 'invoice' | 'statement' | 'payment' | 'receipt';
    expires_at: string;
    tenant: Tenant;
    customer: Customer;
    invoice: Invoice | null;
    payment: Payment | null;
    statement: { invoices: Invoice[]; payments: Payment[] } | null;
    gateways: { stripe: Gateway; whish: Gateway } | null;
};
type StripeSession = { clientSecret: string; publishableKey: string };
type WhishSession = { attemptId: string; collectUrl: string; qrDataUri: string };

export default function PublicBilling({
    token,
    type,
    expires_at,
    tenant,
    customer,
    invoice,
    payment,
    statement,
    gateways,
}: Props) {
    const [stripeSession, setStripeSession] = useState<StripeSession | null>(null);
    const [whishSession, setWhishSession] = useState<WhishSession | null>(null);
    const [busy, setBusy] = useState<'stripe' | 'whish' | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const payable = type === 'payment' && invoice && invoice.outstanding_amount > 0;

    const startStripe = async () => {
        if (!payable) return;
        setBusy('stripe');
        setMessage(null);
        const response = await fetch(`/api/v1/public-billing/${token}/stripe-intent`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Idempotency-Key': createIdempotencyKey('public-stripe') },
            body: JSON.stringify({ amount: invoice.outstanding_amount }),
        });
        const payload = await response.json();
        if (
            response.ok &&
            typeof payload.payload?.client_secret === 'string' &&
            typeof payload.payload?.publishable_key === 'string'
        ) {
            setStripeSession({
                clientSecret: payload.payload.client_secret,
                publishableKey: payload.payload.publishable_key,
            });
            setWhishSession(null);
        } else setMessage(payload.message ?? 'Stripe checkout could not be started.');
        setBusy(null);
    };
    const startWhish = async () => {
        if (!payable) return;
        setBusy('whish');
        setMessage(null);
        const response = await fetch(`/api/v1/public-billing/${token}/whish`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Idempotency-Key': createIdempotencyKey('public-whish') },
            body: JSON.stringify({ amount: invoice.outstanding_amount }),
        });
        const payload = await response.json();
        if (response.ok) {
            setWhishSession({
                attemptId: payload.attempt_id,
                collectUrl: payload.collect_url,
                qrDataUri: payload.qr_data_uri,
            });
            setStripeSession(null);
        } else setMessage(payload.message ?? 'Whish checkout could not be started.');
        setBusy(null);
    };

    useEffect(() => {
        if (!whishSession) return;
        const timer = window.setInterval(async () => {
            const response = await fetch(`/api/v1/public-billing/${token}/whish/${whishSession.attemptId}`);
            if (!response.ok) return;
            const payload = await response.json();
            if (payload.status === 'succeeded') {
                setMessage('Payment confirmed. Thank you.');
                setWhishSession(null);
                window.clearInterval(timer);
            } else if (payload.terminal) {
                setMessage('The payment was not completed. You can try again.');
                setWhishSession(null);
                window.clearInterval(timer);
            }
        }, 4000);
        return () => window.clearInterval(timer);
    }, [token, whishSession]);

    const title =
        type === 'statement'
            ? 'Account statement'
            : type === 'receipt'
              ? 'Payment receipt'
              : type === 'payment'
                ? 'Pay invoice'
                : 'Invoice';

    return (
        <div className="min-h-dvh bg-canvas px-4 py-6 text-ink sm:px-6 sm:py-10">
            <Head title={`${title} · ${tenant.name}`} />
            <main className="mx-auto max-w-3xl">
                <header className="flex items-center justify-between gap-4 print:hidden">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-brand text-white">
                            {tenant.logo_url ? (
                                <img src={tenant.logo_url} alt="" className="size-full object-cover" />
                            ) : (
                                <Wifi size={19} />
                            )}
                        </span>
                        <div className="min-w-0">
                            <p className="truncate font-display font-bold">{tenant.name}</p>
                            <p className="text-xs text-muted">Secure billing link</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {type !== 'statement' && (
                            <a href={`/share/${token}/pdf`} className="button-secondary">
                                <Download size={15} />
                                <span className="hidden sm:inline">PDF</span>
                            </a>
                        )}
                        <button type="button" className="button-secondary" onClick={() => window.print()}>
                            <Printer size={15} />
                            <span className="hidden sm:inline">Print</span>
                        </button>
                    </div>
                </header>

                <section className="mt-8 card overflow-hidden sm:mt-10">
                    <div className="border-b border-line px-5 py-6 sm:px-8">
                        <p className="eyebrow">{title}</p>
                        <div className="mt-2 flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                            <div>
                                <h1 className="page-title text-balance">
                                    {invoice?.number ?? payment?.number ?? customer.name}
                                </h1>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {customer.name} · {customer.code}
                                </p>
                            </div>
                            {(invoice || payment) && (
                                <StatusBadge status={(invoice?.status ?? payment?.status) as Status} />
                            )}
                        </div>
                    </div>

                    {invoice && <InvoiceDetails invoice={invoice} />}
                    {payment && <PaymentDetails payment={payment} token={token} />}
                    {statement && <StatementDetails customer={customer} statement={statement} />}

                    {type === 'payment' && invoice && (
                        <div className="border-t border-line bg-sand/30 px-5 py-6 sm:px-8">
                            {invoice.outstanding_amount === 0 ? (
                                <div className="flex items-start gap-3 text-emerald-700">
                                    <CheckCircle2 className="mt-0.5 shrink-0" size={20} />
                                    <div>
                                        <p className="font-semibold">This invoice is paid</p>
                                        <p className="mt-1 text-pretty text-sm">No balance remains on this invoice.</p>
                                    </div>
                                </div>
                            ) : (
                                <div>
                                    <h2 className="section-title text-balance">
                                        Pay {formatMoney(invoice.outstanding_amount, invoice.currency)}
                                    </h2>
                                    <p className="mt-1 text-pretty text-sm text-muted">
                                        Choose a configured online payment method. Confirmation is recorded by the
                                        provider callback.
                                    </p>
                                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                        {gateways?.stripe.ready && (
                                            <button
                                                type="button"
                                                className="button-primary justify-center"
                                                disabled={busy !== null}
                                                onClick={startStripe}
                                            >
                                                <CreditCard size={16} />
                                                {busy === 'stripe' ? 'Starting…' : 'Pay by card'}
                                            </button>
                                        )}
                                        {gateways?.whish.ready && (
                                            <button
                                                type="button"
                                                className="button-secondary justify-center"
                                                disabled={busy !== null}
                                                onClick={startWhish}
                                            >
                                                <ExternalLink size={16} />
                                                {busy === 'whish' ? 'Starting…' : 'Pay with Whish'}
                                            </button>
                                        )}
                                    </div>
                                    {!gateways?.stripe.ready && !gateways?.whish.ready && (
                                        <p className="mt-4 rounded-xl border border-line bg-white p-4 text-pretty text-sm text-muted">
                                            Online payment is not configured. Contact {tenant.name} to arrange payment.
                                        </p>
                                    )}
                                    {message && (
                                        <p
                                            className="mt-4 rounded-xl border border-line bg-white p-4 text-pretty text-sm"
                                            role="status"
                                        >
                                            {message}
                                        </p>
                                    )}
                                    {stripeSession && (
                                        <StripeCheckout
                                            session={stripeSession}
                                            onComplete={() => {
                                                setStripeSession(null);
                                                setMessage('Payment submitted. Confirmation may take a moment.');
                                            }}
                                            onError={setMessage}
                                        />
                                    )}
                                    {whishSession && (
                                        <div className="mt-5 rounded-2xl border border-line bg-white p-5 text-center">
                                            <img
                                                src={whishSession.qrDataUri}
                                                alt="Whish payment QR code"
                                                className="mx-auto size-52 max-w-full"
                                            />
                                            <p className="mt-3 text-pretty text-sm text-muted">
                                                Scan with your phone or open Whish Pay directly.
                                            </p>
                                            <a
                                                href={whishSession.collectUrl}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="button-primary mt-4 justify-center"
                                            >
                                                <ExternalLink size={16} />
                                                Open Whish Pay
                                            </a>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </section>
                {type === 'receipt' && (
                    <div className="mt-4 flex justify-center gap-2 print:hidden">
                        <a href={`/share/${token}/pdf?format=compact&width=58`} className="button-secondary">
                            58 mm receipt
                        </a>
                        <a href={`/share/${token}/pdf?format=compact&width=80`} className="button-secondary">
                            80 mm receipt
                        </a>
                    </div>
                )}
                <p className="mt-6 text-center text-pretty text-xs text-muted print:hidden">
                    This private link expires {formatDate(expires_at)}. Do not forward it unless you intend to share
                    this billing document.
                </p>
            </main>
        </div>
    );
}

function InvoiceDetails({ invoice }: { invoice: Invoice }) {
    return (
        <div className="px-5 py-6 sm:px-8">
            <dl className="grid gap-4 sm:grid-cols-3">
                <div>
                    <dt className="field-label">Issued</dt>
                    <dd className="mt-1 text-sm font-semibold">{formatDate(invoice.issued_at)}</dd>
                </div>
                <div>
                    <dt className="field-label">Due</dt>
                    <dd className="mt-1 text-sm font-semibold">{formatDate(invoice.due_at)}</dd>
                </div>
                <div>
                    <dt className="field-label">Outstanding</dt>
                    <dd className="mt-1 text-sm font-semibold tabular-nums">
                        {formatMoney(invoice.outstanding_amount, invoice.currency)}
                    </dd>
                </div>
            </dl>
            <div className="mt-6 divide-y divide-line border-y border-line">
                {invoice.lines.map((line, index) => (
                    <div key={`${line.description}-${index}`} className="flex justify-between gap-4 py-3">
                        <div>
                            <p className="text-sm font-semibold">{line.description}</p>
                            <p className="mt-0.5 text-xs text-muted tabular-nums">Quantity {line.quantity}</p>
                        </div>
                        <p className="text-sm font-semibold tabular-nums">{formatMoney(line.amount, line.currency)}</p>
                    </div>
                ))}
            </div>
            <div className="ms-auto mt-5 max-w-xs space-y-2 text-sm">
                <div className="flex justify-between">
                    <span className="text-muted">Subtotal</span>
                    <span className="tabular-nums">{formatMoney(invoice.subtotal_amount, invoice.currency)}</span>
                </div>
                <div className="flex justify-between">
                    <span className="text-muted">Tax</span>
                    <span className="tabular-nums">{formatMoney(invoice.tax_amount, invoice.currency)}</span>
                </div>
                <div className="flex justify-between border-t border-line pt-2 text-base font-bold">
                    <span>Total</span>
                    <span className="tabular-nums">{formatMoney(invoice.total_amount, invoice.currency)}</span>
                </div>
            </div>
        </div>
    );
}

function PaymentDetails({ payment, token }: { payment: Payment; token: string }) {
    return (
        <div className="px-5 py-6 sm:px-8">
            <div className="text-center">
                <ReceiptText className="mx-auto text-brand" size={24} />
                <p className="mt-3 text-sm text-muted">Amount received</p>
                <p className="mt-1 font-display text-3xl font-bold tabular-nums">
                    {formatMoney(payment.amount, payment.currency)}
                </p>
            </div>
            <dl className="mt-7 grid gap-4 border-t border-line pt-5 sm:grid-cols-3">
                <div>
                    <dt className="field-label">Received</dt>
                    <dd className="mt-1 text-sm font-semibold">{formatDate(payment.received_at)}</dd>
                </div>
                <div>
                    <dt className="field-label">Method</dt>
                    <dd className="mt-1 text-sm font-semibold capitalize">{payment.method.replaceAll('_', ' ')}</dd>
                </div>
                <div>
                    <dt className="field-label">Reference</dt>
                    <dd className="mt-1 break-all text-sm font-semibold">{payment.reference ?? '—'}</dd>
                </div>
            </dl>
            {payment.allocations.length > 0 && (
                <div className="mt-6 divide-y divide-line border-y border-line">
                    {payment.allocations.map((item) => (
                        <div key={item.invoice_number} className="flex justify-between gap-4 py-3 text-sm">
                            <span>{item.invoice_number}</span>
                            <span className="font-semibold tabular-nums">
                                {formatMoney(item.amount, item.currency)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
            <a href={`/share/${token}/pdf`} className="sr-only">
                Download receipt PDF
            </a>
        </div>
    );
}

function StatementDetails({ customer, statement }: { customer: Customer; statement: NonNullable<Props['statement']> }) {
    return (
        <div className="px-5 py-6 sm:px-8">
            <div className="rounded-xl bg-brand-soft p-4">
                <p className="text-sm text-muted">Current account balance</p>
                <p className="mt-1 text-2xl font-bold tabular-nums">
                    {formatMoney(customer.balance_amount, customer.balance_currency)}
                </p>
            </div>
            <h2 className="mt-7 section-title">Recent invoices</h2>
            <div className="mt-3 divide-y divide-line border-y border-line">
                {statement.invoices.map((invoice) => (
                    <div key={invoice.public_id} className="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p className="text-sm font-semibold">{invoice.number}</p>
                            <p className="text-xs text-muted">
                                {formatDate(invoice.issued_at)} · {invoice.status}
                            </p>
                        </div>
                        <div className="text-end">
                            <p className="text-sm font-semibold tabular-nums">
                                {formatMoney(invoice.total_amount, invoice.currency)}
                            </p>
                            <p className="text-xs text-muted tabular-nums">
                                Due {formatMoney(invoice.outstanding_amount, invoice.currency)}
                            </p>
                        </div>
                    </div>
                ))}
                {statement.invoices.length === 0 && (
                    <p className="py-8 text-center text-sm text-muted">No invoices on this account.</p>
                )}
            </div>
            <h2 className="mt-7 section-title">Recent payments</h2>
            <div className="mt-3 divide-y divide-line border-y border-line">
                {statement.payments.map((payment) => (
                    <div key={payment.public_id} className="flex items-center justify-between gap-4 py-3">
                        <div>
                            <p className="text-sm font-semibold">{payment.number}</p>
                            <p className="text-xs text-muted">
                                {formatDate(payment.received_at)} · {payment.method}
                            </p>
                        </div>
                        <p className="text-sm font-semibold tabular-nums">
                            {formatMoney(payment.amount, payment.currency)}
                        </p>
                    </div>
                ))}
                {statement.payments.length === 0 && (
                    <p className="py-8 text-center text-sm text-muted">No posted payments on this account.</p>
                )}
            </div>
        </div>
    );
}

function StripeCheckout({
    session,
    onComplete,
    onError,
}: {
    session: StripeSession;
    onComplete: () => void;
    onError: (message: string) => void;
}) {
    const stripePromise = useMemo(() => loadStripe(session.publishableKey), [session.publishableKey]);
    return (
        <div className="mt-5 rounded-2xl border border-line bg-white p-5">
            <Elements stripe={stripePromise} options={{ clientSecret: session.clientSecret }}>
                <StripeForm onComplete={onComplete} onError={onError} />
            </Elements>
        </div>
    );
}

function StripeForm({ onComplete, onError }: { onComplete: () => void; onError: (message: string) => void }) {
    const stripe = useStripe();
    const elements = useElements();
    const [busy, setBusy] = useState(false);
    return (
        <form
            className="space-y-4"
            onSubmit={async (event) => {
                event.preventDefault();
                if (!stripe || !elements) return;
                setBusy(true);
                const result = await stripe.confirmPayment({
                    elements,
                    confirmParams: { return_url: window.location.href },
                    redirect: 'if_required',
                });
                if (result.error) onError(result.error.message ?? 'The card payment could not be confirmed.');
                else onComplete();
                setBusy(false);
            }}
        >
            <PaymentElement />
            <button className="button-primary w-full justify-center" disabled={busy || !stripe || !elements}>
                <CreditCard size={16} />
                {busy ? 'Confirming…' : 'Confirm card payment'}
            </button>
        </form>
    );
}
