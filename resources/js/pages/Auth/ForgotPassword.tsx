import { ArrowLeft, KeyRound, Mail } from 'lucide-react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

import AuthLayout from '@/layouts/AuthLayout';

export default function ForgotPassword() {
    const [submitted, setSubmitted] = useState(false);
    const form = useForm({ email: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/forgot-password', { onSuccess: () => setSubmitted(true) });
    };

    return (
        <AuthLayout>
            <Head title="Reset your password" />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white">
                <KeyRound size={22} />
            </div>
            <p className="eyebrow">Account recovery</p>
            <h1 className="page-title">Reset your password</h1>
            <p className="page-subtitle">Enter your staff email and we’ll send a secure reset link.</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label>
                    <span className="field-label">Email address</span>
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
                    {form.errors.email && <p className="field-error">{form.errors.email}</p>}
                </label>
                {submitted && !form.errors.email && (
                    <p className="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">
                        If an account matches that address, a reset link is on its way.
                    </p>
                )}
                <button className="button-primary w-full" disabled={form.processing}>
                    <KeyRound size={16} /> Send reset link
                </button>
                <Link
                    href="/login"
                    className="flex items-center justify-center gap-2 text-sm font-semibold text-brand hover:underline"
                >
                    <ArrowLeft size={15} /> Back to sign in
                </Link>
            </form>
        </AuthLayout>
    );
}
