import CustomerCombobox, { type CustomerOption } from '@/components/ui/customer-combobox';
import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, FileText, Save } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, parseMoneyToMinor } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Props = PageProps & {
    customerOptions: CustomerOption[];
    selectedCustomer: CustomerOption | null;
    currencies: CurrencyOption[];
    defaultCurrency: string;
};

type CustomerSearchResponse = { data: CustomerOption[] };

export default function CreateInvoice({ customerOptions, selectedCustomer, currencies, defaultCurrency }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const form = useForm({
        customer_id: selectedCustomer?.id ?? '',
        description: '',
        amount: '',
        currency: defaultCurrency,
        due_at: '',
        issue: true,
    });
    const [customers, setCustomers] = useState(customerOptions);
    const [customerSearchStatus, setCustomerSearchStatus] = useState<'idle' | 'loading' | 'error'>('idle');
    const searchTimer = useRef<number | null>(null);
    const searchController = useRef<AbortController | null>(null);
    const searchSequence = useRef(0);
    const selectedCurrency = currencies.find((currency) => currency.code === form.data.currency);
    const fractionDigits = selectedCurrency?.decimal_digits ?? currencyFractionDigits(form.data.currency);

    useEffect(() => {
        return () => {
            if (searchTimer.current !== null) window.clearTimeout(searchTimer.current);
            searchController.current?.abort();
        };
    }, []);

    const searchCustomers = (query: string) => {
        if (searchTimer.current !== null) window.clearTimeout(searchTimer.current);
        searchController.current?.abort();
        const sequence = ++searchSequence.current;
        if (query.trim() === '') {
            setCustomerSearchStatus('idle');
            setCustomers((current) => {
                const selected = current.find((customer) => customer.id === form.data.customer_id);
                const merged = new Map(customerOptions.map((customer) => [customer.id, customer]));
                if (selected) merged.set(selected.id, selected);

                return Array.from(merged.values());
            });
            return;
        }

        setCustomerSearchStatus('loading');
        searchTimer.current = window.setTimeout(async () => {
            const controller = new AbortController();
            searchController.current = controller;

            try {
                const response = await fetch(`/billing/invoices/customers?search=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(`Customer search failed with status ${response.status}`);

                const payload = (await response.json()) as CustomerSearchResponse;
                if (sequence !== searchSequence.current) return;

                setCustomers((current) => {
                    const selected = current.find((customer) => customer.id === form.data.customer_id);
                    const merged = new Map(payload.data.map((customer) => [customer.id, customer]));
                    if (selected) merged.set(selected.id, selected);

                    return Array.from(merged.values());
                });
                setCustomerSearchStatus('idle');
            } catch (error) {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                if (sequence === searchSequence.current) setCustomerSearchStatus('error');
            } finally {
                if (searchController.current === controller) searchController.current = null;
            }
        }, 250);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(form.data.amount, form.data.currency);
        if (amountMinor === null) {
            form.setError('amount', t('Enter a valid positive amount.'));
            return;
        }

        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount: amountMinor }));
        form.post('/billing/invoices');
    };

    return (
        <AppLayout>
            <Head title={t('Create invoice')} />
            <Link
                href="/billing/invoices"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to invoices')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('Billing operations')}</p>
                <h1 className="page-title">{t('Create invoice')}</h1>
                <p className="page-subtitle">
                    {t('Create a one-off customer charge with a saved currency and amount snapshot.')}
                </p>

                <form onSubmit={submit} className="card mt-8 space-y-6 p-6">
                    <div>
                        <label className="field-label" htmlFor="customer_id">
                            {t('Customer')}
                        </label>
                        <CustomerCombobox
                            id="customer_id"
                            aria-label={t('Customer')}
                            aria-invalid={Boolean(form.errors.customer_id)}
                            aria-describedby={form.errors.customer_id ? 'customer_id-error' : undefined}
                            value={form.data.customer_id}
                            customers={customers}
                            onChange={(value) => form.setData('customer_id', value)}
                            onSearch={searchCustomers}
                            searchStatus={customerSearchStatus}
                            placeholder={t('Select a customer')}
                        />
                        {form.errors.customer_id && (
                            <p id="customer_id-error" className="field-error" role="alert">
                                {t(form.errors.customer_id)}
                            </p>
                        )}
                    </div>

                    <div>
                        <label className="field-label" htmlFor="description">
                            {t('Description')}
                        </label>
                        <textarea
                            id="description"
                            className="field min-h-28 resize-y"
                            maxLength={255}
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            placeholder={t('Installation, equipment, or other one-off charge')}
                        />
                        {form.errors.description && <p className="field-error" role="alert">{t(form.errors.description)}</p>}
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label className="field-label" htmlFor="amount">
                                {t('Amount')} ({form.data.currency})
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
                                {t('The saved invoice uses the smallest unit for exact ledger math.')}
                            </p>
                            {form.errors.amount && <p className="field-error" role="alert">{t(form.errors.amount)}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="currency">
                                {t('Currency')}
                            </label>
                            <CurrencyCombobox
                                id="currency"
                                aria-label={t('Currency')}
                                aria-invalid={Boolean(form.errors.currency)}
                                aria-describedby={form.errors.currency ? 'currency-error' : undefined}
                                value={form.data.currency}
                                currencies={currencies}
                                onChange={(value) => {
                                    form.setData('currency', value);
                                    form.setData('amount', '');
                                }}
                            />
                            {form.errors.currency && (
                                <p id="currency-error" className="field-error" role="alert">
                                    {t(form.errors.currency)}
                                </p>
                            )}
                        </div>
                    </div>

                    <div>
                        <label className="field-label" htmlFor="due_at">
                            {t('Due date')} <span className="font-normal text-muted">({t('optional')})</span>
                        </label>
                        <input
                            id="due_at"
                            type="date"
                            className="field"
                            value={form.data.due_at}
                            onChange={(event) => form.setData('due_at', event.target.value)}
                        />
                        {form.errors.due_at && <p className="field-error" role="alert">{t(form.errors.due_at)}</p>}
                    </div>

                    <label className="flex items-start gap-3 rounded-xl border border-line bg-sand/40 p-4 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.issue}
                            onChange={(event) => form.setData('issue', event.target.checked)}
                            className="mt-0.5 size-4 rounded border-line text-brand focus:ring-brand"
                        />
                        <span>
                            <span className="block font-semibold">{t('Issue invoice immediately')}</span>
                            <span className="mt-1 block text-xs leading-5 text-muted">
                                {t('When enabled, the invoice is posted to accounts receivable and revenue now.')}
                            </span>
                        </span>
                    </label>

                    <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                        <Link href="/billing/invoices" className="button-secondary justify-center">
                            {t('Cancel')}
                        </Link>
                        <button type="submit" disabled={form.processing} className="button-primary justify-center">
                            {form.data.issue ? <FileText size={16} /> : <Save size={16} />}
                            {form.processing
                                ? t('Saving...')
                                : form.data.issue
                                  ? t('Create and issue invoice')
                                  : t('Create draft invoice')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
