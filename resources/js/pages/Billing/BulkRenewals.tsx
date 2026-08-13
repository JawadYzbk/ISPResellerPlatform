import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, FileText, RefreshCw, Search, WalletCards } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createIdempotencyKey } from '@/lib/idempotency';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type BulkRow = {
    service_id: string;
    username: string;
    expires_at: string | null;
    customer: { public_id: string | null; code: string | null; name: string | null };
    plan: string | null;
    price: { amount: number; currency: string } | null;
    status: 'ready' | 'open' | 'blocked';
    can_select: boolean;
    reason: string;
    open_invoice: { public_id: string; number: string; outstanding_amount: number } | null;
};

type RunRow = {
    service_id: string;
    username?: string;
    status: 'processed' | 'failed';
    invoice_id?: string;
    invoice_number?: string;
    message: string;
};

type BulkRun = {
    id: number;
    status: string;
    processed_count: number;
    failed_count: number;
    completed_at: string | null;
    idempotency_key: string | null;
    rows: RunRow[];
};

type Props = PageProps & {
    asOf: string;
    timezone: string;
    filters: { as_of: string; search: string };
    rows: BulkRow[];
    summary: { total: number; ready: number; open: number; blocked: number };
    lastRun: BulkRun | null;
};

function statusLabel(status: BulkRow['status']): string {
    return status === 'ready' ? 'Ready' : status === 'open' ? 'Open invoice' : 'Blocked';
}

function statusClass(status: BulkRow['status']): string {
    return status === 'ready'
        ? 'bg-emerald-50 text-emerald-700'
        : status === 'open'
          ? 'bg-sky-50 text-sky-700'
          : 'bg-amber-50 text-amber-800';
}

