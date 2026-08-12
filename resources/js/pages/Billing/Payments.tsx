import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CreditCard, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type PaymentRow = {
    public_id: string;
    number: string;
    status: 'posted' | 'reversed';
    amount: number;
    currency: string;
    method: string;
    received_at: string | null;
    reversed_at: string | null;
    collector: string | null;
    customer: { public_id: string; code: string; name: string };
    invoice: { public_id: string; number: string } | null;
};

type Props = PageProps & {
    payments: Paginator<PaymentRow>;
    filters: { status?: string; method?: string; search?: string };
    canReverse?: boolean;
};

export default function PaymentsPage({ payments, filters, canReverse = false }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [method, setMethod] = useState(filters.method ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/billing/payments',
            { search: search || undefined, status: status || undefined, method: method || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('Payments')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Billing operations')}</p>
                    <h1 className="page-title">{t('Payments')}</h1>
                    <p className="page-subtitle">{t('Review posted collections and keep ledger reversals visible.')}</p>
                </div>
                <Link href="/billing/invoices" className="button-secondary">
                    {t('Invoice queue')}
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">{t('Search receipt or customer')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('RCT-0001, customer code, name')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-40">
                    <span className="field-label">{t('Payment status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('All statuses')}</option>
                        <option value="posted">{t('Posted')}</option>
                        <option value="reversed">{t('Reversed')}</option>
                    </ResponsiveSelect>
                </label>
                <label className="block sm:min-w-44">
                    <span className="field-label">{t('Method')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={method}
                        onChange={(event) => setMethod(event.target.value)}
                    >
                        <option value="">{t('All methods')}</option>
                        <option value="cash">{t('Cash')}</option>
                        <option value="bank_transfer">{t('Bank transfer')}</option>
                        <option value="card">{t('Card')}</option>
                        <option value="mobile_wallet">{t('Mobile wallet')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <CreditCard size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {payments.total.toLocaleString()} {t('payment(s)')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('Reversals remain in the queue for audit history.')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1120px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Receipt')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Amount')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Method')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Invoice')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Received')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {payments.data.map((payment) => (
                                <tr key={payment.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/billing/payments/${payment.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {payment.number}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{payment.collector ?? t('System')}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/customers/${payment.customer.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {payment.customer.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{payment.customer.code}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={payment.status} />
                                        {payment.reversed_at && (
                                            <p className="mt-1 text-xs text-muted">{formatDate(payment.reversed_at)}</p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold">
                                        {formatMoney(payment.amount, payment.currency)}
                                    </td>
                                    <td className="px-5 py-4 text-sm capitalize text-muted">
                                        {t(
                                            payment.method === 'bank_transfer'
                                                ? 'Bank transfer'
                                                : payment.method === 'mobile_wallet'
                                                  ? 'Mobile wallet'
                                                  : payment.method === 'cash'
                                                    ? 'Cash'
                                                    : 'Card',
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {payment.invoice?.number ?? t('Account credit')}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(payment.received_at)}</td>
                                    <td className="px-5 py-4 text-end">
                                        {canReverse && payment.status === 'posted' && (
                                            <ConfirmDialog
                                                title={`${t('Reverse payment')} ${payment.number}?`}
                                                description={t(
                                                    'The reversal is append-only and the original receipt remains available for audit.',
                                                )}
                                                confirmLabel={t('Reverse payment')}
                                                destructive
                                                onConfirm={() =>
                                                    router.post(`/billing/payments/${payment.public_id}/reverse`)
                                                }
                                            >
                                                <button type="button" className="text-sm font-semibold text-coral">
                                                    {t('Reverse')}
                                                </button>
                                            </ConfirmDialog>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {payments.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-5 py-16 text-center">
                                        <CreditCard className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('No payments match these filters')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {payments.current_page} {t('of')} {payments.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {payments.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === payments.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? (
                                            <ChevronLeft size={16} />
                                        ) : isNext ? (
                                            <ChevronRight size={16} />
                                        ) : (
                                            link.label
                                        )}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? (
                                        <ChevronLeft size={16} />
                                    ) : isNext ? (
                                        <ChevronRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
