import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Receipt, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, formatMoney, parseMoneyToMinor } from '@/lib/format';

type CustomerSummary = {
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
    balance_amount: number;
    balance_currency: string;
};

type InvoiceOption = {
    public_id: string;
    number: string;
    currency: string;
    total_amount: number;
    outstanding_amount: number;
    due_at: string | null;
};

type Props = { customer: CustomerSummary; invoices: InvoiceOption[] };

export default function PaymentCreate({ customer, invoices }: Props) {
    const form = useForm({
        amount: '',
        currency: customer.balance_currency,
        method: 'cash',
        invoice_id: '',
        idempotency_key: crypto.randomUUID(),
    });
    const fractionDigits = currencyFractionDigits(customer.balance_currency);

    const selectInvoice = (invoiceId: string) => {
        form.setData('invoice_id', invoiceId);
        const invoice = invoices.find((item) => item.public_id === invoiceId);
        if (invoice) {
            form.setData('amount', (invoice.outstanding_amount / 10 ** fractionDigits).toFixed(fractionDigits));
        }
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(form.data.amount, form.data.currency);
        if (amountMinor === null) {
            form.setError('amount', 'Enter a valid positive amount.');
            return;
        }

        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount: amountMinor }));
        form.post(`/customers/${customer.public_id}/payments`);
    };

    return (
        <AppLayout>
            <Head title="Record payment" />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to customer
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">Billing · {customer.code}</p>
                <h1 className="page-title">Record payment</h1>
                <p className="page-subtitle">
                    Apply a payment to {customer.first_name} {customer.last_name ?? ''} or leave it unallocated as
                    account credit.
                </p>
                <div className="mt-6 flex items-center gap-3 rounded-xl border border-line bg-white px-4 py-3 text-sm">
                    <CreditCard size={18} className="text-brand" />
                    <span className="text-muted">Current balance</span>
                    <span className="ms-auto font-semibold">
                        {formatMoney(customer.balance_amount, customer.balance_currency)}
                    </span>
                </div>
                <form onSubmit={submit} className="card mt-6 space-y-6 p-6">
                    <div>
                        <label className="field-label" htmlFor="amount">
                            Amount ({customer.balance_currency})
                        </label>
                        <input
                            id="amount"
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step={fractionDigits === 0 ? '1' : '0.01'}
                            className="field"
                            placeholder={fractionDigits === 0 ? '0' : '0.00'}
                            value={form.data.amount}
                            onChange={(event) => form.setData('amount', event.target.value)}
                        />
                        <p className="mt-1 text-xs text-muted">
                            The ledger stores this as {customer.balance_currency} minor units.
                        </p>
                        {form.errors.amount && <p className="field-error">{form.errors.amount}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="invoice_id">
                            Apply to invoice (optional)
                        </label>
                        <select
                            id="invoice_id"
                            className="field"
                            value={form.data.invoice_id}
                            onChange={(event) => selectInvoice(event.target.value)}
                        >
                            <option value="">Leave as account credit</option>
                            {invoices.map((invoice) => (
                                <option key={invoice.public_id} value={invoice.public_id}>
                                    {invoice.number} · {formatMoney(invoice.outstanding_amount, invoice.currency)}{' '}
                                    outstanding
                                </option>
                            ))}
                        </select>
                        {form.errors.invoice_id && <p className="field-error">{form.errors.invoice_id}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="method">
                            Payment method
                        </label>
                        <select
                            id="method"
                            className="field"
                            value={form.data.method}
                            onChange={(event) => form.setData('method', event.target.value)}
                        >
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="card">Card</option>
                            <option value="mobile_wallet">Mobile wallet</option>
                        </select>
                        {form.errors.method && <p className="field-error">{form.errors.method}</p>}
                    </div>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            Cancel
                        </Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> Record payment
                        </button>
                    </div>
                    {invoices.length === 0 && (
                        <p className="flex items-center gap-2 text-xs text-muted">
                            <Receipt size={14} /> No issued invoices are currently open for this customer.
                        </p>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