export default function BulkRenewalsPage({ asOf, timezone, filters, rows, summary, lastRun }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search);
    const [asOfDate, setAsOfDate] = useState(filters.as_of);
    const [retrySelected, setRetrySelected] = useState(false);
    const selectableIds = rows.filter((row) => row.can_select).map((row) => row.service_id);
    const failedBatchIds = lastRun?.rows.filter((row) => row.status === 'failed').map((row) => row.service_id) ?? [];
    const form = useForm({
        service_ids: lastRun?.failed_count && failedBatchIds.length > 0 ? failedBatchIds : selectableIds,
        idempotency_key:
            lastRun?.failed_count && lastRun.idempotency_key
                ? lastRun.idempotency_key
                : createIdempotencyKey('bulk-renewal'),
    });
    const selected = new Set(form.data.service_ids);
    const allSelected = selectableIds.length > 0 && selectableIds.every((id) => selected.has(id));

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/billing/bulk-renewals',
            { as_of: asOfDate || undefined, search: search || undefined },
            { replace: true },
        );
    };

    const toggleService = (serviceId: string) => {
        form.setData(
            'service_ids',
            selected.has(serviceId)
                ? form.data.service_ids.filter((id) => id !== serviceId)
                : [...form.data.service_ids, serviceId].sort(),
        );
        setRetrySelected(false);
    };

    const toggleAll = () => {
        form.setData('service_ids', allSelected ? [] : selectableIds);
        setRetrySelected(false);
    };

    const selectRetryBatch = () => {
        if (!lastRun || lastRun.rows.length === 0) return;
        form.setData('service_ids', failedBatchIds.sort());
        if (lastRun.idempotency_key) {
            form.setData('idempotency_key', lastRun.idempotency_key);
        }
        setRetrySelected(true);
    };

    return (
        <AppLayout>
            <Head title={t('Bulk renewals')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Billing operations')}</p>
                    <h1 className="page-title text-balance">{t('Bulk renewals')}</h1>
                    <p className="page-subtitle text-pretty">
                        Preview due services, issue renewal invoices in one controlled batch, and retry the same batch
                        safely when a row needs attention.
                    </p>
                </div>
                <WalletCards className="text-brand" size={24} />
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-56">
                    <span className="field-label">Due through</span>
                    <input
                        type="date"
                        className="field"
                        value={asOfDate}
                        onChange={(event) => setAsOfDate(event.target.value)}
                    />
                </label>
                <label className="block sm:min-w-72 sm:flex-1">
                    <span className="field-label">Search customer or service</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Username, customer name, or phone"
                        />
                    </div>
                </label>
                <button type="submit" className="button-secondary">
                    Refresh preview
                </button>
            </form>

            <div className="mt-6 grid gap-4 sm:grid-cols-4">
                {[
                    ['Due services', summary.total],
                    ['Ready', summary.ready],
                    ['Open invoice', summary.open],
                    ['Blocked', summary.blocked],
                ].map(([label, value]) => (
                    <div key={label} className="card p-5">
                        <p className="text-xs font-semibold uppercase text-muted">{label}</p>
                        <p className="mt-2 text-2xl font-semibold tabular-nums">{value}</p>
                    </div>
                ))}
            </div>

            {lastRun && (
                <section className="card mt-6 p-5">
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                        <div className="flex gap-3">
                            {lastRun.failed_count > 0 ? (
                                <AlertCircle className="mt-0.5 text-amber-700" size={18} />
                            ) : (
                                <CheckCircle2 className="mt-0.5 text-emerald-700" size={18} />
                            )}
                            <div>
                                <h2 className="text-sm font-semibold text-balance">Last batch outcome</h2>
                                <p className="mt-1 text-sm text-muted text-pretty">
                                    {lastRun.processed_count} processed and {lastRun.failed_count} failed ·{' '}
                                    {formatDate(lastRun.completed_at)}
                                </p>
                            </div>
                        </div>
                        {lastRun.failed_count > 0 && (
                            <button type="button" className="button-secondary" onClick={selectRetryBatch}>
                                <RefreshCw size={15} /> {retrySelected ? 'Failed rows selected' : 'Retry failed batch'}
                            </button>
                        )}
                    </div>
                    {lastRun.failed_count > 0 && (
                        <div className="mt-4 space-y-2 border-t border-line pt-4">
                            {lastRun.rows
                                .filter((row) => row.status === 'failed')
                                .map((row) => (
                                    <div
                                        key={row.service_id}
                                        className="flex flex-col gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span className="font-semibold">{row.username ?? row.service_id}</span>
                                        <span className="text-amber-800 text-pretty">{row.message}</span>
                                    </div>
                                ))}
                        </div>
                    )}
                </section>
            )}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/billing/bulk-renewals', { preserveScroll: true });
                }}
                className="card mt-6 overflow-hidden"
            >
                <div className="flex flex-col justify-between gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-center">
                    <div className="flex items-center gap-2">
                        <FileText size={17} className="text-brand" />
                        <h2 className="text-sm font-semibold text-balance">
                            {selected.size} of {selectableIds.length} selectable service(s) selected
                        </h2>
                    </div>
                    <button type="button" className="text-sm font-semibold text-brand" onClick={toggleAll}>
                        {allSelected ? 'Clear selection' : 'Select all ready rows'}
                    </button>
                </div>
                {form.errors.service_ids && <p className="field-error px-5 pt-4">{form.errors.service_ids}</p>}
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1040px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="w-12 px-5 py-3">
                                    <input
                                        type="checkbox"
                                        aria-label="Select all ready rows"
                                        checked={allSelected}
                                        onChange={toggleAll}
                                        disabled={selectableIds.length === 0}
                                    />
                                </th>
                                <th className="px-5 py-3 text-start">Customer</th>
                                <th className="px-5 py-3 text-start">Service</th>
                                <th className="px-5 py-3 text-start">Expires</th>
                                <th className="px-5 py-3 text-start">Renewal price</th>
                                <th className="px-5 py-3 text-start">Decision</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rows.map((row) => (
                                <tr key={row.service_id} className={row.can_select ? 'hover:bg-sand/30' : 'bg-sand/20'}>
                                    <td className="px-5 py-4">
                                        <input
                                            type="checkbox"
                                            aria-label={'Select ' + row.username}
                                            checked={selected.has(row.service_id)}
                                            disabled={!row.can_select}
                                            onChange={() => toggleService(row.service_id)}
                                        />
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">
                                            {row.customer.name ?? 'Unknown customer'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">{row.customer.code ?? 'No code'}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{row.username}</p>
                                        <p className="mt-1 text-xs text-muted">{row.plan ?? 'Plan unavailable'}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm tabular-nums text-muted">
                                        {formatDate(row.expires_at)}
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold tabular-nums">
                                        {row.price ? formatMoney(row.price.amount, row.price.currency) : '—'}
                                    </td>
                                    <td className="px-5 py-4">
                                        <span
                                            className={
                                                'rounded-full px-2.5 py-1 text-xs font-semibold ' +
                                                statusClass(row.status)
                                            }
                                        >
                                            {statusLabel(row.status)}
                                        </span>
                                        <p className="mt-2 max-w-xs text-xs text-muted text-pretty">{row.reason}</p>
                                    </td>
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <WalletCards className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold text-balance">No due services found</p>
                                        <p className="mt-1 text-sm text-muted text-pretty">
                                            Adjust the date or search to find services ready for renewal.
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-col justify-between gap-3 border-t border-line px-5 py-4 sm:flex-row sm:items-center">
                    <p className="text-xs text-muted text-pretty">
                        Preview as of {asOf} ({timezone}). Open invoices are reused safely.
                    </p>
                    <button type="submit" className="button-primary" disabled={form.processing || selected.size === 0}>
                        <WalletCards size={15} />{' '}
                        {retrySelected ? 'Retry selected renewals' : 'Issue selected renewals'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}
