import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, CheckCircle2, Download, ExternalLink, FileText, Wallet } from 'lucide-react';
import { useState } from 'react';

import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, SupplierPayablesReport } from '@/types';

type SupplierOption = { id: number; name: string; code: string };
type Props = PageProps & { report: SupplierPayablesReport; suppliers: SupplierOption[] };
type AgingBuckets = SupplierPayablesReport['summary']['aging_by_currency'][string];

const bucketLabels: Record<keyof AgingBuckets, string> = {
    current: 'Current',
    '1_30': '1–30 days',
    '31_60': '31–60 days',
    '61_90': '61–90 days',
    '90_plus': '90+ days',
};

const formatAmounts = (amounts: Record<string, number>) =>
    entriesOrEmpty(amounts)
        .map(([currency, amount]) => formatMoney(amount, currency))
        .join(' · ') || '—';

function AgingTable({ values, t }: { values: Record<string, AgingBuckets>; t: (key: string) => string }) {
    const rows = entriesOrEmpty(values);

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-sm">
                <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                    <tr>
                        <th className="pb-3">{t('Currency')}</th>
                        {Object.keys(bucketLabels).map((bucket) => (
                            <th key={bucket} className="pb-3">
                                {bucketLabels[bucket as keyof AgingBuckets]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {rows.map(([currency, buckets]) => (
                        <tr key={currency}>
                            <td className="py-3 font-semibold">{currency}</td>
                            {Object.keys(bucketLabels).map((bucket) => (
                                <td key={bucket} className="py-3">
                                    {formatMoney(buckets[bucket as keyof AgingBuckets], currency)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
            {rows.length === 0 && <p className="py-3 text-sm text-muted">{t('supplier_payables.no_balances')}</p>}
        </div>
    );
}

export default function SupplierPayablesPage({ report, suppliers }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [asOf, setAsOf] = useState(report.as_of);
    const [supplierId, setSupplierId] = useState(report.supplier_id ? String(report.supplier_id) : '');
    const [includeSettled, setIncludeSettled] = useState(report.include_settled);
    const exportQuery = [
        `as_of=${encodeURIComponent(report.as_of)}`,
        report.supplier_id === null ? null : `supplier_id=${report.supplier_id}`,
        report.include_settled ? 'include_settled=1' : null,
    ]
        .filter((value): value is string => value !== null)
        .join('&');

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/reports/supplier-payables',
            {
                as_of: asOf,
                supplier_id: supplierId || undefined,
                include_settled: includeSettled ? 1 : undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('supplier_payables.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <Link
                        href="/reports/finance"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                    >
                        <ArrowLeft size={16} /> {t('Back to finance')}
                    </Link>
                    <p className="eyebrow">{t('supplier_payables.eyebrow')}</p>
                    <h1 className="page-title">{t('supplier_payables.title')}</h1>
                    <p className="page-subtitle">{t('supplier_payables.subtitle')}</p>
                </div>
                <div className="flex gap-2">
                    <Link href="/operations/suppliers" className="button-primary">
                        <Wallet size={15} /> {t('supplier_payables.manage_settlements')}
                    </Link>
                    <a href={`/reports/supplier-payables?format=csv&${exportQuery}`} className="button-quiet">
                        <Download size={15} /> {t('Export')}
                    </a>
                </div>
            </div>

            <form
                onSubmit={applyFilters}
                className="card mt-6 grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end"
            >
                <label>
                    <span className="field-label">{t('supplier_payables.as_of')}</span>
                    <input
                        className="field"
                        type="date"
                        value={asOf}
                        onChange={(event) => setAsOf(event.target.value)}
                    />
                </label>
                <label>
                    <span className="field-label">{t('Supplier')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={supplierId}
                        onChange={(event) => setSupplierId(event.target.value)}
                    >
                        <option value="">{t('supplier_payables.all_suppliers')}</option>
                        {suppliers.map((supplier) => (
                            <option key={supplier.id} value={supplier.id}>
                                {supplier.name} · {supplier.code}
                            </option>
                        ))}
                    </ResponsiveSelect>
                </label>
                <div className="flex flex-wrap items-center gap-3">
                    <label className="flex items-center gap-2 text-sm text-muted">
                        <input
                            type="checkbox"
                            checked={includeSettled}
                            onChange={(event) => setIncludeSettled(event.target.checked)}
                        />
                        {t('supplier_payables.include_settled')}
                    </label>
                    <button type="submit" className="button-primary">
                        {t('Apply filters')}
                    </button>
                </div>
            </form>

            <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="card p-5">
                    <FileText className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('supplier_payables.bills_in_view')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.summary.bill_count}</p>
                    <p className="mt-1 text-xs text-muted">
                        {report.summary.open_bill_count} {t('supplier_payables.still_open')}
                    </p>
                </div>
                <div className="card p-5">
                    <Wallet className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Outstanding')}</p>
                    <p className="mt-1 text-sm font-semibold">
                        {formatAmounts(report.summary.outstanding_by_currency)}
                    </p>
                </div>
                <div className="card p-5">
                    <CheckCircle2 className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('supplier_payables.paid_by_cutoff')}</p>
                    <p className="mt-1 text-sm font-semibold">{formatAmounts(report.summary.paid_by_currency)}</p>
                </div>
                <div className="card p-5">
                    <CalendarClock className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('supplier_payables.cutoff')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.as_of}</p>
                </div>
            </div>

            <div className="card mt-6 p-6">
                <h2 className="section-title">{t('supplier_payables.aging_by_currency')}</h2>
                <p className="mt-1 text-sm text-muted">{t('supplier_payables.aging_description')}</p>
                <div className="mt-5">
                    <AgingTable values={report.summary.aging_by_currency} t={t} />
                </div>
            </div>

            <div className="card mt-6 p-6">
                <h2 className="section-title">{t('supplier_payables.by_supplier')}</h2>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[680px] text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                            <tr>
                                <th className="pb-3">{t('Supplier')}</th>
                                <th className="pb-3">{t('supplier_payables.bills')}</th>
                                <th className="pb-3">{t('Outstanding')}</th>
                                <th className="pb-3">{t('Aging')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {report.by_supplier.map((supplier) => (
                                <tr key={supplier.supplier_id}>
                                    <td className="py-3">
                                        <p className="font-semibold">{supplier.supplier_name}</p>
                                        <p className="text-xs text-muted">{supplier.supplier_code ?? '—'}</p>
                                    </td>
                                    <td className="py-3">{supplier.bill_count}</td>
                                    <td className="py-3">{formatAmounts(supplier.outstanding_by_currency)}</td>
                                    <td className="py-3 text-xs text-muted">
                                        {entriesOrEmpty(supplier.aging_by_currency)
                                            .map(([currency, buckets]) =>
                                                [
                                                    currency,
                                                    formatMoney(buckets['90_plus'] + buckets['61_90'], currency),
                                                    t('supplier_payables.overdue'),
                                                ].join(': '),
                                            )
                                            .join(' · ') || t('supplier_payables.current_only')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {report.by_supplier.length === 0 && (
                        <p className="py-3 text-sm text-muted">{t('supplier_payables.no_suppliers')}</p>
                    )}
                </div>
            </div>

            <div className="card mt-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="section-title">{t('supplier_payables.settlement_queue')}</h2>
                        <p className="mt-1 text-sm text-muted">{t('supplier_payables.settlement_description')}</p>
                    </div>
                    <ExternalLink className="shrink-0 text-muted" size={18} />
                </div>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="text-xs uppercase tracking-[0.14em] text-muted">
                            <tr>
                                <th className="pb-3">{t('Due')}</th>
                                <th className="pb-3">{t('supplier_payables.supplier_reference')}</th>
                                <th className="pb-3">{t('Status')}</th>
                                <th className="pb-3">{t('supplier_payables.billed')}</th>
                                <th className="pb-3">{t('Paid')}</th>
                                <th className="pb-3">{t('Balance')}</th>
                                <th className="pb-3 text-end">{t('Action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {report.bills.map((bill) => (
                                <tr key={bill.id}>
                                    <td className="py-3">
                                        <p>{bill.period_end}</p>
                                        <p className="text-xs text-muted">
                                            {bill.age_days > 0
                                                ? [bill.age_days, t('supplier_payables.days_old')].join(' ')
                                                : t('supplier_payables.current')}
                                        </p>
                                    </td>
                                    <td className="py-3">
                                        <p className="font-semibold">{bill.supplier_name}</p>
                                        <p className="text-xs text-muted">
                                            {bill.reference} · {bill.supplier_code ?? '—'}
                                        </p>
                                    </td>
                                    <td className="py-3">
                                        <span className="status-badge">{enumLabel(bill.status, t)}</span>
                                    </td>
                                    <td className="py-3">{formatMoney(bill.amount, bill.currency)}</td>
                                    <td className="py-3">{formatMoney(bill.paid_amount, bill.currency)}</td>
                                    <td className="py-3 font-semibold">
                                        {formatMoney(bill.outstanding_amount, bill.currency)}
                                    </td>
                                    <td className="py-3 text-end">
                                        <Link
                                            href="/operations/suppliers"
                                            className="text-xs font-semibold text-brand hover:underline"
                                        >
                                            {t('supplier_payables.open_settlement')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {report.bills.length === 0 && (
                        <p className="py-3 text-sm text-muted">{t('supplier_payables.no_bills')}</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
