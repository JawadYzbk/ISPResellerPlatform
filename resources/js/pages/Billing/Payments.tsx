import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, CreditCard, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
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
            <Head title="Payments" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Billing operations</p>
                    <h1 className="page-title">Payments</h1>
                    <p className="page-subtitle">Review posted collections and keep ledger reversals visible.</p>
                </div>
                <Link href="/billing/invoices" className="button-secondary">
                    Invoice queue
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">Search receipt or customer</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="RCT-0001, customer code, name"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-40">
                    <span className="field-label">Payment status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="posted">Posted</option>
                        <option value="reversed">Reversed</option>
                    </select>
                </label>
                <label className="block sm:min-w-44">
                    <span className="field-label">Method</span>
                    <select className="field" value={method} onChange={(event) => setMethod(event.target.value)}>
                        <option value="">All methods</option>
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank transfer</option>
                        <option value="card">Card</option>
                        <option value="mobile_wallet">Mobile wallet</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <CreditCard size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{payments.total.toLocaleString()} payment(s)</p>
                    </div>
                    <p className="text-xs text-muted">Reversals remain in the queue for audit history.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1120px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Receipt</th>
                                <th className="px-5 py-3.5 text-start">Customer</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Amount</th>
                                <th className="px-5 py-3.5 text-start">Method</th>
                                <th className="px-5 py-3.5 text-start">Invoice</th>
                                <th className="px-5 py-3.5 text-start">Received</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {payments.data.map((payment) => (
                                <tr key={payment.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{payment.number}</p>
                                        <p className="mt-1 text-xs text-muted">{payment.collector ?? 'System'}</p>
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
                                        {payment.method.replace('_', ' ')}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {payment.invoice?.number ?? 'Account credit'}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(payment.received_at)}</td>
                                    <td className="px-5 py-4 text-end">
                                        {canReverse && payment.status === 'posted' && (
                                            <button
                                                type="button"
                                                className="text-sm font-semibold text-coral"
                                                onClick={() => {
                                                    if (window.confirm(`Reverse payment ${payment.number}?`)) {
                                                        router.post(`/billing/payments/${payment.public_id}/reverse`);
                                                    }
                                                }}
                                            >
                                                Reverse
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {payments.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-5 py-16 text-center">
                                        <CreditCard className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No payments match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {payments.current_page} of {payments.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {payments.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === payments.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
