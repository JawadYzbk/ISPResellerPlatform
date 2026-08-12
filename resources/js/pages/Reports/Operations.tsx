import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, AlertTriangle, ArrowLeft, Download, Radio, Router, Wrench } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { OperationsReport, PageProps } from '@/types';

type Props = PageProps & { report: OperationsReport };

const titleize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function StatusList({ values, t }: { values: Record<string, number>; t: (key: string) => string }) {
    return (
        <div className="mt-4 divide-y divide-line text-sm">
            {Object.entries(values).map(([status, total]) => (
                <div key={status} className="flex items-center justify-between py-3">
                    <span className="font-semibold">{t(titleize(status))}</span>
                    <span className="text-muted">{total}</span>
                </div>
            ))}
            {Object.keys(values).length === 0 && <p className="py-3 text-sm text-muted">{t('No records yet.')}</p>}
        </div>
    );
}

export default function OperationsReportPage({ report }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [from, setFrom] = useState(report.report_from);
    const [to, setTo] = useState(report.report_to);
    const query = `from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`;
    const applyPeriod = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/reports/operations', { from, to }, { preserveState: true, replace: true });
    };
    return (
        <AppLayout>
            <Head title={t('Operations report')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <Link
                        href="/dashboard"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                    >
                        <ArrowLeft size={16} />
                        {t('Back to overview')}
                    </Link>
                    <p className="eyebrow">{t('Operations')}</p>
                    <h1 className="page-title">{t('Network and field health')}</h1>
                    <p className="page-subtitle">{t('Current service, network, work-order and incident signals.')}</p>
                </div>
                <div className="flex gap-2">
                    <Link href="/reports/finance" className="button-quiet">
                        {t('Finance report')}
                    </Link>
                    <a href={`/reports/operations?format=csv&${query}`} className="button-quiet">
                        <Download size={15} />
                        {t('Download CSV')}
                    </a>
                    <a href={`/reports/operations?format=xlsx&${query}`} className="button-quiet">
                        <Download size={15} />
                        {t('Download XLSX')}
                    </a>
                </div>
            </div>
            <form onSubmit={applyPeriod} className="card mt-6 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-48">
                    <span className="field-label">Purchased from</span>
                    <input
                        className="field"
                        type="date"
                        value={from}
                        onChange={(event) => setFrom(event.target.value)}
                    />
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Purchased through</span>
                    <input className="field" type="date" value={to} onChange={(event) => setTo(event.target.value)} />
                </label>
                <button type="submit" className="button-primary">
                    Apply period
                </button>
            </form>
            <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="card p-5">
                    <Activity className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Expiring in 7 days')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.expiring_services}</p>
                </div>
                <div className="card p-5">
                    <Radio className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Active sessions')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.active_sessions}</p>
                </div>
                <div className="card p-5">
                    <Router className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Offline routers')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.offline_routers}</p>
                </div>
                <div className="card p-5">
                    <AlertTriangle className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Network drift')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.network_drift}</p>
                </div>
                <div className="card p-5">
                    <Wrench className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">{t('Failed commands')}</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.failed_commands}</p>
                </div>
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="card p-6">
                    <h2 className="section-title">{t('Services')}</h2>
                    <StatusList values={report.service_counts_by_status} t={t} />
                </div>
                <div className="card p-6">
                    <h2 className="section-title">{t('Work orders')}</h2>
                    <StatusList values={report.work_order_counts_by_status} t={t} />
                </div>
                <div className="card p-6">
                    <h2 className="section-title">{t('Incidents')}</h2>
                    <StatusList values={report.incident_counts_by_status} t={t} />
                </div>
            </div>
            <div className="card mt-6 p-6">
                <h2 className="section-title">{t('Low stock')}</h2>
                <div className="mt-4 divide-y divide-line text-sm">
                    {report.low_stock_items.map((item) => (
                        <div key={item.sku} className="flex items-center justify-between py-3">
                            <span>
                                <b>{item.sku}</b>
                                <span className="ms-2 text-muted">{item.name}</span>
                            </span>
                            <span className="text-muted">
                                {item.available_units} {t('available')} / {t('reorder at')} {item.reorder_level}
                            </span>
                        </div>
                    ))}
                    {report.low_stock_items.length === 0 && (
                        <p className="py-3 text-sm text-muted">{t('No low-stock items.')}</p>
                    )}
                </div>
            </div>
            <div className="card mt-6 p-6">
                <div className="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <div>
                        <h2 className="section-title">Supplier credential reconciliation</h2>
                        <p className="mt-1 text-sm text-muted">
                            Purchased batches imported from {report.supplier_credentials.from} through{' '}
                            {report.supplier_credentials.to}; live state is current.
                        </p>
                    </div>
                    <span className="text-xs text-muted">
                        Expiring within {report.supplier_credentials.expiring_days} days
                    </span>
                </div>
                <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {Object.entries(report.supplier_credentials.totals).map(([metric, total]) => (
                        <div key={metric} className="rounded-lg bg-sand/50 p-4">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                {titleize(metric)}
                            </p>
                            <p className="mt-1 font-display text-2xl font-semibold">{total}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-5 overflow-x-auto">
                    <table className="w-full min-w-[760px] text-start text-sm">
                        <thead>
                            <tr className="border-b border-line text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="py-3 text-start">Supplier</th>
                                <th className="py-3 text-end">Purchased</th>
                                <th className="py-3 text-end">Assigned</th>
                                <th className="py-3 text-end">Available</th>
                                <th className="py-3 text-end">Expiring</th>
                                <th className="py-3 text-end">Revoked / invalid</th>
                                <th className="py-3 text-end">Recorded cost</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {report.supplier_credentials.by_supplier.map((supplier) => (
                                <tr key={supplier.code ?? supplier.name}>
                                    <td className="py-3 font-semibold">
                                        {supplier.name}
                                        <span className="ms-2 text-xs text-muted">{supplier.code ?? '—'}</span>
                                    </td>
                                    <td className="py-3 text-end">{supplier.purchased}</td>
                                    <td className="py-3 text-end">{supplier.assigned}</td>
                                    <td className="py-3 text-end">{supplier.available}</td>
                                    <td className="py-3 text-end">{supplier.expiring}</td>
                                    <td className="py-3 text-end">{supplier.revoked_invalid}</td>
                                    <td className="py-3 text-end">
                                        {Object.entries(supplier.cost_by_currency).map(([currency, amount]) => (
                                            <span key={currency} className="ms-2 first:ms-0">
                                                {formatMoney(amount, currency)}
                                            </span>
                                        ))}
                                        {Object.keys(supplier.cost_by_currency).length === 0 && '—'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {report.supplier_credentials.by_supplier.length === 0 && (
                        <p className="py-4 text-sm text-muted">No supplier credential batches have been recorded.</p>
                    )}
                </div>
                {report.supplier_credentials.by_supplier.some((supplier) => supplier.contracts.length > 0) && (
                    <div className="mt-6 border-t border-line pt-5">
                        <h3 className="text-sm font-semibold">Cost by supplier / contract</h3>
                        <div className="mt-3 divide-y divide-line text-sm">
                            {report.supplier_credentials.by_supplier.flatMap((supplier) =>
                                supplier.contracts.map((contract) => (
                                    <div
                                        key={`${supplier.code ?? supplier.name}-${contract.reference ?? 'unspecified'}`}
                                        className="flex flex-col justify-between gap-2 py-3 sm:flex-row sm:items-center"
                                    >
                                        <span>
                                            <b>{supplier.name}</b>
                                            <span className="ms-2 text-muted">
                                                {contract.reference ?? 'Unspecified contract'}
                                            </span>
                                        </span>
                                        <span className="text-muted">
                                            {contract.purchased} purchased ·{' '}
                                            {Object.entries(contract.cost_by_currency)
                                                .map(([currency, amount]) => formatMoney(amount, currency))
                                                .join(' · ') || 'No cost recorded'}
                                        </span>
                                    </div>
                                )),
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
