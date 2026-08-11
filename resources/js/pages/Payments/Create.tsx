import CurrencyCombobox from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CreditCard, QrCode, Receipt, Save } from 'lucide-react';

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

type FxRateSnapshot = {
    source_currency: string;
    target_currency: string;
    numerator: number;
    denominator: number;
    source: string;
    overridden: boolean;
};

type PaymentCurrency = {
    code: string;
    name: string;
    decimal_digits: number;
    rate: FxRateSnapshot | null;
};

type Props = {
    customer: CustomerSummary;
    invoices: InvoiceOption[];
    defaultCurrency?: string;
    paymentCurrencies: PaymentCurrency[];
    whishEnabled: boolean;
};

export default function PaymentCreate({ customer, invoices, defaultCurrency, paymentCurrencies, whishEnabled }: Props) {
    const form = useForm({
        amount: '',
        currency: defaultCurrency ?? customer.balance_currency,
        method: 'cash',
        invoice_id: '',
        idempotency_key: crypto.randomUUID(),
        fx_override: false,
        fx_rate_numerator: '',
        fx_rate_denominator: '',
        fx_override_reason: '',
        rounding_mode: 'half_up',
        reference: '',
    });
    const selectedCurrency = paymentCurrencies.find((item) => item.code === form.data.currency);
    const selectedRate = selectedCurrency?.rate ?? null;
    const fractionDigits = selectedCurrency?.decimal_digits ?? currencyFractionDigits(form.data.currency);
    const needsFx = form.data.currency !== customer.balance_currency;
    const whishSupported = whishEnabled && ['USD', 'LBP', 'AED'].includes(form.data.currency);

    const formatRate = (rate: FxRateSnapshot) => {
        const targetPerSource = rate.numerator / rate.denominator;

        return `1 ${rate.source_currency} = ${targetPerSource.toLocaleString(undefined, { maximumFractionDigits: 6 })} ${rate.target_currency}`;
    };

    const selectCurrency = (currency: string) => {
        form.setData('currency', currency);
        form.setData('amount', '');
        form.setData('fx_override', false);
        form.setData('fx_rate_numerator', '');
        form.setData('fx_rate_denominator', '');
        form.setData('fx_override_reason', '');

        if (form.data.invoice_id) {
            selectInvoice(form.data.invoice_id, currency);
        }
    };

    const selectInvoice = (invoiceId: string, currency = form.data.currency) => {
        form.setData('invoice_id', invoiceId);
        const invoice = invoices.find((item) => item.public_id === invoiceId);
        if (!invoice) {
            form.setData('amount', '');
            return;
        }

        if (invoice.currency === currency) {
            const digits = currencyFractionDigits(invoice.currency);
            form.setData('amount', (invoice.outstanding_amount / 10 ** digits).toFixed(digits));
            return;
        }

        const rate = paymentCurrencies.find((item) => item.code === currency)?.rate;
        if (rate?.target_currency === invoice.currency) {
            const amountMinor = Math.ceil((invoice.outstanding_amount * rate.denominator) / rate.numerator);
            const digits =
                paymentCurrencies.find((item) => item.code === currency)?.decimal_digits ??
                currencyFractionDigits(currency);
            form.setData('amount', (amountMinor / 10 ** digits).toFixed(digits));
            return;
        }

        form.setData('amount', '');
    };

    const toggleFxOverride = (enabled: boolean) => {
        form.setData('fx_override', enabled);
        form.setData('fx_rate_numerator', enabled && selectedRate ? String(selectedRate.numerator) : '');
        form.setData('fx_rate_denominator', enabled && selectedRate ? String(selectedRate.denominator) : '');
        form.setData('fx_override_reason', enabled ? form.data.fx_override_reason : '');
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

    const generateWhishQr = () => {
        const amountMinor = parseMoneyToMinor(form.data.amount, form.data.currency);
        if (amountMinor === null) {
            form.setError('amount', 'Enter a valid positive amount.');
            return;
        }

        form.clearErrors('amount');
        router.post(`/customers/${customer.public_id}/payments/whish`, {
            amount: amountMinor,
            currency: form.data.currency,
            invoice_id: form.data.invoice_id || null,
            idempotency_key: crypto.randomUUID(),
        });
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
                            Amount ({form.data.currency})
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
                            Enter the amount in {form.data.currency}. The ledger stores the converted value in{' '}
                            {customer.balance_currency}.
                        </p>
                        {form.errors.amount && <p className="field-error">{form.errors.amount}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="currency">
                            Payment currency
                        </label>
                        <CurrencyCombobox
                            id="currency"
                            className="field"
                            value={form.data.currency}
                            currencies={paymentCurrencies}
                            onChange={selectCurrency}
                        />
                        {selectedRate ? (
                            <p className="mt-1 text-xs text-muted">Current rate: {formatRate(selectedRate)}</p>
                        ) : needsFx ? (
                            <p className="mt-1 text-xs text-amber-700">
                                No current rate is configured. Enter an approved override before saving.
                            </p>
                        ) : null}
                        {form.errors.currency && <p className="field-error">{form.errors.currency}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="invoice_id">
                            Apply to invoice (optional)
                        </label>
                        <ResponsiveSelect
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
                        </ResponsiveSelect>
                        {form.errors.invoice_id && <p className="field-error">{form.errors.invoice_id}</p>}
                    </div>
                    <div>
                        <label className="field-label" htmlFor="method">
                            Payment method
                        </label>
                        <ResponsiveSelect
                            id="method"
                            className="field"
                            value={form.data.method}
                            onChange={(event) => form.setData('method', event.target.value)}
                        >
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="card">Card (manual record)</option>
                            <option value="mobile_wallet">Mobile wallet</option>
                        </ResponsiveSelect>
                        {form.errors.method && <p className="field-error">{form.errors.method}</p>}
                    </div>
                    {needsFx && (
                        <div className="space-y-4 rounded-xl border border-line bg-sand px-4 py-4">
                            <label className="flex items-start gap-3 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={form.data.fx_override}
                                    onChange={(event) => toggleFxOverride(event.target.checked)}
                                />
                                <span>
                                    <span className="font-semibold text-ink">Use an approved FX override</span>
                                    <span className="mt-1 block text-xs text-muted">
                                        The ratio and reason are stored with this receipt for audit review.
                                    </span>
                                </span>
                            </label>
                            {form.data.fx_override && (
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label className="field-label" htmlFor="fx_rate_numerator">
                                            FX numerator
                                        </label>
                                        <input
                                            id="fx_rate_numerator"
                                            type="number"
                                            min="1"
                                            className="field"
                                            value={form.data.fx_rate_numerator}
                                            onChange={(event) => form.setData('fx_rate_numerator', event.target.value)}
                                        />
                                        {form.errors.fx_rate_numerator && (
                                            <p className="field-error">{form.errors.fx_rate_numerator}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="field-label" htmlFor="fx_rate_denominator">
                                            FX denominator
                                        </label>
                                        <input
                                            id="fx_rate_denominator"
                                            type="number"
                                            min="1"
                                            className="field"
                                            value={form.data.fx_rate_denominator}
                                            onChange={(event) =>
                                                form.setData('fx_rate_denominator', event.target.value)
                                            }
                                        />
                                        {form.errors.fx_rate_denominator && (
                                            <p className="field-error">{form.errors.fx_rate_denominator}</p>
                                        )}
                                    </div>
                                    <div className="sm:col-span-2">
                                        <label className="field-label" htmlFor="fx_override_reason">
                                            Override reason
                                        </label>
                                        <input
                                            id="fx_override_reason"
                                            type="text"
                                            className="field"
                                            placeholder="Approved counter rate"
                                            value={form.data.fx_override_reason}
                                            onChange={(event) => form.setData('fx_override_reason', event.target.value)}
                                        />
                                        {form.errors.fx_override_reason && (
                                            <p className="field-error">{form.errors.fx_override_reason}</p>
                                        )}
                                    </div>
                                </div>
                            )}
                            <div>
                                <label className="field-label" htmlFor="rounding_mode">
                                    Conversion rounding
                                </label>
                                <ResponsiveSelect
                                    id="rounding_mode"
                                    className="field"
                                    value={form.data.rounding_mode}
                                    onChange={(event) => form.setData('rounding_mode', event.target.value)}
                                >
                                    <option value="half_up">Half up (standard)</option>
                                    <option value="floor">Floor (never over-collect)</option>
                                    <option value="ceil">Ceiling</option>
                                </ResponsiveSelect>
                                <p className="mt-1 text-xs text-muted">
                                    The selected policy is saved with the payment rate for audit and receipt history.
                                </p>
                                {form.errors.rounding_mode && (
                                    <p className="field-error">{form.errors.rounding_mode}</p>
                                )}
                            </div>
                        </div>
                    )}
                    <div>
                        <label className="field-label" htmlFor="reference">
                            Payment reference (optional)
                        </label>
                        <input
                            id="reference"
                            type="text"
                            className="field"
                            placeholder="Receipt or transfer reference"
                            value={form.data.reference}
                            onChange={(event) => form.setData('reference', event.target.value)}
                        />
                        {form.errors.reference && <p className="field-error">{form.errors.reference}</p>}
                    </div>
                    {form.data.invoice_id && form.data.currency !== customer.balance_currency && (
                        <p className="text-xs text-muted">
                            Any amount above the invoice balance stays as customer credit after conversion.
                        </p>
                    )}
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            Cancel
                        </Link>
                        {whishSupported && (
                            <button type="button" className="button-secondary" onClick={generateWhishQr}>
                                <QrCode size={16} /> Generate Whish QR
                            </button>
                        )}
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
