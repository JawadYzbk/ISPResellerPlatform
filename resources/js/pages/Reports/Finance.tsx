import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Receipt, Wallet } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import type { FinanceReport, PageProps } from '@/types';

type Props = PageProps & { report: FinanceReport };

const firstAmount = (amounts: Record<string, number>) => {
    const currency = Object.keys(amounts)[0] ?? 'USD';
    return formatMoney(amounts[currency] ?? 0, currency);
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
            <div>
                <p className="eyebrow">Finance</p>
                <h1 className="page-title">Collections and revenue</h1>
                <p className="page-subtitle">
                    Issued invoices and posted payments for {report.from} through {report.to}.
                </p>
            </div>
            <div className="mt-8 grid gap-4 md:grid-cols-3">
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
                    <BarChart3 className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Customer balance</p>
                    <p className="mt-1 font-display text-2xl font-semibold">
                        {firstAmount(report.customer_balances_by_currency)}
                    </p>
                    <p className="mt-1 text-sm text-muted">Ledger cache by currency</p>
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
        </AppLayout>
    );
}
