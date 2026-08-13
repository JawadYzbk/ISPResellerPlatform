import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Download, Printer } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import PublicLinkCreator, { type PublicLinkSummary } from '@/components/PublicLinkCreator';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Payment = {
    public_id: string;
    number: string;
    status: 'posted' | 'reversed';
    amount: number;
    currency: string;
    ledger_amount: number | null;
    ledger_currency: string;
    base_amount: number | null;
    base_currency: string;
    fx_rate_numerator: number | null;
    fx_rate_denominator: number | null;
    fx_rate_overridden: boolean;
    fx_override_reason: string | null;
    fx_rounding_mode: 'half_up' | 'floor' | 'ceil' | 'ceil_5000' | null;
    fx_rate_source: string | null;
    fx_rate_effective_from: string | null;
    reference: string | null;
    method: string;
    received_at: string | null;
    reversed_at: string | null;
    collector: string | null;
    cash_shift: string | null;
    customer: { public_id: string; code: string; name: string };
    invoice: { public_id: string; number: string } | null;
    allocations: { id: number; amount: number; currency: string; invoice: { public_id: string; number: string } }[];
};

type Props = { payment: Payment; canReverse: boolean; canShare: boolean; publicLinks: PublicLinkSummary[] };

export default function PaymentShowPage({ payment, canReverse, canShare, publicLinks }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const methodLabel = (method: string) =>
        t(
            method === 'bank_transfer'
                ? 'Bank transfer'
                : method === 'mobile_wallet'
                  ? 'Mobile wallet'
                  : method === 'cash'
                    ? 'Cash'
                    : 'Card',
        );
    const reverse = () => {
        router.post(`/billing/payments/${payment.public_id}/reverse`);
    };

    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="flex items-center justify-between gap-4 print:hidden">
                <Link
                    href="/billing/payments"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                >
                    <ArrowLeft size={16} /> {t('Back to payments')}
                </Link>
                <div className="flex gap-2">
                    <a href={`/billing/payments/${payment.public_id}/pdf`} className="button-secondary">
                        <Download size={16} /> {t('Download PDF')}
                    </a>
                    <a
                        href={`/billing/payments/${payment.public_id}/compact-pdf?width=80`}
                        className="button-secondary"
                    >
                        <Printer size={16} /> {t('payment.receipt_80mm')}
                    </a>
                    <button type="button" className="button-secondary" onClick={() => window.print()}>
                        <Printer size={16} /> {t('Print receipt')}
                    </button>
                </div>
            </div>

            <div className="mt-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div>
                    <p className="eyebrow">{t('payment.collection_receipt')}</p>
                    <h1 className="page-title">{payment.number}</h1>
                    <p className="page-subtitle">
                        {t('Received')} {formatDate(payment.received_at)} · {payment.collector ?? t('System posted')}
                    </p>
                </div>
                <StatusBadge status={payment.status} />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="card p-6">
                    <div className="flex items-start justify-between gap-5">
                        <div>
                            <p className="text-sm text-muted">{t('payment.amount_received')}</p>
                            <p className="mt-2 text-3xl font-bold tracking-tight">
                                {formatMoney(payment.amount, payment.currency)}
                            </p>
                        </div>
                        <div className="grid size-11 place-items-center rounded-xl bg-brand-soft text-brand">
                            <CreditCard size={20} />
                        </div>
                    </div>
                    <dl className="mt-8 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt className="field-label">{t('Customer')}</dt>
                            <dd className="mt-1 text-sm font-semibold">
                                <Link href={`/customers/${payment.customer.public_id}`} className="hover:text-brand">
                                    {payment.customer.name}
                                </Link>
                                <span className="mt-1 block text-xs font-normal text-muted">
                                    {payment.customer.code}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt className="field-label">{t('Method')}</dt>
                            <dd className="mt-1 text-sm font-semibold capitalize">
                                {methodLabel(payment.method)}
                            </dd>
                        </div>
                        <div>
                            <dt className="field-label">{t('Invoice')}</dt>
                            <dd className="mt-1 text-sm font-semibold">
                                {payment.invoice ? (
                                    <Link
                                        href={`/billing/invoices/${payment.invoice.public_id}`}
                                        className="hover:text-brand"
                                    >
                                        {payment.invoice.number}
                                    </Link>
                                ) : (
                                    t('payment.account_credit')
                                )}
                            </dd>
                        </div>
                        <div>
                            <dt className="field-label">{t('Cash shifts')}</dt>
                            <dd className="mt-1 text-sm font-semibold">{payment.cash_shift ?? t('Unassigned')}</dd>
                        </div>
                        {payment.ledger_amount !== null && payment.ledger_currency !== payment.currency && (
                            <div>
                                <dt className="field-label">{t('payment.ledger_equivalent')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {formatMoney(payment.ledger_amount, payment.ledger_currency)}
                                </dd>
                            </div>
                        )}
                        {payment.base_amount !== null && (
                            <div>
                                <dt className="field-label">{t('payment.base_equivalent')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {formatMoney(payment.base_amount, payment.base_currency)}
                                </dd>
                            </div>
                        )}
                        {payment.reference && (
                            <div>
                                <dt className="field-label">{t('Reference')}</dt>
                                <dd className="mt-1 text-sm font-semibold break-all">{payment.reference}</dd>
                            </div>
                        )}
                        {payment.fx_rate_overridden && (
                            <div>
                                <dt className="field-label">{t('payment.fx_override')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {payment.fx_rate_numerator}/{payment.fx_rate_denominator}
                                    <span className="mt-1 block text-xs font-normal text-muted">
                                        {payment.fx_override_reason}
                                    </span>
                                </dd>
                            </div>
                        )}
                        {payment.fx_rounding_mode && (
                            <div>
                                <dt className="field-label">{t('payment.fx_policy')}</dt>
                                <dd className="mt-1 text-sm font-semibold">
                                    {payment.fx_rounding_mode.replace('_', ' ')}
                                    <span className="mt-1 block text-xs font-normal text-muted">
                                        {payment.fx_rate_source ?? t('stored rate')}
                                        {payment.fx_rate_effective_from
                                            ? ` · ${t('effective')} ${formatDate(payment.fx_rate_effective_from)}`
                                           : ''}
                                    </span>
                                </dd>
                            </div>
                        )}
                    </dl>
                    {payment.reversed_at && (
                        <p className="mt-8 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            {t('payment.reversed_note')} {formatDate(payment.reversed_at)}.{' '}
                            {t('payment.reversed_effect')}
                        </p>
                    )}
                </div>

                <div className="card h-fit p-5 print:hidden">
                    <p className="text-sm font-semibold">{t('payment.receipt_actions')}</p>
                    <div className="mt-5 space-y-3">
                        <a
                            href={`/billing/payments/${payment.public_id}/pdf`}
                            className="button-secondary w-full justify-center"
                        >
                            <Download size={16} /> {t('Download PDF')}
                        </a>
                        <div className="grid grid-cols-2 gap-2">
                            <a
                                href={`/billing/payments/${payment.public_id}/compact-pdf?width=58`}
                                className="button-secondary justify-center"
                            >
                                58 mm
                            </a>
                            <a
                                href={`/billing/payments/${payment.public_id}/compact-pdf?width=80`}
                                className="button-secondary justify-center"
                            >
                                80 mm
                            </a>
                        </div>
                        <button
                            type="button"
                            className="button-secondary w-full justify-center"
                            onClick={() => window.print()}
                        >
                            <Printer size={16} /> {t('Print receipt')}
                        </button>
                        {canReverse && payment.status === 'posted' && (
                            <ConfirmDialog
                                title={`${t('payment.reverse')} ${payment.number}?`}
                                description={t('payment.reverse_description')}
                                confirmLabel={t('payment.reverse')}
                                destructive
                                onConfirm={reverse}
                            >
                                <button type="button" className="button-danger w-full justify-center">
                                    {t('payment.reverse_posted')}
                                </button>
                            </ConfirmDialog>
                        )}
                    </div>
                    <p className="mt-4 text-xs leading-5 text-muted">{t('payment.reverse_note')}</p>
                </div>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="border-b border-line px-5 py-4">
                    <p className="text-sm font-semibold">{t('payment.allocation_trail')}</p>
                    <p className="mt-1 text-xs text-muted">{t('payment.allocation_description')}</p>
                </div>
                <div className="divide-y divide-line">
                    {payment.allocations.map((allocation) => (
                        <Link
                            key={allocation.id}
                            href={`/billing/invoices/${allocation.invoice.public_id}`}
                            className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-sand/30"
                        >
                            <p className="text-sm font-semibold">{allocation.invoice.number}</p>
                            <p className="text-sm font-semibold">
                                {formatMoney(allocation.amount, allocation.currency)}
                            </p>
                        </Link>
                    ))}
                    {payment.allocations.length === 0 && (
                        <p className="px-5 py-10 text-center text-sm text-muted">{t('payment.no_allocations')}</p>
                    )}
                </div>
            </div>
            {canShare && (
                <div className="mt-6">
                    <PublicLinkCreator
                        endpoint={`/billing/payments/${payment.public_id}/public-links`}
                        types={[{ value: 'receipt', label: t('payment.receipt_link') }]}
                        title={t('payment.share_receipt')}
                        existingLinks={publicLinks}
                    />
                </div>
            )}
        </AppLayout>
    );
}
