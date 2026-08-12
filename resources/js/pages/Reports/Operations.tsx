import { Head, Link, usePage } from '@inertiajs/react';
import { Activity, AlertTriangle, ArrowLeft, Download, Radio, Router, Wrench } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
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
                    <a href="/reports/operations?format=csv" className="button-quiet">
                        <Download size={15} />
                        {t('Download CSV')}
                    </a>
                    <a href="/reports/operations?format=xlsx" className="button-quiet">
                        <Download size={15} />
                        {t('Download XLSX')}
                    </a>
                </div>
            </div>
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
        </AppLayout>
    );
}
