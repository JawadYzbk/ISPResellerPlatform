import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, Router as RouterIcon, Save, ShieldCheck } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Pop = { id: number; name: string; code: string };
type RouterRecord = {
    public_id: string;
    pop_id: number | null;
    name: string;
    host: string;
    api_port: number;
    username: string;
    coa_port: number;
    tls_verify: boolean;
    status: 'online' | 'offline' | 'unknown';
    last_seen_at: string | null;
    consecutive_failures: number;
    services_count: number;
    pop: { name: string; code: string } | null;
};

type Props = { router: RouterRecord; pops: Pop[]; canEdit: boolean };

export default function RouterShowPage({ router: device, pops, canEdit }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;
    const form = useForm({
        name: device.name,
        host: device.host,
        api_port: device.api_port.toString(),
        username: device.username,
        password: '',
        radius_secret: '',
        coa_port: device.coa_port.toString(),
        tls_verify: device.tls_verify,
        pop_id: device.pop_id?.toString() ?? '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            api_port: Number(data.api_port),
            coa_port: Number(data.coa_port),
            pop_id: data.pop_id ? Number(data.pop_id) : null,
        }));
        form.put(`/operations/routers/${device.public_id}`);
    };

    return (
        <AppLayout>
            <Head title={device.name} />
            <div className="flex items-center justify-between gap-4">
                <Link
                    href="/operations/routers"
                    className="inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
                >
                    <ArrowLeft size={16} /> {t('Back to routers')}
                </Link>
                <button
                    type="button"
                    className="button-secondary"
                    onClick={() => router.post(`/operations/routers/${device.public_id}/health`)}
                >
                    <RefreshCw size={16} /> {t('routers.check_health')}
                </button>
            </div>
            <div className="mt-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('routers.eyebrow')}</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="page-title">{device.name}</h1>
                        <StatusBadge status={device.status} />
                    </div>
                    <p className="page-subtitle">
                        {device.host}:{device.api_port} ·{' '}
                        {device.pop ? `${device.pop.name} (${device.pop.code})` : t('No POP assigned')}
                    </p>
                </div>
                <div className="flex items-center gap-2 text-sm text-muted">
                    <ShieldCheck size={16} className="text-brand" /> {t('router_show.credentials_note')}
                </div>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                <div className="card h-fit p-6">
                    <div className="flex items-center gap-2">
                        <RouterIcon size={17} className="text-brand" />
                        <h2 className="section-title">{t('router_show.device_health')}</h2>
                    </div>
                    <dl className="mt-5 space-y-4 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">{t('Last seen')}</dt>
                            <dd className="font-semibold">{formatDate(device.last_seen_at)}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">{t('router_show.assigned_services')}</dt>
                            <dd className="font-semibold">{device.services_count}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">{t('routers.failed_checks')}</dt>
                            <dd
                                className={
                                    device.consecutive_failures > 0 ? 'font-semibold text-coral' : 'font-semibold'
                                }
                            >
                                {device.consecutive_failures}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">{t('router_show.tls_verification')}</dt>
                            <dd className="font-semibold">{device.tls_verify ? t('Enabled') : t('Disabled')}</dd>
                        </div>
                    </dl>
                </div>
                {canEdit ? (
                    <form onSubmit={submit} className="card space-y-6 p-6">
                        <div>
                            <h2 className="section-title">{t('router_show.connection_settings')}</h2>
                            <p className="mt-1 text-sm text-muted">{t('router_show.secret_note')}</p>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('router_create.name')}</span>
                                <input
                                    id="router-edit-name"
                                    className="field"
                                    {...fieldA11y('router-edit-name', form.errors.name)}
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                {fieldError('router-edit-name', form.errors.name)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_create.host')}</span>
                                <input
                                    id="router-edit-host"
                                    className="field"
                                    {...fieldA11y('router-edit-host', form.errors.host)}
                                    value={form.data.host}
                                    onChange={(event) => form.setData('host', event.target.value)}
                                />
                                {fieldError('router-edit-host', form.errors.host)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_create.api_port')}</span>
                                <input
                                    id="router-edit-api-port"
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    {...fieldA11y('router-edit-api-port', form.errors.api_port)}
                                    value={form.data.api_port}
                                    onChange={(event) => form.setData('api_port', event.target.value)}
                                />
                                {fieldError('router-edit-api-port', form.errors.api_port)}
                            </label>
                            <label>
                                <span className="field-label">POP</span>
                                <ResponsiveSelect
                                    id="router-edit-pop"
                                    className="field"
                                    {...fieldA11y('router-edit-pop', form.errors.pop_id)}
                                    value={form.data.pop_id}
                                    onChange={(event) => form.setData('pop_id', event.target.value)}
                                >
                                    <option value="">{t('No POP assigned')}</option>
                                    {pops.map((pop) => (
                                        <option key={pop.id} value={pop.id}>
                                            {pop.name} ({pop.code})
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                {fieldError('router-edit-pop', form.errors.pop_id)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_create.username')}</span>
                                <input
                                    id="router-edit-username"
                                    className="field"
                                    autoComplete="off"
                                    {...fieldA11y('router-edit-username', form.errors.username)}
                                    value={form.data.username}
                                    onChange={(event) => form.setData('username', event.target.value)}
                                />
                                {fieldError('router-edit-username', form.errors.username)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_show.new_password')}</span>
                                <input
                                    id="router-edit-password"
                                    className="field"
                                    type="password"
                                    autoComplete="new-password"
                                    {...fieldA11y('router-edit-password', form.errors.password)}
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                />
                                {fieldError('router-edit-password', form.errors.password)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_show.new_radius')}</span>
                                <input
                                    id="router-edit-radius-secret"
                                    className="field"
                                    type="password"
                                    autoComplete="new-password"
                                    {...fieldA11y('router-edit-radius-secret', form.errors.radius_secret)}
                                    value={form.data.radius_secret}
                                    onChange={(event) => form.setData('radius_secret', event.target.value)}
                                />
                                {fieldError('router-edit-radius-secret', form.errors.radius_secret)}
                            </label>
                            <label>
                                <span className="field-label">{t('router_create.coa_port')}</span>
                                <input
                                    id="router-edit-coa-port"
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    {...fieldA11y('router-edit-coa-port', form.errors.coa_port)}
                                    value={form.data.coa_port}
                                    onChange={(event) => form.setData('coa_port', event.target.value)}
                                />
                                {fieldError('router-edit-coa-port', form.errors.coa_port)}
                            </label>
                        </div>
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <input
                                id="router-edit-tls"
                                type="checkbox"
                                {...fieldA11y('router-edit-tls', form.errors.tls_verify)}
                                checked={form.data.tls_verify}
                                onChange={(event) => form.setData('tls_verify', event.target.checked)}
                            />{' '}
                            {t('router_create.verify_tls')}
                        </label>
                        {fieldError('router-edit-tls', form.errors.tls_verify)}
                        <div className="flex justify-end border-t border-line pt-5">
                            <button type="submit" className="button-primary" disabled={form.processing}>
                                <Save size={16} /> {t('router_show.save')}
                            </button>
                        </div>
                    </form>
                ) : (
                    <div className="card p-6 text-sm text-muted">{t('router_show.read_only_note')}</div>
                )}
            </div>
        </AppLayout>
    );
}
