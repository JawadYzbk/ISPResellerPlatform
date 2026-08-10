import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, Router as RouterIcon, Save, ShieldCheck } from 'lucide-react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';

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
                    <ArrowLeft size={16} /> Back to routers
                </Link>
                <button
                    type="button"
                    className="button-secondary"
                    onClick={() => router.post(`/operations/routers/${device.public_id}/health`)}
                >
                    <RefreshCw size={16} /> Check health
                </button>
            </div>
            <div className="mt-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Network operations</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="page-title">{device.name}</h1>
                        <StatusBadge status={device.status} />
                    </div>
                    <p className="page-subtitle">
                        {device.host}:{device.api_port} ·{' '}
                        {device.pop ? `${device.pop.name} (${device.pop.code})` : 'No POP assigned'}
                    </p>
                </div>
                <div className="flex items-center gap-2 text-sm text-muted">
                    <ShieldCheck size={16} className="text-brand" /> Credentials stay encrypted and write-only
                </div>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-[0.75fr_1.25fr]">
                <div className="card h-fit p-6">
                    <div className="flex items-center gap-2">
                        <RouterIcon size={17} className="text-brand" />
                        <h2 className="section-title">Device health</h2>
                    </div>
                    <dl className="mt-5 space-y-4 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Last seen</dt>
                            <dd className="font-semibold">{formatDate(device.last_seen_at)}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Assigned services</dt>
                            <dd className="font-semibold">{device.services_count}</dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">Failed checks</dt>
                            <dd
                                className={
                                    device.consecutive_failures > 0 ? 'font-semibold text-coral' : 'font-semibold'
                                }
                            >
                                {device.consecutive_failures}
                            </dd>
                        </div>
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted">TLS verification</dt>
                            <dd className="font-semibold">{device.tls_verify ? 'Enabled' : 'Disabled'}</dd>
                        </div>
                    </dl>
                </div>
                {canEdit ? (
                    <form onSubmit={submit} className="card space-y-6 p-6">
                        <div>
                            <h2 className="section-title">Connection settings</h2>
                            <p className="mt-1 text-sm text-muted">
                                Leave a secret blank to keep the currently encrypted value.
                            </p>
                        </div>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">Router name</span>
                                <input
                                    className="field"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                            </label>
                            <label>
                                <span className="field-label">Host or IP</span>
                                <input
                                    className="field"
                                    value={form.data.host}
                                    onChange={(event) => form.setData('host', event.target.value)}
                                />
                                {form.errors.host && <p className="field-error">{form.errors.host}</p>}
                            </label>
                            <label>
                                <span className="field-label">API port</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={form.data.api_port}
                                    onChange={(event) => form.setData('api_port', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">POP</span>
                                <select
                                    className="field"
                                    value={form.data.pop_id}
                                    onChange={(event) => form.setData('pop_id', event.target.value)}
                                >
                                    <option value="">No POP assigned</option>
                                    {pops.map((pop) => (
                                        <option key={pop.id} value={pop.id}>
                                            {pop.name} ({pop.code})
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                <span className="field-label">API username</span>
                                <input
                                    className="field"
                                    autoComplete="off"
                                    value={form.data.username}
                                    onChange={(event) => form.setData('username', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">New API password</span>
                                <input
                                    className="field"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                />
                                {form.errors.password && <p className="field-error">{form.errors.password}</p>}
                            </label>
                            <label>
                                <span className="field-label">New RADIUS secret</span>
                                <input
                                    className="field"
                                    type="password"
                                    autoComplete="new-password"
                                    value={form.data.radius_secret}
                                    onChange={(event) => form.setData('radius_secret', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">CoA port</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={65535}
                                    value={form.data.coa_port}
                                    onChange={(event) => form.setData('coa_port', event.target.value)}
                                />
                            </label>
                        </div>
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={form.data.tls_verify}
                                onChange={(event) => form.setData('tls_verify', event.target.checked)}
                            />{' '}
                            Verify TLS certificates for router API requests
                        </label>
                        <div className="flex justify-end border-t border-line pt-5">
                            <button className="button-primary" disabled={form.processing}>
                                <Save size={16} /> Save connection settings
                            </button>
                        </div>
                    </form>
                ) : (
                    <div className="card p-6 text-sm text-muted">
                        You can inspect this device, but network provisioning capability is required to edit connection
                        settings.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
