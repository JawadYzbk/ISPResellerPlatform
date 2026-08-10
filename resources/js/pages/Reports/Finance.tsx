import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Download, Receipt, TrendingUp, Wallet } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import type { FinanceReport, PageProps } from '@/types';

type Props = PageProps & { report: FinanceReport };

const firstAmount = (amounts: Record<string, number>) => {
    const currency = Object.keys(amounts)[0] ?? 'USD';
    return formatMoney(amounts[currency] ?? 0, currency);
};

const firstRate = (rates: Record<string, number | null>) => {
    const currency = Object.keys(rates)[0];
    const rate = currency === undefined ? null : rates[currency];

    return rate === null || rate === undefined ? '—' : `${rate.toFixed(2)}%`;
};

export default function FinanceReportPage({ report }: Props) {
    return (
        <AppLayout>
            <Head title="Finance report" />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} />
                Back to overview
            </Link>
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Finance</p>
                    <h1 className="page-title">Collections and revenue</h1>
                    <p className="page-subtitle">
                        Issued invoices and posted payments for {report.from} through {report.to}.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Link href="/reports/operations" className="button-quiet">
                        Operations report
                    </Link>
                    <a href="/reports/finance?format=csv" className="button-quiet">
                        <Download size={15} />
                        Download CSV
                    </a>
                </div>
            </div>
            <div className="mt-8 grid gap-4 md:grid-cols-4">
                <div className="card p-5">
                    <Receipt className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Issued invoices</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.invoice_count}</p>
                    <p className="mt-1 text-sm text-muted">{firstAmount(report.invoiced_by_currency)}</p>
                </div>
                <div className="card p-5">
                    <Wallet className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Posted payments</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.payment_count}</p>
                    <p className="mt-1 text-sm text-muted">{firstAmount(report.collected_by_currency)}</p>
                </div>
                <div className="card p-5">
                    <TrendingUp className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Collection rate</p>
                    <p className="mt-1 font-display text-2xl font-semibold">
                        {firstRate(report.collection_rate_by_currency)}
                    </p>
                    <p className="mt-1 text-sm text-muted">Collected against invoiced</p>
                </div>
                <div className="card p-5">
                    <BarChart3 className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Open accounts receivable</p>
                    <p className="mt-1 font-display text-2xl font-semibold">
                        {firstAmount(report.outstanding_by_currency)}
                    </p>
                    <p className="mt-1 text-sm text-muted">Issued invoices less allocations</p>
                </div>
            </div>
            <div className="mt-6 card p-6">
                <h2 className="section-title">Currency detail</h2>
                <div className="mt-4 divide-y divide-line text-sm">
                    {Object.keys({ ...report.invoiced_by_currency, ...report.collected_by_currency }).map(
                        (currency) => (
                            <div key={currency} className="flex items-center justify-between py-3">
                                <span className="font-semibold">{currency}</span>
                                <span className="text-muted">
                                    Invoiced {formatMoney(report.invoiced_by_currency[currency] ?? 0, currency)} ·
                                    Collected {formatMoney(report.collected_by_currency[currency] ?? 0, currency)}
                                </span>
                            </div>
                        ),
                    )}
                </div>
            </div>
            <div className="mt-6 card p-6">
                <h2 className="section-title">Accounts receivable aging</h2>
                <div className="mt-4 overflow-x-auto">
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
                            {Object.entries(report.aging_by_currency).map(([currency, buckets]) => (
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
        </AppLayout>
    );
}
