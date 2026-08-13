import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Plus, RefreshCw, Scale } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type ExchangeRate = {
    id: number;
    base_currency: string;
    quote_currency: string;
    rate_numerator: number;
    rate_denominator: number;
    effective_from: string | null;
    source: string;
};

type RateForm = {
    base_currency: string;
    quote_currency: string;
    rate_numerator: number;
    rate_denominator: number;
    effective_from: string;
    source: string;
};

type Props = PageProps & {
    rates: Paginator<ExchangeRate>;
    filters: { base_currency?: string; quote_currency?: string };
    frankfurterEnabled: boolean;
    workspaceCurrencies: { base: string | null; collection: string | null };
    currencies: CurrencyOption[];
};

function formatExchangeRate(numerator: number, denominator: number, locale: string): string {
    const value = denominator > 0 ? numerator / denominator : Number.NaN;

    if (!Number.isFinite(value)) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(locale, {
            maximumFractionDigits: 8,
        }).format(value);
    } catch {
        return value.toLocaleString(undefined, { maximumFractionDigits: 8 });
    }
}

function Pagination({ rates, t }: { rates: Paginator<ExchangeRate>; t: (key: string) => string }) {
    return (
        <div className="flex items-center justify-between border-t border-line px-5 py-4">
            <p className="text-xs text-muted">
                {t('Page')} {rates.current_page} {t('of')} {rates.last_page}
            </p>
            <div className="flex items-center gap-1">
                {rates.links.map((link, index) => {
                    const isPrevious = index === 0;
                    const isNext = index === rates.links.length - 1;

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
                                t(link.label)
                            )}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export default function ExchangeRatesPage({
    rates,
    filters,
    frankfurterEnabled,
    workspaceCurrencies,
    currencies,
}: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [baseCurrency, setBaseCurrency] = useState(filters.base_currency ?? '');
    const [quoteCurrency, setQuoteCurrency] = useState(filters.quote_currency ?? '');
    const defaultBaseCurrency =
        currencies.find((currency) => currency.code.toUpperCase() === 'USD')?.code ?? currencies[0]?.code ?? '';
    const defaultQuoteCurrency =
        currencies.find((currency) => currency.code.toUpperCase() === 'LBP')?.code ??
        currencies.find((currency) => currency.code !== defaultBaseCurrency)?.code ??
        '';
    const form = useForm<RateForm>({
        base_currency: defaultBaseCurrency,
        quote_currency: defaultQuoteCurrency,
        rate_numerator: 1,
        rate_denominator: 1,
        effective_from: new Date().toISOString().slice(0, 10),
        source: 'manual',
    });
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/billing/exchange-rates',
            {
                base_currency: baseCurrency.toUpperCase() || undefined,
                quote_currency: quoteCurrency.toUpperCase() || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            base_currency: data.base_currency.toUpperCase(),
            quote_currency: data.quote_currency.toUpperCase(),
        }));
        form.post('/billing/exchange-rates', {
            preserveScroll: true,
            onSuccess: () =>
                form.reset('base_currency', 'quote_currency', 'rate_numerator', 'rate_denominator', 'source'),
        });
    };

    return (
        <AppLayout>
            <Head title={t('Exchange rates')} />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to settings')}
            </Link>

            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Billing configuration')}</p>
                    <h1 className="page-title">{t('Exchange rates')}</h1>
                    <p className="page-subtitle">
                        {t('Maintain exact, effective-dated currency ratios for billing and collection.')}
                    </p>
                </div>
                <div className="rounded-xl border border-line bg-white px-4 py-3 text-sm text-muted">
                    <span className="font-semibold text-ink">{t('Fraction based')}</span> ·{' '}
                    {t('No rounding is applied before the payment snapshot.')}
                </div>
            </div>

            <div className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold">{t('Frankfurter market rates')}</p>
                    <p className="mt-1 text-sm text-muted">
                        {t('Import append-only rates for')}{' '}
                        {workspaceCurrencies.base ?? t('the workspace base currency')} {t('into')}{' '}
                        {workspaceCurrencies.collection ?? t('the collection currency')}.
                    </p>
                </div>
                {frankfurterEnabled ? (
                    <button
                        type="button"
                        className="button-secondary shrink-0"
                        onClick={() => router.post('/billing/exchange-rates/sync')}
                    >
                        <RefreshCw size={16} /> {t('Sync Frankfurter')}
                    </button>
                ) : (
                    <span className="text-xs text-muted">{t('Enable FRANKFURTER_ENABLED to use provider rates.')}</span>
                )}
            </div>

            <form onSubmit={submit} className="card mt-8 p-6">
                <div className="flex items-center gap-2">
                    <Plus size={17} className="text-brand" />
                    <h2 className="section-title">{t('Add a rate')}</h2>
                </div>
                <p className="mt-1 text-sm text-muted">
                    {t('Use an exact fraction so historical payments remain reproducible.')}
                </p>
                <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <label>
                        <span className="field-label">{t('Base currency')}</span>
                        <CurrencyCombobox
                            id="base_currency"
                            className="field"
                            {...fieldA11y('base_currency', form.errors.base_currency)}
                            value={form.data.base_currency}
                            currencies={currencies}
                            onChange={(value) => form.setData('base_currency', value)}
                        />
                        {fieldError('base_currency', form.errors.base_currency)}
                    </label>
                    <label>
                        <span className="field-label">{t('Quote currency')}</span>
                        <CurrencyCombobox
                            id="quote_currency"
                            className="field"
                            {...fieldA11y('quote_currency', form.errors.quote_currency)}
                            value={form.data.quote_currency}
                            currencies={currencies}
                            onChange={(value) => form.setData('quote_currency', value)}
                        />
                        {fieldError('quote_currency', form.errors.quote_currency)}
                    </label>
                    <label>
                        <span className="field-label">{t('Effective from')}</span>
                        <input
                            id="effective_from"
                            className="field"
                            type="date"
                            {...fieldA11y('effective_from', form.errors.effective_from)}
                            value={form.data.effective_from}
                            onChange={(event) => form.setData('effective_from', event.target.value)}
                        />
                        {fieldError('effective_from', form.errors.effective_from)}
                    </label>
                    <label>
                        <span className="field-label">{t('Numerator')}</span>
                        <input
                            id="rate_numerator"
                            className="field"
                            type="number"
                            min={1}
                            step={1}
                            {...fieldA11y('rate_numerator', form.errors.rate_numerator)}
                            value={form.data.rate_numerator}
                            onChange={(event) => form.setData('rate_numerator', Number(event.target.value))}
                        />
                        {fieldError('rate_numerator', form.errors.rate_numerator)}
                    </label>
                    <label>
                        <span className="field-label">{t('Denominator')}</span>
                        <input
                            id="rate_denominator"
                            className="field"
                            type="number"
                            min={1}
                            step={1}
                            {...fieldA11y('rate_denominator', form.errors.rate_denominator)}
                            value={form.data.rate_denominator}
                            onChange={(event) => form.setData('rate_denominator', Number(event.target.value))}
                        />
                        {fieldError('rate_denominator', form.errors.rate_denominator)}
                    </label>
                    <label>
                        <span className="field-label">{t('Source')}</span>
                        <input
                            id="source"
                            className="field"
                            maxLength={80}
                            {...fieldA11y('source', form.errors.source)}
                            value={form.data.source}
                            onChange={(event) => form.setData('source', event.target.value)}
                            placeholder={t('Treasury desk')}
                        />
                        {fieldError('source', form.errors.source)}
                    </label>
                </div>
                <div className="mt-5 flex justify-end">
                    <button type="submit" className="button-primary" disabled={form.processing}>
                        <RefreshCw size={16} className={form.processing ? 'animate-spin' : ''} /> {t('Save rate')}
                    </button>
                </div>
            </form>

            <form onSubmit={applyFilters} className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('Base currency')}</span>
                    <CurrencyCombobox
                        id="filter_base_currency"
                        className="field"
                        value={baseCurrency}
                        currencies={currencies}
                        emptyLabel={t('All currencies')}
                        onChange={setBaseCurrency}
                    />
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('Quote currency')}</span>
                    <CurrencyCombobox
                        id="filter_quote_currency"
                        className="field"
                        value={quoteCurrency}
                        currencies={currencies}
                        emptyLabel={t('All currencies')}
                        onChange={setQuoteCurrency}
                    />
                </label>
                <button type="submit" className="button-secondary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Scale size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {rates.total.toLocaleString()} {t('rate(s)')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('Newest effective rates appear first.')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Pair')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Rate for 1 unit')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Effective from')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Source')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rates.data.map((rate) => (
                                <tr key={rate.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4 text-sm font-semibold">
                                        {rate.base_currency} <span className="text-muted">→</span> {rate.quote_currency}
                                    </td>
                                    <td className="px-5 py-4 font-mono text-sm text-muted" dir="ltr">
                                        <span className="font-semibold text-ink">1 {rate.base_currency}</span> ={' '}
                                        {formatExchangeRate(rate.rate_numerator, rate.rate_denominator, app.locale)}{' '}
                                        {rate.quote_currency}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(rate.effective_from)}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{rate.source}</td>
                                </tr>
                            ))}
                            {rates.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-5 py-16 text-center">
                                        <Scale className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('No rates match these filters')}</p>
                                        <p className="mt-1 text-sm text-muted">
                                            {t('Add the first effective-dated ratio above.')}
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination rates={rates} t={t} />
            </div>
        </AppLayout>
    );
}
