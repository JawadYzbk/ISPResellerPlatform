import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Router as RouterIcon, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Pop = { id: number; name: string; code: string };

type Props = { pops: Pop[] };

export default function RouterCreate({ pops }: Props) {
    const form = useForm({
        name: '',
        host: '',
        api_port: '443',
        username: '',
        password: '',
        radius_secret: '',
        coa_port: '1700',
        tls_verify: true,
        pop_id: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            api_port: Number(data.api_port),
            coa_port: Number(data.coa_port),
            pop_id: data.pop_id ? Number(data.pop_id) : null,
        }));
        form.post('/operations/routers');
    };

    return (
        <AppLayout>
            <Head title="Add router" />
            <Link
                href="/operations/routers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to routers
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">Network operations</p>
                <h1 className="page-title">Register router</h1>
                <p className="page-subtitle">
                    Store the connection boundary for a router. Secrets are encrypted at rest and never returned by the
                    operations list.
                </p>
                <form onSubmit={submit} className="card mt-8 space-y-6 p-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label>
                            <span className="field-label">Router name</span>
                            <input className="field" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                            {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                        </label>
                        <label>
                            <span className="field-label">Host or IP</span>
                            <input className="field" value={form.data.host} onChange={(event) => form.setData('host', event.target.value)} />
                            {form.errors.host && <p className="field-error">{form.errors.host}</p>}
                        </label>
                        <label>
                            <span className="field-label">API port</span>
                            <input className="field" type="number" min="1" max="65535" value={form.data.api_port} onChange={(event) => form.setData('api_port', event.target.value)} />
                            {form.errors.api_port && <p className="field-error">{form.errors.api_port}</p>}
                        </label>
                        <label>
                            <span className="field-label">POP</span>
                            <select className="field" value={form.data.pop_id} onChange={(event) => form.setData('pop_id', event.target.value)}>
                                <option value="">No POP assigned</option>
                                {pops.map((pop) => <option key={pop.id} value={pop.id}>{pop.name} ({pop.code})</option>)}
                            </select>
                            {form.errors.pop_id && <p className="field-error">{form.errors.pop_id}</p>}
                        </label>
                        <label>
                            <span className="field-label">API username</span>
                            <input className="field" autoComplete="off" value={form.data.username} onChange={(event) => form.setData('username', event.target.value)} />
                            {form.errors.username && <p className="field-error">{form.errors.username}</p>}
                        </label>
                        <label>
                            <span className="field-label">API password</span>
                            <input className="field" type="password" autoComplete="new-password" value={form.data.password} onChange={(event) => form.setData('password', event.target.value)} />
                            {form.errors.password && <p className="field-error">{form.errors.password}</p>}
                        </label>
                        <label>
                            <span className="field-label">RADIUS shared secret (optional)</span>
                            <input className="field" type="password" autoComplete="new-password" value={form.data.radius_secret} onChange={(event) => form.setData('radius_secret', event.target.value)} />
                            {form.errors.radius_secret && <p className="field-error">{form.errors.radius_secret}</p>}
                        </label>
                        <label>
                            <span className="field-label">CoA port</span>
                            <input className="field" type="number" min="1" max="65535" value={form.data.coa_port} onChange={(event) => form.setData('coa_port', event.target.value)} />
                            {form.errors.coa_port && <p className="field-error">{form.errors.coa_port}</p>}
                        </label>
                    </div>
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <input type="checkbox" checked={form.data.tls_verify} onChange={(event) => form.setData('tls_verify', event.target.checked)} />
                        Verify TLS certificates for router API requests
                    </label>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href="/operations/routers" className="button-secondary">Cancel</Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> Register router
                        </button>
                    </div>
                    <p className="flex items-center gap-2 text-xs text-muted">
                        <RouterIcon size={14} /> Connection health can be checked after registration from the router queue.
                    </p>
                </form>
            </div>
        </AppLayout>
    );
}
