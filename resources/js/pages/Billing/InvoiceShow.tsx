import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, FilePlus2, Printer, ReceiptText } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import PublicLinkCreator, { type PublicLinkSummary } from '@/components/PublicLinkCreator';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';

type Invoice = {
    public_id: string;
    number: string;
    status: 'draft' | 'issued' | 'void';
    currency: string;
    subtotal_amount: number;
    tax_amount: number;
    total_amount: number;
    allocated_amount: number;
    credited_amount: number;
    outstanding_amount: number;
    due_at: string | null;
    issued_at: string | null;
    voided_at: string | null;
    customer: { public_id: string; code: string; name: string };
    lines: {
        id: number;
        description: string;
        quantity: number;
        unit_amount: number;
        total_amount: number;
        currency: string;
        plan: { name: string } | null;
        service: { public_id: string; username: string } | null;
    }[];
    payments: {
        public_id: string;
        number: string;
        amount: number;
        currency: string;
        method: string;
        received_at: string | null;
        collector: string | null;
    }[];
    credit_notes: {
        public_id: string;
        number: string;
        amount: number;
        currency: string;
        reason: string;
        issued_at: string | null;
        creator: string | null;
    }[];
};

export default function InvoiceShowPage({
    invoice,
    canCredit,
    publicLinks,
}: {
    invoice: Invoice;
    canCredit: boolean;
    publicLinks: PublicLinkSummary[];
}) {
    const creditForm = useForm({ amount: '', reason: '' });

    const submitCreditNote = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        creditForm.post('/billing/invoices/' + invoice.public_id + '/credit-notes', {
            onSuccess: () => creditForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title={invoice.number} />
            <div className="flex items-center justify-between gap-4 print:hidden">
                <Link
                    href="/billing/invoices"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                >
                    <ArrowLeft size={16} /> Back to invoices
                </Link>
                <div className="flex gap-2">
                    <a href={`/billing/invoices/${invoice.public_id}/pdf`} className="button-secondary">
                        <Download size={16} /> Download PDF
                    </a>
                    <button type="button" className="button-secondary" onClick={() => window.print()}>
                        <Printer size={16} /> Print invoice
                    </button>
                </div>
            </div>

            <div className="mt-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                <div>
                    <p className="eyebrow">Billing document</p>
                    <h1 className="page-title">{invoice.number}</h1>
                    <p className="page-subtitle">
                        Issued {formatDate(invoice.issued_at)} · Due {formatDate(invoice.due_at)}
                    </p>
                </div>
                <StatusBadge status={invoice.status} />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
                <div className="card overflow-hidden">
                    <div className="border-b border-line px-5 py-4">
                        <p className="text-sm font-semibold">Line items</p>
                        <Link
                            href={`/customers/${invoice.customer.public_id}`}
                            className="mt-1 inline-block text-sm text-muted hover:text-brand"
                        >
                            {invoice.customer.name} · {invoice.customer.code}
                        </Link>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-start">
                            <thead>
                                <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                    <th className="px-5 py-3.5 text-start">Description</th>
                                    <th className="px-5 py-3.5 text-start">Quantity</th>
                                    <th className="px-5 py-3.5 text-start">Unit price</th>
                                    <th className="px-5 py-3.5 text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {invoice.lines.map((line) => (
                                    <tr key={line.id}>
                                        <td className="px-5 py-4">
                                            <p className="text-sm font-semibold">{line.description}</p>
                                            {(line.plan || line.service) && (
                                                <p className="mt-1 text-xs text-muted">
                                                    {line.plan?.name ?? 'Plan removed'}
                                                    {line.service ? ` · ${line.service.username}` : ''}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-5 py-4 text-sm text-muted">{line.quantity}</td>
                                        <td className="px-5 py-4 text-sm text-muted">
                                            {formatMoney(line.unit_amount, line.currency)}
                                        </td>
                                        <td className="px-5 py-4 text-end text-sm font-semibold">
                                            {formatMoney(line.total_amount, line.currency)}
                                        </td>
                                    </tr>
                                ))}
                                {invoice.lines.length === 0 && (
                                    <tr>
                                        <td colSpan={4} className="px-5 py-12 text-center text-sm text-muted">
                                            No line items were recorded.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="card h-fit p-5">
                    <p className="text-sm font-semibold">Invoice totals</p>
                    <dl className="mt-5 space-y-3 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Subtotal</dt>
                            <dd>{formatMoney(invoice.subtotal_amount, invoice.currency)}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Tax</dt>
                            <dd>{formatMoney(invoice.tax_amount, invoice.currency)}</dd>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-line pt-3 font-semibold">
                            <dt>Total</dt>
                            <dd>{formatMoney(invoice.total_amount, invoice.currency)}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Posted payments</dt>
                            <dd className="text-emerald-700">
                                − {formatMoney(invoice.allocated_amount, invoice.currency)}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Credit notes</dt>
                            <dd className="text-emerald-700">
                                − {formatMoney(invoice.credited_amount, invoice.currency)}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4 border-t border-line pt-3 font-semibold">
                            <dt>Outstanding</dt>
                            <dd className={invoice.outstanding_amount > 0 ? 'text-coral' : 'text-emerald-700'}>
                                {formatMoney(invoice.outstanding_amount, invoice.currency)}
                            </dd>
                        </div>
                    </dl>
                    {canCredit && (
                        <form onSubmit={submitCreditNote} className="mt-6 space-y-3 border-t border-line pt-5">
                            <div className="flex items-center gap-2">
                                <FilePlus2 size={16} className="text-brand" />
                                <p className="text-sm font-semibold">Issue credit note</p>
                            </div>
                            <label>
                                <span className="field-label">Amount (minor units)</span>
                                <input
                                    type="number"
                                    min="1"
                                    className="field"
                                    value={creditForm.data.amount}
                                    onChange={(event) => creditForm.setData('amount', event.target.value)}
                                    placeholder="1000"
                                />
                                {creditForm.errors.amount && <p className="field-error">{creditForm.errors.amount}</p>}
                            </label>
                            <label>
                                <span className="field-label">Reason</span>
                                <textarea
                                    className="field min-h-20"
                                    value={creditForm.data.reason}
                                    onChange={(event) => creditForm.setData('reason', event.target.value)}
                                    placeholder="Service interruption"
                                />
                                {creditForm.errors.reason && <p className="field-error">{creditForm.errors.reason}</p>}
                            </label>
                            <button type="submit" className="button-secondary w-full" disabled={creditForm.processing}>
                                Issue credit note
                            </button>
                        </form>
                    )}
                </div>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                    <ReceiptText size={17} className="text-brand" />
                    <p className="text-sm font-semibold">Posted payments</p>
                </div>
                <div className="divide-y divide-line">
                    {invoice.payments.map((payment) => (
                        <Link
                            key={payment.public_id}
                            href={`/billing/payments/${payment.public_id}`}
                            className="flex flex-col gap-2 px-5 py-4 hover:bg-sand/30 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p className="text-sm font-semibold">{payment.number}</p>
                                <p className="mt-1 text-xs capitalize text-muted">
                                    {payment.method.replace('_', ' ')} · {payment.collector ?? 'System'} ·{' '}
                                    {formatDate(payment.received_at)}
                                </p>
                            </div>
                            <p className="text-sm font-semibold">{formatMoney(payment.amount, payment.currency)}</p>
                        </Link>
                    ))}
                    {invoice.payments.length === 0 && (
                        <p className="px-5 py-10 text-center text-sm text-muted">
                            No posted payments have been allocated to this invoice.
                        </p>
                    )}
                </div>
            </div>
            <div className="mt-6">
                <PublicLinkCreator
                    endpoint={`/billing/invoices/${invoice.public_id}/public-links`}
                    types={
                        invoice.outstanding_amount > 0
                            ? [
                                  { value: 'payment', label: 'Payment link' },
                                  { value: 'invoice', label: 'Invoice only' },
                              ]
                            : [{ value: 'invoice', label: 'Invoice only' }]
                    }
                    title="Share invoice or payment link"
                    existingLinks={publicLinks}
                />
            </div>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                    <FilePlus2 size={17} className="text-brand" />
                    <p className="text-sm font-semibold">Credit notes</p>
                </div>
                <div className="divide-y divide-line">
                    {invoice.credit_notes.map((note) => (
                        <div
                            key={note.public_id}
                            className="flex flex-col gap-2 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p className="text-sm font-semibold">{note.number}</p>
                                <p className="mt-1 text-xs text-muted">
                                    {note.reason} · {note.creator ?? 'System'} · {formatDate(note.issued_at)}
                                </p>
                            </div>
                            <p className="text-sm font-semibold text-emerald-700">
                                − {formatMoney(note.amount, note.currency)}
                            </p>
                        </div>
                    ))}
                    {invoice.credit_notes.length === 0 && (
                        <p className="px-5 py-10 text-center text-sm text-muted">No credit notes have been issued.</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
