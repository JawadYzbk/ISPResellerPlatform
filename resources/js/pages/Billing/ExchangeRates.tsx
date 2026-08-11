import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronLeft, ChevronRight, Plus, RefreshCw, Scale } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
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
};

function Pagination({ rates }: { rates: Paginator<ExchangeRate> }) {
    return (
        <div className="flex items-center justify-between border-t border-line px-5 py-4">
            <p className="text-xs text-muted">
                Page {rates.current_page} of {rates.last_page}
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
                            {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                        </Link>
                    );
                })}
            </div>
        </div>
    );
}

export default function ExchangeRatesPage({ rates, filters, frankfurterEnabled, workspaceCurrencies }: Props) {
    const [baseCurrency, setBaseCurrency] = useState(filters.base_currency ?? '');
    const [quoteCurrency, setQuoteCurrency] = useState(filters.quote_currency ?? '');
    const form = useForm<RateForm>({
        base_currency: '',
        quote_currency: '',
        rate_numerator: 1,
        rate_denominator: 1,
        effective_from: new Date().toISOString().slice(0, 10),
        source: 'manual',
    });

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
            <Head title="Exchange rates" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to settings
            </Link>

            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Billing configuration</p>
                    <h1 className="page-title">Exchange rates</h1>
                    <p className="page-subtitle">
                        Maintain exact, effective-dated currency ratios for billing and collection.
                    </p>
                </div>
                <div className="rounded-xl border border-line bg-white px-4 py-3 text-sm text-muted">
                    <span className="font-semibold text-ink">Fraction based</span> · No rounding in the rate history
                </div>
            </div>

            <div className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold">Frankfurter market rates</p>
                    <p className="mt-1 text-sm text-muted">
                        Import append-only rates for {workspaceCurrencies.base ?? 'the workspace base currency'} into{' '}
                        {workspaceCurrencies.collection ?? 'the collection currency'}.
                    </p>
                </div>
                {frankfurterEnabled ? (
                    <button
                        type="button"
                        className="button-secondary shrink-0"
                        onClick={() => router.post('/billing/exchange-rates/sync')}
                    >
                        <RefreshCw size={16} /> Sync Frankfurter
                    </button>
                ) : (
                    <span className="text-xs text-muted">Enable FRANKFURTER_ENABLED to use provider sync.</span>
                )}
            </div>

            <form onSubmit={submit} className="card mt-8 p-6">
                <div className="flex items-center gap-2">
                    <Plus size={17} className="text-brand" />
                    <h2 className="section-title">Add a rate</h2>
                </div>
                <p className="mt-1 text-sm text-muted">
                    Add a new effective time instead of editing a rate already used by a transaction.
                </p>
                <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <label>
                        <span className="field-label">Base currency</span>
                        <input
                            className="field"
                            maxLength={3}
                            value={form.data.base_currency}
                            onChange={(event) => form.setData('base_currency', event.target.value.toUpperCase())}
                            placeholder="USD"
                        />
                        {form.errors.base_currency && <p className="field-error">{form.errors.base_currency}</p>}
                    </label>
                    <label>
                        <span className="field-label">Quote currency</span>
                        <input
                            className="field"
                            maxLength={3}
                            value={form.data.quote_currency}
                            onChange={(event) => form.setData('quote_currency', event.target.value.toUpperCase())}
                            placeholder="LBP"
                        />
                        {form.errors.quote_currency && <p className="field-error">{form.errors.quote_currency}</p>}
                    </label>
                    <label>
                        <span className="field-label">Effective from</span>
                        <input
                            className="field"
                            type="date"
                            value={form.data.effective_from}
                            onChange={(event) => form.setData('effective_from', event.target.value)}
                        />
                        {form.errors.effective_from && <p className="field-error">{form.errors.effective_from}</p>}
                    </label>
                    <label>
                        <span className="field-label">Numerator</span>
                        <input
                            className="field"
                            type="number"
                            min={1}
                            step={1}
                            value={form.data.rate_numerator}
                            onChange={(event) => form.setData('rate_numerator', Number(event.target.value))}
                        />
                        {form.errors.rate_numerator && <p className="field-error">{form.errors.rate_numerator}</p>}
                    </label>
                    <label>
                        <span className="field-label">Denominator</span>
                        <input
                            className="field"
                            type="number"
                            min={1}
                            step={1}
                            value={form.data.rate_denominator}
                            onChange={(event) => form.setData('rate_denominator', Number(event.target.value))}
                        />
                        {form.errors.rate_denominator && <p className="field-error">{form.errors.rate_denominator}</p>}
                    </label>
                    <label>
                        <span className="field-label">Source</span>
                        <input
                            className="field"
                            maxLength={80}
                            value={form.data.source}
                            onChange={(event) => form.setData('source', event.target.value)}
                            placeholder="Treasury desk"
                        />
                        {form.errors.source && <p className="field-error">{form.errors.source}</p>}
                    </label>
                </div>
                <div className="mt-5 flex justify-end">
                    <button type="submit" className="button-primary" disabled={form.processing}>
                        <RefreshCw size={16} className={form.processing ? 'animate-spin' : ''} /> Save rate
                    </button>
                </div>
            </form>

            <form onSubmit={applyFilters} className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-48">
                    <span className="field-label">Base currency</span>
                    <input
                        className="field"
                        maxLength={3}
                        value={baseCurrency}
                        onChange={(event) => setBaseCurrency(event.target.value)}
                        placeholder="All"
                    />
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Quote currency</span>
                    <input
                        className="field"
                        maxLength={3}
                        value={quoteCurrency}
                        onChange={(event) => setQuoteCurrency(event.target.value)}
                        placeholder="All"
                    />
                </label>
                <button type="submit" className="button-secondary">
                    Apply filters
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Scale size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{rates.total.toLocaleString()} rate(s)</p>
                    </div>
                    <p className="text-xs text-muted">Newest effective rates appear first.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Pair</th>
                                <th className="px-5 py-3.5 text-start">Exact ratio</th>
                                <th className="px-5 py-3.5 text-start">Effective from</th>
                                <th className="px-5 py-3.5 text-start">Source</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rates.data.map((rate) => (
                                <tr key={rate.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4 text-sm font-semibold">
                                        {rate.base_currency} <span className="text-muted">→</span> {rate.quote_currency}
                                    </td>
                                    <td className="px-5 py-4 font-mono text-sm text-muted">
                                        {rate.rate_numerator.toLocaleString()} /{' '}
                                        {rate.rate_denominator.toLocaleString()}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(rate.effective_from)}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{rate.source}</td>
                                </tr>
                            ))}
                            {rates.data.length === 0 && (
                                <tr>
                                    <td colSpan={4} className="px-5 py-16 text-center">
                                        <Scale className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No rates match these filters</p>
                                        <p className="mt-1 text-sm text-muted">
                                            Add the first effective-dated ratio above.
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <Pagination rates={rates} />
            </div>
        </AppLayout>
    );
}
