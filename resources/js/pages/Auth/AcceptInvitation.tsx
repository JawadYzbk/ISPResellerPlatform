import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, LockKeyhole, UserPlus } from 'lucide-react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Props = { token: string };

export default function AcceptInvitation({ token }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({ name: '', password: '', password_confirmation: '' });
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
        form.post(`/invite/${token}`);
    };

    return (
        <AuthLayout>
            <Head title={t('auth.accept_invitation')} />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white">
                <UserPlus size={22} />
            </div>
            <p className="eyebrow">{t('auth.tenant_invitation')}</p>
            <h1 className="page-title">{t('auth.create_operator_account')}</h1>
            <p className="page-subtitle">{t('auth.invitation_description')}</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label>
                    <span className="field-label">{t('auth.full_name')}</span>
                    <input
                        id="name"
                        className="field"
                        autoComplete="name"
                        {...fieldA11y('name')}
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                    />
                    {fieldError('name')}
                </label>
                <label>
                    <span className="field-label">{t('Password')}</span>
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
                    <p className="mt-1 text-xs text-muted">{t('auth.minimum_password')}</p>
                </label>
                <label>
                    <span className="field-label">{t('auth.confirm_password')}</span>
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
                    <CheckCircle2 size={16} /> {t('auth.accept_invitation')}
                </button>
                <p className="flex items-center gap-2 text-xs text-muted">
                    <LockKeyhole size={14} /> {t('auth.invitation_expiry')}
                </p>
            </form>
        </AuthLayout>
    );
}
