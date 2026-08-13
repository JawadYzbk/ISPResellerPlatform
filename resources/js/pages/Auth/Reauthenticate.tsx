import { Head, useForm, usePage } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function Reauthenticate() {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const form = useForm({ password: '' });

    return (
        <AuthLayout>
            <Head title={t('Confirm your identity')} />
            <div>
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">
                    {t('Protected action')}
                </p>
                <h1 className="font-display text-3xl font-semibold tracking-tight">{t('Confirm your identity')}</h1>
                <p className="mt-3 text-sm leading-6 text-muted">
                    {t('Enter your password to continue with this sensitive action.')}
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/security/reauthenticate');
                    }}
                    className="mt-8 space-y-4"
                >
                    <label className="block text-sm font-semibold" htmlFor="password">
                        {t('Password')}
                    </label>
                    <input
                        id="password"
                        className="field"
                        type="password"
                        autoFocus
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                    {form.errors.password && <p className="text-xs text-coral">{t(form.errors.password)}</p>}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        {t('Confirm')}
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
