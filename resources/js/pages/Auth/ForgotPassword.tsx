import { ArrowLeft, KeyRound, Mail } from 'lucide-react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function ForgotPassword() {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [submitted, setSubmitted] = useState(false);
    const form = useForm({ email: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/forgot-password', { onSuccess: () => setSubmitted(true) });
    };

    return (
        <AuthLayout>
            <Head title={t('auth.reset_password')} />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white">
                <KeyRound size={22} />
            </div>
            <p className="eyebrow">{t('auth.account_recovery')}</p>
            <h1 className="page-title">{t('auth.reset_password')}</h1>
            <p className="page-subtitle">{t('Enter your staff email and we’ll send a secure reset link.')}</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label>
                    <span className="field-label">{t('Email address')}</span>
                    <div className="relative">
                        <Mail className="pointer-events-none absolute start-3 top-3.5 text-muted" size={18} />
                        <input
                            className="field ps-10"
                            type="email"
                            autoComplete="email"
                            autoFocus
                            value={form.data.email}
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                    </div>
                    {form.errors.email && <p className="field-error">{t(form.errors.email)}</p>}
                </label>
                {submitted && !form.errors.email && (
                    <p className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{t('auth.reset_sent')}</p>
                )}
                <button className="button-primary w-full" disabled={form.processing}>
                    <KeyRound size={16} /> {t('auth.send_reset_link')}
                </button>
                <Link
                    href="/login"
                    className="flex items-center justify-center gap-2 text-sm font-semibold text-brand hover:underline"
                >
                    <ArrowLeft size={15} /> {t('auth.back_to_sign_in')}
                </Link>
            </form>
        </AuthLayout>
    );
}
