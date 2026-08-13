import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Save, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator, roleLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Profile = {
    name: string;
    email: string;
    role: string;
    locale: 'en' | 'ar' | 'fr' | null;
    timezone: string | null;
    default_view: string;
};

type DefaultView = { label: string; detail: string; href: string };
type Props = { profile: Profile; workspaceLocale: 'en' | 'ar' | 'fr'; defaultViews: DefaultView[] };

export default function ProfilePage({ profile, workspaceLocale, defaultViews }: Props) {
    const form = useForm<Profile>(profile);
    const page = usePage<PageProps & { errors?: Record<string, string> }>();
    const t = createTranslator(page.props.app.locale);
    const pageErrors = page.props.errors ?? {};
    const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch('/profile', {
            preserveState: true,
            preserveScroll: true,
            onError: (errors) => setValidationErrors(errors),
            onSuccess: () => setValidationErrors({}),
        });
    };

    return (
        <AppLayout>
            <Head title={t('Profile')} />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to dashboard')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('Account')}</p>
                <h1 className="page-title">{t('Your profile')}</h1>
                <p className="page-subtitle">{t('Keep your name, language, and working timezone up to date.')}</p>

                <form onSubmit={submit} className="card mt-8 space-y-7 p-6">
                    <section>
                        <div className="flex items-center gap-2">
                            <ShieldCheck size={18} className="text-brand" />
                            <h2 className="section-title">{t('Account details')}</h2>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('Name')}</span>
                                <input
                                    className="field"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                    autoComplete="name"
                                />
                                {(form.errors.name ?? validationErrors.name ?? pageErrors.name) && (
                                    <p className="field-error">
                                        {t(form.errors.name ?? validationErrors.name ?? pageErrors.name ?? '')}
                                    </p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Email')}</span>
                                <input className="field bg-sand" value={form.data.email} disabled />
                                <p className="mt-1 text-xs text-muted">
                                    {t('Email changes are handled by an administrator.')}
                                </p>
                            </label>
                            <label>
                                <span className="field-label">{t('Role')}</span>
                                <input
                                    className="field bg-sand capitalize"
                                    value={roleLabel(form.data.role, t)}
                                    disabled
                                />
                            </label>
                            <label>
                                <span className="field-label">{t('Language')}</span>
                                <ResponsiveSelect
                                    id="profile-locale"
                                    className="field"
                                    value={form.data.locale ?? ''}
                                    onChange={(event) =>
                                        form.setData('locale', event.target.value as Profile['locale'])
                                    }
                                >
                                    <option value="">
                                        {t('Use workspace language')} ({workspaceLocale.toUpperCase()})
                                    </option>
                                    <option value="en">{t('English')}</option>
                                    <option value="ar">{t('Arabic')}</option>
                                    <option value="fr">{t('French')}</option>
                                </ResponsiveSelect>
                            </label>
                            <label className="sm:col-span-2">
                                <span className="field-label">{t('Personal timezone')}</span>
                                <input
                                    className="field"
                                    value={form.data.timezone ?? ''}
                                    onChange={(event) => form.setData('timezone', event.target.value || null)}
                                    placeholder={t('Leave blank to use the workspace timezone')}
                                    autoComplete="off"
                                />
                                {(form.errors.timezone ?? validationErrors.timezone ?? pageErrors.timezone) && (
                                    <p className="field-error">
                                        {t(
                                            form.errors.timezone ??
                                                validationErrors.timezone ??
                                                pageErrors.timezone ??
                                                '',
                                        )}
                                    </p>
                                )}
                            </label>
                            <label className="sm:col-span-2">
                                <span className="field-label">{t('Default view after sign in')}</span>
                                <ResponsiveSelect
                                    id="profile-default-view"
                                    className="field"
                                    value={form.data.default_view}
                                    onChange={(event) => form.setData('default_view', event.target.value)}
                                >
                                    {defaultViews.map((view) => (
                                        <option key={view.href} value={view.href}>
                                            {t(view.label)} · {t(view.detail)}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                <p className="mt-1 text-xs text-muted">
                                    {t('This is used when there is no page you were trying to open before signing in.')}
                                </p>
                                {form.errors.default_view && (
                                    <p className="field-error">{t(form.errors.default_view)}</p>
                                )}
                            </label>
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-5">
                        <div className="flex flex-wrap gap-2">
                            <Link href="/security/sessions" className="button-secondary">
                                <KeyRound size={16} /> {t('Active sessions')}
                            </Link>
                            <Link href="/settings/general" className="button-secondary">
                                {t('Workspace settings')}
                            </Link>
                        </div>
                        <button id="save-profile" className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('Save profile')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
