import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileUp, RotateCcw, Upload } from 'lucide-react';
import { useMemo } from 'react';

import AppLayout from '@/layouts/AppLayout';
import type { ImportBatchReportRow, ImportBatchResult, PageProps } from '@/types';

type ImportType = {
    value: string;
    label: string;
    columns: string;
};

type Router = {
    public_id: string;
    name: string;
    host: string;
};

type Batch = {
    id: string;
    type: string;
    filename: string;
    status: string;
    total_rows: number;
    successful_rows: number;
    failed_rows: number;
    created_at: string | null;
    completed_at: string | null;
    rolled_back_at: string | null;
};

type Props = {
    types: ImportType[];
    routers: Router[];
    batches: Batch[];
};

type PagePropsWithImportFlash = PageProps & {
    flash: PageProps['flash'] & { importResult?: ImportBatchResult };
};

const labelForType = (type: string, types: ImportType[]) => types.find((item) => item.value === type)?.label ?? type;

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : '—';

const rowErrorText = (row: ImportBatchReportRow) => (row.errors.length > 0 ? row.errors.join('; ') : 'Ready to import');

export default function Imports({ types, routers, batches }: Props) {
    const { flash } = usePage<PagePropsWithImportFlash>().props;
    const initialType = types[0]?.value ?? '';
    const form = useForm({
        type: initialType,
        file: null as File | null,
        router_public_id: '',
        dry_run: true,
    });
    const selectedType = useMemo(() => types.find((item) => item.value === form.data.type), [form.data.type, types]);
    const isRouterDiscovery = form.data.type === 'router_subscribers';
    const result = flash.importResult;
    const canRollback = (type: string) => type !== 'router_subscribers' && types.some((item) => item.value === type);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/operations/imports', {
            forceFormData: !isRouterDiscovery,
            onSuccess: () => form.reset('file'),
        });
    };

    return (
        <AppLayout>
            <Head title="Imports" />
            <div>
                <p className="eyebrow">Data operations</p>
                <h1 className="page-title">Safe imports</h1>
                <p className="page-subtitle">
                    Preview a file, resolve rejected rows, then commit only when the report is ready.
                </p>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]">
                <form onSubmit={submit} className="card space-y-6 p-6">
                    <div className="flex items-start gap-3">
                        <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                            <FileUp size={19} />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold">Start an import</h2>
                            <p className="mt-1 text-sm leading-6 text-muted">
                                Files are validated inside the current tenant boundary. Secrets and internal IDs are
                                removed from the report.
                            </p>
                        </div>
                    </div>

                    <label>
                        <span className="field-label">Import type</span>
                        <select
                            className="field"
                            value={form.data.type}
                            onChange={(event) => form.setData('type', event.target.value)}
                        >
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                        {form.errors.type && <p className="field-error">{form.errors.type}</p>}
                    </label>

                    {isRouterDiscovery ? (
                        <label>
                            <span className="field-label">Router</span>
                            <select
                                className="field"
                                value={form.data.router_public_id}
                                onChange={(event) => form.setData('router_public_id', event.target.value)}
                            >
                                <option value="">Select a router</option>
                                {routers.map((router) => (
                                    <option key={router.public_id} value={router.public_id}>
                                        {router.name} · {router.host}
                                    </option>
                                ))}
                            </select>
                            {form.errors.router_public_id && (
                                <p className="field-error">{form.errors.router_public_id}</p>
                            )}
                            <p className="mt-2 text-xs leading-5 text-muted">
                                Discovery reads PPP secrets from the router and records a redacted match report. It
                                never changes the router or services.
                            </p>
                        </label>
                    ) : (
                        <label>
                            <span className="field-label">CSV or XLSX file</span>
                            <input
                                className="field file:me-3 file:rounded-md file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand"
                                type="file"
                                accept=".csv,.txt,.xlsx"
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.file && <p className="field-error">{form.errors.file}</p>}
                            <p className="mt-2 text-xs leading-5 text-muted">
                                Required columns: {selectedType?.columns ?? 'Choose an import type.'}
                            </p>
                        </label>
                    )}

                    <label className="flex items-start gap-3 rounded-xl border border-line bg-sand/40 p-4">
                        <input
                            className="mt-1"
                            type="checkbox"
                            checked={form.data.dry_run}
                            onChange={(event) => form.setData('dry_run', event.target.checked)}
                        />
                        <span>
                            <span className="block text-sm font-semibold">Preview only</span>
                            <span className="mt-1 block text-xs leading-5 text-muted">
                                No records are created. Upload the corrected file again with this unchecked to commit
                                valid rows.
                            </span>
                        </span>
                    </label>

                    <button
                        type="submit"
                        className="button-primary w-full justify-center"
                        disabled={
                            form.processing ||
                            (!isRouterDiscovery && form.data.file === null) ||
                            (isRouterDiscovery && form.data.router_public_id === '')
                        }
                    >
                        {form.data.dry_run ? <Upload size={16} /> : <CheckCircle2 size={16} />}
                        {form.data.dry_run ? 'Preview import' : 'Commit import'}
                    </button>
                </form>

                <div className="card overflow-hidden">
                    <div className="border-b border-line px-6 py-5">
                        <h2 className="text-lg font-semibold">Latest report</h2>
                        <p className="mt-1 text-sm text-muted">
                            The latest preview or commit is shown here after validation.
                        </p>
                    </div>
                    {result ? (
                        <div>
                            <div className="grid gap-3 border-b border-line p-6 sm:grid-cols-4">
                                <Metric label="Type" value={labelForType(result.type, types)} />
                                <Metric label="Rows" value={result.total_rows.toLocaleString()} />
                                <Metric
                                    label="Accepted"
                                    value={result.successful_rows.toLocaleString()}
                                    tone="success"
                                />
                                <Metric
                                    label="Rejected"
                                    value={result.failed_rows.toLocaleString()}
                                    tone={result.failed_rows > 0 ? 'warning' : 'success'}
                                />
                            </div>
                            <div className="max-h-[440px] overflow-auto">
                                <table className="w-full min-w-[560px] text-start">
                                    <thead className="sticky top-0 border-b border-line bg-white text-xs font-semibold uppercase tracking-wider text-muted">
                                        <tr>
                                            <th className="px-6 py-3 text-start">Row</th>
                                            <th className="px-6 py-3 text-start">Status</th>
                                            <th className="px-6 py-3 text-start">Validation</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {result.report.map((row) => (
                                            <ReportRow key={`${row.row}-${row.status}`} row={row} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="grid min-h-80 place-items-center px-6 text-center">
                            <div>
                                <FileUp className="mx-auto text-muted" size={28} />
                                <p className="mt-3 font-semibold">No import report yet</p>
                                <p className="mt-1 text-sm text-muted">
                                    Choose a file and run a preview to see row-level validation.
                                </p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-6 py-5">
                    <div>
                        <h2 className="text-lg font-semibold">Import history</h2>
                        <p className="mt-1 text-sm text-muted">
                            Completed batches remain available for controlled rollback.
                        </p>
                    </div>
                    <RotateCcw size={18} className="text-muted" />
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-6 py-3 text-start">Import</th>
                                <th className="px-6 py-3 text-start">Status</th>
                                <th className="px-6 py-3 text-start">Rows</th>
                                <th className="px-6 py-3 text-start">Created</th>
                                <th className="px-6 py-3 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {batches.map((batch) => (
                                <HistoryRow
                                    key={batch.id}
                                    batch={batch}
                                    types={types}
                                    canRollback={canRollback(batch.type)}
                                />
                            ))}
                            {batches.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-14 text-center text-sm text-muted">
                                        No imports have been run in this tenant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({
    label,
    value,
    tone = 'default',
}: {
    label: string;
    value: string;
    tone?: 'default' | 'success' | 'warning';
}) {
    return (
        <div className="rounded-xl bg-sand/50 p-3">
            <p className="text-[11px] font-semibold uppercase tracking-wider text-muted">{label}</p>
            <p
                className={`mt-1 text-lg font-semibold ${tone === 'warning' ? 'text-coral' : tone === 'success' ? 'text-brand' : 'text-ink'}`}
            >
                {value}
            </p>
        </div>
    );
}

function ReportRow({ row }: { row: ImportBatchReportRow }) {
    const accepted = row.status === 'valid' || row.status === 'imported';
    return (
        <tr>
            <td className="px-6 py-3 text-sm font-semibold">{row.row}</td>
            <td className="px-6 py-3">
                <span
                    className={`inline-flex items-center gap-1.5 text-xs font-semibold ${accepted ? 'text-brand' : 'text-coral'}`}
                >
                    {accepted ? <CheckCircle2 size={14} /> : <AlertTriangle size={14} />}
                    {row.status}
                </span>
            </td>
            <td className="px-6 py-3 text-sm text-muted">{rowErrorText(row)}</td>
        </tr>
    );
}

function HistoryRow({ batch, types, canRollback }: { batch: Batch; types: ImportType[]; canRollback: boolean }) {
    const form = useForm({});
    return (
        <tr>
            <td className="px-6 py-4">
                <p className="text-sm font-semibold">{labelForType(batch.type, types)}</p>
                <p className="mt-1 text-xs text-muted">{batch.filename}</p>
            </td>
            <td className="px-6 py-4">
                <span className="rounded-full bg-sand px-2.5 py-1 text-xs font-semibold capitalize">
                    {batch.status.replace('_', ' ')}
                </span>
            </td>
            <td className="px-6 py-4 text-sm text-muted">
                {batch.successful_rows}/{batch.total_rows} accepted
                {batch.failed_rows > 0 ? ` · ${batch.failed_rows} rejected` : ''}
            </td>
            <td className="px-6 py-4 text-sm text-muted">{formatDate(batch.created_at)}</td>
            <td className="px-6 py-4 text-end">
                {canRollback && batch.status === 'completed' && (
                    <button
                        type="button"
                        className="inline-flex items-center gap-2 text-sm font-semibold text-coral hover:underline"
                        disabled={form.processing}
                        onClick={() => {
                            if (
                                window.confirm(
                                    'Roll back this completed import? Financial balance imports are reversed through the journal.',
                                )
                            )
                                form.post(`/operations/imports/${batch.id}/rollback`);
                        }}
                    >
                        <RotateCcw size={14} /> Roll back
                    </button>
                )}
            </td>
        </tr>
    );
}
