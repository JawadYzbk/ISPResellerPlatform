import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileUp, RotateCcw, Upload } from 'lucide-react';
import { useMemo } from 'react';

import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { createTranslator } from '@/lib/i18n';
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

const labelForType = (type: string, types: ImportType[], t: (key: string) => string) =>
    t(types.find((item) => item.value === type)?.label ?? type);

const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : '—';

const rowErrorText = (row: ImportBatchReportRow, t: (key: string) => string) =>
    row.errors.length > 0 ? row.errors.map((error) => t(error)).join('; ') : t('Ready to import');

export default function Imports({ types, routers, batches }: Props) {
    const { props } = usePage<PagePropsWithImportFlash>();
    const { flash } = props;
    const t = createTranslator(props.app.locale);
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
            <Head title={t('imports.title')} />
            <div>
                <p className="eyebrow">{t('imports.eyebrow')}</p>
                <h1 className="page-title">{t('imports.title')}</h1>
                <p className="page-subtitle">{t('imports.subtitle')}</p>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]">
                <form onSubmit={submit} className="card space-y-6 p-6">
                    <div className="flex items-start gap-3">
                        <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                            <FileUp size={19} />
                        </div>
                        <div>
                            <h2 className="text-lg font-semibold">{t('imports.start')}</h2>
                            <p className="mt-1 text-sm leading-6 text-muted">{t('imports.start_description')}</p>
                        </div>
                    </div>

                    <label>
                        <span className="field-label">{t('imports.type')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={form.data.type}
                            onChange={(event) => form.setData('type', event.target.value)}
                        >
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {t(type.label)}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {form.errors.type && <p className="field-error">{t(form.errors.type)}</p>}
                    </label>

                    {isRouterDiscovery ? (
                        <label>
                            <span className="field-label">{t('Router')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={form.data.router_public_id}
                                onChange={(event) => form.setData('router_public_id', event.target.value)}
                            >
                                <option value="">{t('imports.select_router')}</option>
                                {routers.map((router) => (
                                    <option key={router.public_id} value={router.public_id}>
                                        {router.name} · {router.host}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {form.errors.router_public_id && (
                                <p className="field-error">{t(form.errors.router_public_id)}</p>
                            )}
                            <p className="mt-2 text-xs leading-5 text-muted">{t('imports.discovery_description')}</p>
                        </label>
                    ) : (
                        <label>
                            <span className="field-label">{t('imports.file')}</span>
                            <input
                                className="field file:me-3 file:rounded-md file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand"
                                type="file"
                                accept=".csv,.txt,.xlsx"
                                onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            />
                            {form.errors.file && <p className="field-error">{t(form.errors.file)}</p>}
                            <p className="mt-2 text-xs leading-5 text-muted">
                                {t('imports.required_columns')}: {selectedType?.columns ?? t('imports.choose_type')}
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
                            <span className="block text-sm font-semibold">{t('imports.preview_only')}</span>
                            <span className="mt-1 block text-xs leading-5 text-muted">
                                {t('imports.preview_description')}
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
                        {form.data.dry_run ? t('imports.preview') : t('imports.commit')}
                    </button>
                </form>

                <div className="card overflow-hidden">
                    <div className="border-b border-line px-6 py-5">
                        <h2 className="text-lg font-semibold">{t('imports.latest_report')}</h2>
                        <p className="mt-1 text-sm text-muted">{t('imports.latest_report_description')}</p>
                    </div>
                    {result ? (
                        <div>
                            <div className="grid gap-3 border-b border-line p-6 sm:grid-cols-4">
                                <Metric label={t('Type')} value={labelForType(result.type, types, t)} />
                                <Metric label={t('Rows')} value={result.total_rows.toLocaleString()} />
                                <Metric
                                    label={t('Accepted')}
                                    value={result.successful_rows.toLocaleString()}
                                    tone="success"
                                />
                                <Metric
                                    label={t('Rejected')}
                                    value={result.failed_rows.toLocaleString()}
                                    tone={result.failed_rows > 0 ? 'warning' : 'success'}
                                />
                            </div>
                            <div className="max-h-[440px] overflow-auto">
                                <table className="w-full min-w-[560px] text-start">
                                    <thead className="sticky top-0 border-b border-line bg-white text-xs font-semibold uppercase tracking-wider text-muted">
                                        <tr>
                                            <th className="px-6 py-3 text-start">{t('Row')}</th>
                                            <th className="px-6 py-3 text-start">{t('Status')}</th>
                                            <th className="px-6 py-3 text-start">{t('Validation')}</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-line">
                                        {result.report.map((row) => (
                                            <ReportRow key={`${row.row}-${row.status}`} row={row} t={t} />
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div className="grid min-h-80 place-items-center px-6 text-center">
                            <div>
                                <FileUp className="mx-auto text-muted" size={28} />
                                <p className="mt-3 font-semibold">{t('imports.no_report')}</p>
                                <p className="mt-1 text-sm text-muted">{t('imports.no_report_description')}</p>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-6 py-5">
                    <div>
                        <h2 className="text-lg font-semibold">{t('imports.history')}</h2>
                        <p className="mt-1 text-sm text-muted">{t('imports.history_description')}</p>
                    </div>
                    <RotateCcw size={18} className="text-muted" />
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-6 py-3 text-start">{t('Import')}</th>
                                <th className="px-6 py-3 text-start">{t('Status')}</th>
                                <th className="px-6 py-3 text-start">{t('Rows')}</th>
                                <th className="px-6 py-3 text-start">{t('Created')}</th>
                                <th className="px-6 py-3 text-end">{t('Action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {batches.map((batch) => (
                                <HistoryRow
                                    key={batch.id}
                                    batch={batch}
                                    types={types}
                                    canRollback={canRollback(batch.type)}
                                    t={t}
                                />
                            ))}
                            {batches.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-6 py-14 text-center text-sm text-muted">
                                        {t('imports.no_history')}
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

function ReportRow({ row, t }: { row: ImportBatchReportRow; t: (key: string) => string }) {
    const accepted = row.status === 'valid' || row.status === 'imported';
    return (
        <tr>
            <td className="px-6 py-3 text-sm font-semibold">{row.row}</td>
            <td className="px-6 py-3">
                <span
                    className={`inline-flex items-center gap-1.5 text-xs font-semibold ${accepted ? 'text-brand' : 'text-coral'}`}
                >
                    {accepted ? <CheckCircle2 size={14} /> : <AlertTriangle size={14} />}
                    {t(row.status)}
                </span>
            </td>
            <td className="px-6 py-3 text-sm text-muted">{rowErrorText(row, t)}</td>
        </tr>
    );
}

function HistoryRow({
    batch,
    types,
    canRollback,
    t,
}: {
    batch: Batch;
    types: ImportType[];
    canRollback: boolean;
    t: (key: string) => string;
}) {
    const form = useForm({});
    return (
        <tr>
            <td className="px-6 py-4">
                <p className="text-sm font-semibold">{labelForType(batch.type, types, t)}</p>
                <p className="mt-1 text-xs text-muted">{batch.filename}</p>
            </td>
            <td className="px-6 py-4">
                <span className="rounded-full bg-sand px-2.5 py-1 text-xs font-semibold capitalize">
                    {t(batch.status.replace('_', ' '))}
                </span>
            </td>
            <td className="px-6 py-4 text-sm text-muted">
                {batch.successful_rows}/{batch.total_rows} {t('Accepted').toLocaleLowerCase()}
                {batch.failed_rows > 0 ? ` · ${batch.failed_rows} ${t('Rejected').toLocaleLowerCase()}` : ''}
            </td>
            <td className="px-6 py-4 text-sm text-muted">{formatDate(batch.created_at)}</td>
            <td className="px-6 py-4 text-end">
                {canRollback && batch.status === 'completed' && (
                    <ConfirmDialog
                        title={t('imports.rollback_title')}
                        description={t('imports.rollback_description')}
                        confirmLabel={t('imports.rollback')}
                        destructive
                        onConfirm={() => form.post(`/operations/imports/${batch.id}/rollback`)}
                    >
                        <button
                            type="button"
                            className="inline-flex items-center gap-2 text-sm font-semibold text-coral hover:underline"
                            disabled={form.processing}
                        >
                            <RotateCcw size={14} /> {t('imports.rollback')}
                        </button>
                    </ConfirmDialog>
                )}
            </td>
        </tr>
    );
}
