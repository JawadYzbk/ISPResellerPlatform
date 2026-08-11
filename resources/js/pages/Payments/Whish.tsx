import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, ExternalLink, LoaderCircle, QrCode, RefreshCw, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';

type CustomerSummary = {
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
};

type AttemptStatus = 'pending' | 'succeeded' | 'failed' | 'settlement_failed';

type Attempt = {
    id: string;
    status: AttemptStatus;
    external_id: string;
    amount: number;
    currency: string;
    collect_url: string;
    qr_code: { format: 'svg'; data_uri: string };
    provider_transaction_id?: string | null;
    payment_id?: string | null;
    last_checked_at?: string | null;
};

type StatusResponse = {
    data?: {
        status: AttemptStatus;
        provider_transaction_id: string | null;
        payment_id: string | null;
        last_checked_at: string | null;
    };
    message?: string;
};

type Props = {
    customer: CustomerSummary;
    attempt: Attempt;
};

export default function WhishPayment({ customer, attempt: initialAttempt }: Props) {
    const [attempt, setAttempt] = useState(initialAttempt);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (attempt.status !== 'pending') {
            return;
        }

        let cancelled = false;
        const checkStatus = async () => {
            try {
                const response = await fetch(`/customers/${customer.public_id}/payments/whish/${attempt.id}`, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const body = (await response.json()) as StatusResponse;
                if (!response.ok) {
                    throw new Error(body.message ?? 'Whish status could not be verified.');
                }
                if (!cancelled && body.data) {
                    setAttempt((current) => ({ ...current, ...body.data }));
                    setError(null);
                }
            } catch (statusError) {
                if (!cancelled) {
                    setError(
                        statusError instanceof Error ? statusError.message : 'Whish status could not be verified.',
                    );
                }
            }
        };

        void checkStatus();
        const interval = window.setInterval(() => void checkStatus(), 4000);

        return () => {
            cancelled = true;
            window.clearInterval(interval);
        };
    }, [attempt.id, attempt.status, customer.public_id]);

    const terminal = attempt.status !== 'pending';
    const succeeded = attempt.status === 'succeeded';
    const failed = attempt.status === 'failed' || attempt.status === 'settlement_failed';

    return (
        <AppLayout>
            <Head title="Whish payment" />
            <Link
                href={`/customers/${customer.public_id}/payments/create`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <RefreshCw size={16} /> Back to payment form
            </Link>
            <div className="mx-auto max-w-3xl">
                <p className="eyebrow">Whish Pay · {customer.code}</p>
                <h1 className="page-title">Scan to pay</h1>
                <p className="page-subtitle">
                    Ask {customer.first_name} {customer.last_name ?? ''} to scan this code in Whish. The server verifies
                    the provider status before posting the payment.
                </p>

                <div className="card mt-6 grid gap-8 p-6 sm:grid-cols-[minmax(0,1fr)_240px] sm:items-center">
                    <div>
                        <div className="flex items-center gap-2 text-sm font-semibold text-brand">
                            <QrCode size={18} /> Whish collection attempt
                        </div>
                        <p className="mt-4 text-3xl font-bold text-ink">
                            {formatMoney(attempt.amount, attempt.currency)}
                        </p>
                        <p className="mt-2 text-sm text-muted">External ID: {attempt.external_id}</p>
                        <div className="mt-6 rounded-xl border border-line bg-sand px-4 py-3 text-sm">
                            {succeeded ? (
                                <p className="flex items-center gap-2 font-semibold text-emerald-700">
                                    <CheckCircle2 size={18} /> Payment confirmed and posted.
                                </p>
                            ) : failed ? (
                                <p className="flex items-center gap-2 font-semibold text-coral">
                                    <XCircle size={18} /> Payment was not posted.
                                </p>
                            ) : (
                                <p className="flex items-center gap-2 font-semibold text-amber-700">
                                    <LoaderCircle className="animate-spin" size={18} /> Waiting for Whish
                                    confirmation...
                                </p>
                            )}
                            <p className="mt-2 text-xs text-muted">
                                {attempt.last_checked_at
                                    ? `Last checked ${new Date(attempt.last_checked_at).toLocaleTimeString()}.`
                                    : 'Status checks begin automatically.'}
                            </p>
                        </div>
                        {error && <p className="mt-3 text-sm text-coral">{error}</p>}
                        <a
                            href={attempt.collect_url}
                            target="_blank"
                            rel="noreferrer"
                            className="button-secondary mt-5 inline-flex"
                        >
                            <ExternalLink size={15} /> Open Whish link
                        </a>
                    </div>
                    <div className="mx-auto rounded-2xl border border-line bg-white p-3 shadow-sm">
                        <img
                            src={attempt.qr_code.data_uri}
                            alt="Scan to open Whish payment"
                            className="block size-48"
                        />
                    </div>
                </div>

                {terminal && (
                    <div className="mt-6 flex justify-end">
                        <Link href={`/customers/${customer.public_id}`} className="button-primary">
                            {succeeded ? 'Open customer record' : 'Return to customer'}
                        </Link>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
