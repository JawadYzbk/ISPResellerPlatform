import { Head, useForm, usePage } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function Challenge() {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const form = useForm({ code: '' });

    return (
        <AuthLayout>
            <Head title={t('Verify your account')} />
            <div>
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">
                    {t('Additional verification')}
                </p>
                <h1 className="font-display text-3xl font-semibold tracking-tight">
                    {t('Enter your authentication code')}
                </h1>
                <p className="mt-3 text-sm leading-6 text-muted">
                    {t('Use your authenticator code or one unused recovery code.')}
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/two-factor/challenge');
                    }}
                    className="mt-8 space-y-4"
                >
                    <label className="block text-sm font-semibold" htmlFor="code">
                        {t('Code')}
                    </label>
                    <input
                        id="code"
                        className="field"
                        autoFocus
                        inputMode="numeric"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                    />
                    {form.errors.code && <p className="text-xs text-coral">{t(form.errors.code)}</p>}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        {t('Verify and continue')}
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
