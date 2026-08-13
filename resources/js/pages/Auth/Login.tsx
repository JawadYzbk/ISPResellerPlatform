import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, Eye, EyeOff, LockKeyhole, Mail } from 'lucide-react';
import { useState } from 'react';

import AuthLayout from '@/layouts/AuthLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

export default function Login() {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({ email: 'admin@example.com', password: 'password', remember: true });

    return (
        <AuthLayout>
            <Head title={t('Sign in')} />
            <div>
                <div className="mb-10 lg:hidden">
                    <div className="mb-5 grid size-10 place-items-center rounded-xl bg-brand text-white">
                        <LockKeyhole size={20} />
                    </div>
                    <p className="font-display text-xl font-bold">ISP Manager</p>
                </div>
                <div className="mb-8">
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">
                        {t('Welcome back')}
                    </p>
                    <h2 className="font-display text-3xl font-semibold tracking-tight">
                        {t('Sign in to your workspace')}
                    </h2>
                    <p className="mt-3 text-sm leading-6 text-muted">
                        {t('Use your staff account to access the operations desk.')}
                    </p>
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/login');
                    }}
                    className="space-y-5"
                >
                    <div>
                        <label htmlFor="email" className="mb-2 block text-sm font-semibold">
                            {t('Email address')}
                        </label>
                        <div className="relative">
                            <Mail className="pointer-events-none absolute start-3 top-3.5 text-muted" size={18} />
                            <input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                className="field ps-10"
                                autoComplete="email"
                            />
                            {form.errors.email && <p className="mt-1.5 text-xs text-coral">{form.errors.email}</p>}
                        </div>
                    </div>
                    <div>
                        <label htmlFor="password" className="mb-2 block text-sm font-semibold">
                            {t('Password')}
                        </label>
                        <div className="relative">
                            <LockKeyhole
                                className="pointer-events-none absolute start-3 top-3.5 text-muted"
                                size={18}
                            />
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                value={form.data.password}
                                onChange={(event) => form.setData('password', event.target.value)}
                                className="field ps-10 pe-10"
                                autoComplete="current-password"
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute end-3 top-3.5 text-muted hover:text-ink"
                            >
                                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
                            </button>
                            {form.errors.password && (
                                <p className="mt-1.5 text-xs text-coral">{form.errors.password}</p>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center justify-between gap-4 text-sm text-muted">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.remember}
                                onChange={(event) => form.setData('remember', event.target.checked)}
                                className="size-4 rounded border-line text-brand focus:ring-brand"
                            />
                            {t('Keep me signed in')}
                        </label>
                        <Link href="/forgot-password" className="font-semibold text-brand hover:underline">
                            {t('Forgot password?')}
                        </Link>
                    </div>
                    <button type="submit" disabled={form.processing} className="button-primary w-full justify-center">
                        {form.processing ? t('Signing in…') : t('Enter workspace')}
                        <ArrowRight size={17} />
                    </button>
                </form>
                <p className="mt-8 text-center text-xs leading-5 text-muted">
                    {t('Need access? Ask your tenant owner to invite your staff account.')}
                </p>
            </div>
        </AuthLayout>
    );
}
