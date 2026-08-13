import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Router as RouterIcon, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Pop = { id: number; name: string; code: string };

type Props = { pops: Pop[] };

export default function RouterCreate({ pops }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
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

    const fieldA11y = (name: keyof typeof form.data) => ({
        'aria-invalid': Boolean(form.errors[name]),
        'aria-describedby': form.errors[name] ? `${name}-error` : undefined,
    });
    const fieldError = (name: keyof typeof form.data) =>
        form.errors[name] ? (
            <p id={`${name}-error`} className="field-error" role="alert">
                {t(form.errors[name])}
            </p>
        ) : null;

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
            <Head title={t('router_create.add')} />
            <Link
                href="/operations/routers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to routers')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('routers.eyebrow')}</p>
                <h1 className="page-title">{t('router_create.register')}</h1>
                <p className="page-subtitle">{t('router_create.subtitle')}</p>
                <form onSubmit={submit} className="card mt-8 space-y-6 p-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <label>
                            <span className="field-label">{t('router_create.name')}</span>
                            <input
                                id="name"
                                className="field"
                                {...fieldA11y('name')}
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                            />
                            {fieldError('name')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.host')}</span>
                            <input
                                id="host"
                                className="field"
                                {...fieldA11y('host')}
                                value={form.data.host}
                                onChange={(event) => form.setData('host', event.target.value)}
                            />
                            {fieldError('host')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.api_port')}</span>
                            <input
                                id="api_port"
                                className="field"
                                type="number"
                                min="1"
                                max="65535"
                                {...fieldA11y('api_port')}
                                value={form.data.api_port}
                                onChange={(event) => form.setData('api_port', event.target.value)}
                            />
                            {fieldError('api_port')}
                        </label>
                        <label>
                            <span className="field-label">POP</span>
                            <ResponsiveSelect
                                id="pop_id"
                                className="field"
                                {...fieldA11y('pop_id')}
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
                            {fieldError('pop_id')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.username')}</span>
                            <input
                                id="username"
                                className="field"
                                autoComplete="off"
                                {...fieldA11y('username')}
                                value={form.data.username}
                                onChange={(event) => form.setData('username', event.target.value)}
                            />
                            {fieldError('username')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.password')}</span>
                            <input
                                id="password"
                                className="field"
                                type="password"
                                autoComplete="new-password"
                                {...fieldA11y('password')}
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                            />
                            {fieldError('password')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.radius_secret')}</span>
                            <input
                                id="radius_secret"
                                className="field"
                                type="password"
                                autoComplete="new-password"
                                {...fieldA11y('radius_secret')}
                                value={form.data.radius_secret}
                                onChange={(event) => form.setData('radius_secret', event.target.value)}
                            />
                            {fieldError('radius_secret')}
                        </label>
                        <label>
                            <span className="field-label">{t('router_create.coa_port')}</span>
                            <input
                                id="coa_port"
                                className="field"
                                type="number"
                                min="1"
                                max="65535"
                                {...fieldA11y('coa_port')}
                                value={form.data.coa_port}
                                onChange={(event) => form.setData('coa_port', event.target.value)}
                            />
                            {fieldError('coa_port')}
                        </label>
                    </div>
                    <label className="flex items-center gap-3 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={form.data.tls_verify}
                            onChange={(event) => form.setData('tls_verify', event.target.checked)}
                        />
                        {t('router_create.verify_tls')}
                    </label>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href="/operations/routers" className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button type="submit" className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('router_create.register')}
                        </button>
                    </div>
                    <p className="flex items-center gap-2 text-xs text-muted">
                        <RouterIcon size={14} /> {t('router_create.health_note')}
                    </p>
                </form>
            </div>
        </AppLayout>
    );
}
