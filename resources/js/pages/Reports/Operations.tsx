import { Head, Link } from '@inertiajs/react';
import { Activity, AlertTriangle, ArrowLeft, Download, Radio, Router, Wrench } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import type { OperationsReport, PageProps } from '@/types';

type Props = PageProps & { report: OperationsReport };

const titleize = (value: string) => value.replaceAll('_', ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());

function StatusList({ values }: { values: Record<string, number> }) {
    return (
        <div className="mt-4 divide-y divide-line text-sm">
            {Object.entries(values).map(([status, total]) => (
                <div key={status} className="flex items-center justify-between py-3">
                    <span className="font-semibold">{titleize(status)}</span>
                    <span className="text-muted">{total}</span>
                </div>
            ))}
            {Object.keys(values).length === 0 && <p className="py-3 text-sm text-muted">No records yet.</p>}
        </div>
    );
}

export default function OperationsReportPage({ report }: Props) {
    return (
        <AppLayout>
            <Head title="Operations report" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <Link
                        href="/dashboard"
                        className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                    >
                        <ArrowLeft size={16} />
                        Back to overview
                    </Link>
                    <p className="eyebrow">Operations</p>
                    <h1 className="page-title">Network and field health</h1>
                    <p className="page-subtitle">Current service, network, work-order and incident signals.</p>
                </div>
                <div className="flex gap-2">
                    <Link href="/reports/finance" className="button-quiet">
                        Finance report
                    </Link>
                    <a href="/reports/operations?format=csv" className="button-quiet">
                        <Download size={15} />
                        Download CSV
                    </a>
                    <a href="/reports/operations?format=xlsx" className="button-quiet">
                        <Download size={15} />
                        Download XLSX
                    </a>
                </div>
            </div>
            <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div className="card p-5">
                    <Activity className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Expiring in 7 days</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.expiring_services}</p>
                </div>
                <div className="card p-5">
                    <Radio className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Active sessions</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.active_sessions}</p>
                </div>
                <div className="card p-5">
                    <Router className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Offline routers</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.offline_routers}</p>
                </div>
                <div className="card p-5">
                    <AlertTriangle className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Network drift</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.network_drift}</p>
                </div>
                <div className="card p-5">
                    <Wrench className="text-brand" size={20} />
                    <p className="mt-4 text-sm text-muted">Failed commands</p>
                    <p className="mt-1 font-display text-2xl font-semibold">{report.failed_commands}</p>
                </div>
            </div>
            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="card p-6">
                    <h2 className="section-title">Services</h2>
                    <StatusList values={report.service_counts_by_status} />
                </div>
                <div className="card p-6">
                    <h2 className="section-title">Work orders</h2>
                    <StatusList values={report.work_order_counts_by_status} />
                </div>
                <div className="card p-6">
                    <h2 className="section-title">Incidents</h2>
                    <StatusList values={report.incident_counts_by_status} />
                </div>
            </div>
            <div className="card mt-6 p-6">
                <h2 className="section-title">Low stock</h2>
                <div className="mt-4 divide-y divide-line text-sm">
                    {report.low_stock_items.map((item) => (
                        <div key={item.sku} className="flex items-center justify-between py-3">
                            <span>
                                <b>{item.sku}</b>
                                <span className="ms-2 text-muted">{item.name}</span>
                            </span>
                            <span className="text-muted">
                                {item.available_units} available / reorder at {item.reorder_level}
                            </span>
                        </div>
                    ))}
                    {report.low_stock_items.length === 0 && (
                        <p className="py-3 text-sm text-muted">No low-stock items.</p>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
