import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Download, Receipt, TrendingUp, Wallet } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatMoney, keysOrEmpty } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { FinanceReport, PageProps } from '@/types';

type Props = PageProps & { report: FinanceReport };

const formatAmounts = (amounts: Record<string, number | null>) => {
    const entries = entriesOrEmpty(amounts);

    return entries.length === 0
        ? '—'
        : entries
              .map(([currency, amount]) => `${currency} ${amount === null ? '—' : formatMoney(amount, currency)}`)
              .join(' · ');
};

const formatRates = (rates: Record<string, number | null>) => {
    const entries = entriesOrEmpty(rates);

    return entries.length === 0
        ? '—'
        : entries.map(([currency, rate]) => `${currency} ${rate === null ? '—' : `${rate.toFixed(2)}%`}`).join(' · ');
};

const formatBytes = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 ** 2) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 ** 3) return `${(bytes / 1024 ** 2).toFixed(1)} MB`;

    return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
};

export default function FinanceReportPage({ report }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [from, setFrom] = useState(report.from);
    const [to, setTo] = useState(report.to);
    const query = `from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;

    const applyPeriod = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/reports/finance', { from, to }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={t('Finance report')} />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} />
                {t('Back to overview')}
            </Link>
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Finance')}</p>
                    <h1 className="page-title">{t('Collections and revenue')}</h1>
                    <p className="page-subtitle">
                        {t('Issued invoices and posted payments for')} {report.from} {t('through')} {report.to}.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Link href="/reports/operations" className="button-quiet">
                        {t('Operations report')}
                    </Link>
                    <Link href="/reports/supplier-payables" className="button-quiet">
                        Supplier payables
                    </Link>
                    <a href={`/reports/finance?format=csv&${query}`} className="button-quiet">
                        <Download size={15} />
                        {t('Download CSV')}
                    </a>
                    <a href={`/reports/finance?format=xlsx&${query}`} className="button-quiet">
                        <Download size={15} />
                        {t('Download XLSX')}
                    </a>
                </div>
            </div>
            <form onSubmit={applyPeriod} className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('From')}</span>
                    <input
                        className="field"
                        type="date"
                        value={from}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('To')}</span>
                    <input className="field" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply period')}
                </button>
            </form>
            <div className="mt-8 grid gap-4 md:grid-cols-4">
                <div className="card p-5">
                    <Receipt className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Issued invoices')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.invoice_count}</p>
                    <p className="mt-1 text-sm text-muted">{formatAmounts(report.invoiced_by_currency)}</p>
                </div>
                <div className="card p-5">
                    <Wallet className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Posted payments')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.payment_count}</p>
                    <p className="mt-1 text-sm text-muted">{formatAmounts(report.collected_by_currency)}</p>
                </div>
                <div className="card p-5">
                    <TrendingUp className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Collection rate')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">
                        {formatRates(report.collection_rate_by_currency)}
                    </p>
                    <p className="mt-1 text-sm text-muted">{t('Collected against invoiced')}</p>
                </div>
                <div className="card p-5">
                    <BarChart3 className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Open accounts receivable')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">
                        {formatAmounts(report.outstanding_by_currency)}
                    </p>
                    <p className="mt-1 text-sm text-muted">{t('Issued invoices less allocations')}</p>
                </div>
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-[1.25fr_0.75fr]">
                <div className="card p-6">
                    <h2 className="section-title">{t('Collection trend')}</h2>
                    <p className="mt-1 text-sm text-muted">
                        {t('Daily issued and collected amounts for the selected period')}
                    </p>
                    <div className="mt-4 overflow-x-auto">
                        <table className="w-full min-w-[520px] text-left text-sm">
                            <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                                <tr>
                                    <th className="pb-3">{t('Date')}</th>
                                    <th className="pb-3">{t('Invoiced')}</th>
                                    <th className="pb-3">{t('Collected')}</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-line">
                                {report.collection_trend.map((day) => (
                                    <tr key={day.date}>
                                        <td className="py-3 font-semibold">{day.date}</td>
                                        <td className="py-3">{formatAmounts(day.invoiced_by_currency)}</td>
                                        <td className="py-3">{formatAmounts(day.collected_by_currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {report.collection_trend.length === 0 && (
                            <p className="py-3 text-sm text-muted">{t('No collection activity in this period')}</p>
                        )}
                    </div>
                </div>
                <div className="card p-6">
                    <h2 className="section-title">{t('Cash reconciliation')}</h2>
                    <p className="mt-1 text-sm text-muted">{t('Closed collector shifts and declared cash variance')}</p>
                    <div className="mt-5 grid grid-cols-2 gap-3">
                        <div className="rounded-lg bg-sand/50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                {t('Closed shifts')}
                            </p>
                            <p className="mt-2 font-display text-2xl font-semibold">
                                {report.cash_reconciliation.closed_shift_count}
                            </p>
                        </div>
                        <div className="rounded-lg bg-sand/50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                {t('With variance')}
                            </p>
                            <p className="mt-2 font-display text-2xl font-semibold">
                                {report.cash_reconciliation.variance_shift_count}
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 divide-y divide-line text-sm">
                        {entriesOrEmpty(report.cash_reconciliation.variance_by_currency).map(([currency, amount]) => (
                            <div key={currency} className="flex items-center justify-between py-3">
                                <span className="font-semibold">{currency}</span>
                                <span className="text-muted">{formatMoney(amount, currency)}</span>
                            </div>
                        ))}
                    </div>
                    {keysOrEmpty(report.cash_reconciliation.variance_by_currency).length === 0 && (
                        <p className="mt-4 text-sm text-muted">{t('No cash variance recorded')}</p>
                    )}
                </div>
            </div>
            <div className="mt-6 card p-6">
                <h2 className="section-title">{t('Currency detail')}</h2>
                <div className="mt-4 divide-y divide-line text-sm">
                    {keysOrEmpty({ ...report.invoiced_by_currency, ...report.collected_by_currency }).map(
                        (currency) => (
                            <div key={currency} className="flex items-center justify-between py-3">
                                <span className="font-semibold">{currency}</span>
                                <span className="text-muted">
                                    {t('Invoiced')} {formatMoney(report.invoiced_by_currency[currency] ?? 0, currency)}{' '}
                                    ·{t('Collected')}{' '}
                                    {formatMoney(report.collected_by_currency[currency] ?? 0, currency)}
                                </span>
                            </div>
                        ),
                    )}
                </div>
            </div>
            <div className="mt-6 card p-6">
                <h2 className="section-title">{t('Accounts receivable aging')}</h2>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                            <tr>
                                <th className="pb-3">{t('Currency')}</th>
                                <th className="pb-3">{t('Current')}</th>
                                <th className="pb-3">{t('1–30 days')}</th>
                                <th className="pb-3">{t('31–60 days')}</th>
                                <th className="pb-3">{t('61–90 days')}</th>
                                <th className="pb-3">{t('90+ days')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {entriesOrEmpty(report.aging_by_currency).map(([currency, buckets]) => (
                                <tr key={currency}>
                                    <td className="py-3 font-semibold">{currency}</td>
                                    <td className="py-3">{formatMoney(buckets.current, currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['1_30'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['31_60'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['61_90'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['90_plus'], currency)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
            <div className="mt-6 card p-6">
                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <div>
                        <h2 className="section-title">Supplier payables</h2>
                        <p className="mt-1 text-sm text-muted">
                            Open supplier bills and payments through {report.to}; aging uses each bill period end.
                        </p>
                    </div>
                    <p className="text-sm text-muted">
                        {report.supplier_payables.bill_count} bill(s) · {report.supplier_payables.payment_count}{' '}
                        payment(s)
                    </p>
                </div>
                <div className="mt-4 grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg bg-sand/50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">Billed</p>
                        <p className="mt-1 text-sm text-muted">
                            {formatAmounts(report.supplier_payables.billed_by_currency)}
                        </p>
                    </div>
                    <div className="rounded-lg bg-sand/50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">Paid</p>
                        <p className="mt-1 text-sm text-muted">
                            {formatAmounts(report.supplier_payables.paid_by_currency)}
                        </p>
                    </div>
                    <div className="rounded-lg bg-sand/50 p-4">
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">Outstanding</p>
                        <p className="mt-1 text-sm text-muted">
                            {formatAmounts(report.supplier_payables.outstanding_by_currency)}
                        </p>
                    </div>
                </div>
                <div className="mt-5 overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                            <tr>
                                <th className="pb-3">Currency</th>
                                <th className="pb-3">Current</th>
                                <th className="pb-3">1–30 days</th>
                                <th className="pb-3">31–60 days</th>
                                <th className="pb-3">61–90 days</th>
                                <th className="pb-3">90+ days</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {entriesOrEmpty(report.supplier_payables.aging_by_currency).map(([currency, buckets]) => (
                                <tr key={currency}>
                                    <td className="py-3 font-semibold">{currency}</td>
                                    <td className="py-3">{formatMoney(buckets.current, currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['1_30'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['31_60'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['61_90'], currency)}</td>
                                    <td className="py-3">{formatMoney(buckets['90_plus'], currency)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {keysOrEmpty(report.supplier_payables.aging_by_currency).length === 0 && (
                        <p className="py-3 text-sm text-muted">No supplier balances are outstanding.</p>
                    )}
                </div>
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <h2 className="section-title">{t('Revenue by plan')}</h2>
                    <div className="mt-4 divide-y divide-line text-sm">
                        {entriesOrEmpty(report.revenue_by_plan).map(([plan, amounts]) => (
                            <div key={plan} className="flex items-center justify-between py-3">
                                <span className="font-semibold">{plan}</span>
                                <span className="text-muted">
                                    {entriesOrEmpty(amounts)
                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                        .join(' · ')}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="card p-6">
                    <h2 className="section-title">{t('Top usage')}</h2>
                    <div className="mt-4 divide-y divide-line text-sm">
                        {report.top_usage.map((usage) => (
                            <div
                                key={usage.service_id ?? usage.username}
                                className="flex items-center justify-between py-3"
                            >
                                <span className="font-semibold">{usage.username ?? t('Unknown service')}</span>
                                <span className="text-muted">{formatBytes(usage.total_octets)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <div className="card p-6">
                    <h2 className="section-title">{t('Margin by POP')}</h2>
                    <div className="mt-4 divide-y divide-line text-sm">
                        {entriesOrEmpty(report.margin_by_pop).map(([pop, amounts]) => (
                            <div key={pop} className="py-3">
                                <div className="flex items-center justify-between">
                                    <span className="font-semibold">{pop}</span>
                                    <span className="font-semibold">
                                        {entriesOrEmpty(amounts.margin_by_currency)
                                            .map(([currency, amount]) => formatMoney(amount, currency))
                                            .join(' · ')}
                                    </span>
                                </div>
                                <p className="mt-1 text-xs text-muted">
                                    {t('Revenue')}{' '}
                                    {entriesOrEmpty(amounts.revenue_by_currency)
                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                        .join(' · ')}{' '}
                                    · {t('Upstream cost')}{' '}
                                    {entriesOrEmpty(amounts.upstream_cost_by_currency)
                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                        .join(' · ') || '—'}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>
                <div className="card p-6">
                    <h2 className="section-title">{t('Collector performance')}</h2>
                    <div className="mt-4 divide-y divide-line text-sm">
                        {report.collector_performance.map((collector) => (
                            <div key={collector.collector} className="flex items-center justify-between py-3">
                                <span>
                                    <span className="block font-semibold">{collector.collector}</span>
                                    <span className="text-xs text-muted">
                                        {collector.payment_count} {t('payment(s)')}
                                    </span>
                                </span>
                                <span className="text-muted">
                                    {entriesOrEmpty(collector.totals_by_currency)
                                        .map(([currency, amount]) => formatMoney(amount, currency))
                                        .join(' · ')}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
            <div className="mt-6 card p-6">
                <div className="grid gap-6 sm:grid-cols-3">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Retention')}</p>
                        <p className="mt-2 font-display text-2xl font-semibold">
                            {report.retention_by_period.retention_rate_percent === null
                                ? '—'
                                : `${report.retention_by_period.retention_rate_percent.toFixed(2)}%`}
                        </p>
                        <p className="mt-1 text-xs text-muted">{t('Based on period-start services')}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('Tax recorded')}</p>
                        <p className="mt-2 font-display text-2xl font-semibold">
                            {formatAmounts(report.tax_by_currency)}
                        </p>
                        <p className="mt-1 text-xs text-muted">{t('Issued invoices in the selected period')}</p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-muted">{t('ARPU')}</p>
                        <p className="mt-2 font-display text-2xl font-semibold">
                            {formatAmounts(report.arpu_by_currency)}
                        </p>
                        <p className="mt-1 text-xs text-muted">{t('Posted collections per active customer')}</p>
                    </div>
                </div>
            </div>
            <div className="mt-6 text-sm text-muted">
                {report.active_customer_count} {t('active customers')} · {report.churned_services}{' '}
                {t('churned services in the selected period')}
            </div>
        </AppLayout>
    );
}
