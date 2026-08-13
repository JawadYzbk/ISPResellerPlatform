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
                        id="email"
                        className="field"
                        type="email"
                        autoComplete="email"
                        {...fieldA11y('email')}
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                    {fieldError('email')}
                </label>
                <label>
                    <span className="field-label">{t('auth.new_password')}</span>
                    <div className="relative">
                        <LockKeyhole className="pointer-events-none absolute start-3 top-3.5 text-muted" size={18} />
                        <input
                            id="password"
                            className="field ps-10"
                            type="password"
                            autoComplete="new-password"
                            autoFocus
                            {...fieldA11y('password')}
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                    </div>
                    {fieldError('password')}
                </label>
                <label>
                    <span className="field-label">{t('auth.confirm_new_password')}</span>
                    <input
                        id="password_confirmation"
                        className="field"
                        type="password"
                        autoComplete="new-password"
                        {...fieldA11y('password_confirmation')}
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    />
                    {fieldError('password_confirmation')}
                </label>
                <button type="submit" className="button-primary w-full" disabled={form.processing}>
                    <CheckCircle2 size={16} /> {t('auth.reset_password')}
                </button>
            </form>
        </AuthLayout>
    );
}
