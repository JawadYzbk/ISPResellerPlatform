import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Download, Printer } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';

type Payment = {
    public_id: string;
    number: string;
    status: 'posted' | 'reversed';
    amount: number;
    currency: string;
    method: string;
    received_at: string | null;
    reversed_at: string | null;
    collector: string | null;
    cash_shift: string | null;
    customer: { public_id: string; code: string; name: string };
    invoice: { public_id: string; number: string } | null;
    allocations: { id: number; amount: number; currency: string; invoice: { public_id: string; number: string } }[];
};

type Props = { payment: Payment; canReverse: boolean };

export default function PaymentShowPage({ payment, canReverse }: Props) {
    const reverse = () => {
        if (window.confirm(`Reverse payment ${payment.number}?`)) {
            router.post(`/billing/payments/${payment.public_id}/reverse`);
        }
    };

    return (
        <AppLayout>
            <Head title={payment.number} />
            <div className="flex items-center justify-between gap-4 print:hidden">
                <Link href="/billing/payments" className="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand">
                    <ArrowLeft size={16} /> Back to payments
                </Link>
                <div className="flex gap-2">
                    <a href={`/billing/payments/${payment.public_id}/pdf`} className="button-secondary"><Download size={16} /> Download PDF</a>
                    <button type="button" className="button-secondary" onClick={() => window.print()}><Printer size={16} /> Print receipt</button>
                </div>
            </div>

            <div className="mt-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div>
                    <p className="eyebrow">Collection receipt</p>
                    <h1 className="page-title">{payment.number}</h1>
                    <p className="page-subtitle">Received {formatDate(payment.received_at)} · {payment.collector ?? 'System posted'}</p>
                </div>
                <StatusBadge status={payment.status} />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="card p-6">
                    <div className="flex items-start justify-between gap-5">
                        <div>
                            <p className="text-sm text-muted">Amount received</p>
                            <p className="mt-2 text-3xl font-bold tracking-tight">{formatMoney(payment.amount, payment.currency)}</p>
                        </div>
                        <div className="grid size-11 place-items-center rounded-xl bg-brand-soft text-brand"><CreditCard size={20} /></div>
                    </div>
                    <dl className="mt-8 grid gap-5 sm:grid-cols-2">
                        <div><dt className="field-label">Customer</dt><dd className="mt-1 text-sm font-semibold"><Link href={`/customers/${payment.customer.public_id}`} className="hover:text-brand">{payment.customer.name}</Link><span className="mt-1 block text-xs font-normal text-muted">{payment.customer.code}</span></dd></div>
                        <div><dt className="field-label">Method</dt><dd className="mt-1 text-sm font-semibold capitalize">{payment.method.replace('_', ' ')}</dd></div>
                        <div><dt className="field-label">Invoice</dt><dd className="mt-1 text-sm font-semibold">{payment.invoice ? <Link href={`/billing/invoices/${payment.invoice.public_id}`} className="hover:text-brand">{payment.invoice.number}</Link> : 'Account credit'}</dd></div>
                        <div><dt className="field-label">Cash shift</dt><dd className="mt-1 text-sm font-semibold">{payment.cash_shift ?? 'Not assigned'}</dd></div>
                    </dl>
                    {payment.reversed_at && <p className="mt-8 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">Reversed on {formatDate(payment.reversed_at)}. This receipt no longer counts toward invoice balances.</p>}
                </div>

                <div className="card h-fit p-5 print:hidden">
                    <p className="text-sm font-semibold">Receipt actions</p>
                    <div className="mt-5 space-y-3">
                        <a href={`/billing/payments/${payment.public_id}/pdf`} className="button-secondary w-full justify-center"><Download size={16} /> Download PDF</a>
                        <button type="button" className="button-secondary w-full justify-center" onClick={() => window.print()}><Printer size={16} /> Print receipt</button>
                        {canReverse && payment.status === 'posted' && <button type="button" className="button-danger w-full justify-center" onClick={reverse}>Reverse posted payment</button>}
                    </div>
                    <p className="mt-4 text-xs leading-5 text-muted">Reversal is append-only and requires recent authentication. The original receipt remains visible for audit.</p>
                </div>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="border-b border-line px-5 py-4"><p className="text-sm font-semibold">Allocation trail</p><p className="mt-1 text-xs text-muted">Posted invoice allocations recorded with this receipt.</p></div>
                <div className="divide-y divide-line">
                    {payment.allocations.map((allocation) => <Link key={allocation.id} href={`/billing/invoices/${allocation.invoice.public_id}`} className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-sand/30"><p className="text-sm font-semibold">{allocation.invoice.number}</p><p className="text-sm font-semibold">{formatMoney(allocation.amount, allocation.currency)}</p></Link>)}
                    {payment.allocations.length === 0 && <p className="px-5 py-10 text-center text-sm text-muted">No invoice allocation. This receipt is account credit.</p>}
                </div>
            </div>
        </AppLayout>
    );
}
