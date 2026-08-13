import { CheckCircle2, KeyRound, LockKeyhole } from 'lucide-react';
import { Head, useForm, usePage } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Props = { token: string; email: string };

export default function ResetPassword({ token, email }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/reset-password');
    };

    return (
        <AuthLayout>
            <Head title={t('auth.choose_new_password')} />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white">
                <KeyRound size={22} />
            </div>
            <p className="eyebrow">{t('auth.account_recovery')}</p>
            <h1 className="page-title">{t('auth.choose_new_password')}</h1>
            <p className="page-subtitle">{t('auth.new_password_description')}</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label>
                    <span className="field-label">{t('Email address')}</span>
                    <input
                        className="field"
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                    {form.errors.email && <p className="field-error">{t(form.errors.email)}</p>}
                </label>
                <label>
                    <span className="field-label">{t('auth.new_password')}</span>
                    <div className="relative">
                        <LockKeyhole className="pointer-events-none absolute start-3 top-3.5 text-muted" size={18} />
                        <input
                            className="field ps-10"
                            type="password"
                            autoComplete="new-password"
                            autoFocus
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </div>
                    {form.errors.password && <p className="field-error">{t(form.errors.password)}</p>}
                </label>
                <label>
                    <span className="field-label">{t('auth.confirm_new_password')}</span>
                    <input
                        className="field"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    />
                </label>
                <button type="submit" className="button-primary w-full" disabled={form.processing}>
                    <CheckCircle2 size={16} /> {t('auth.reset_password')}
                </button>
            </form>
        </AuthLayout>
    );
}
