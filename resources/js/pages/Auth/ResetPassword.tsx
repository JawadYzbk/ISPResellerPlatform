import { CheckCircle2, KeyRound, LockKeyhole } from 'lucide-react';
import { Head, useForm } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';

type Props = { token: string; email: string };

export default function ResetPassword({ token, email }: Props) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/reset-password');
    };

    return (
        <AuthLayout>
            <Head title="Choose a new password" />
            <div className="mb-8 grid size-12 place-items-center rounded-2xl bg-brand text-white">
                <KeyRound size={22} />
            </div>
            <p className="eyebrow">Account recovery</p>
            <h1 className="page-title">Choose a new password</h1>
            <p className="page-subtitle">Use at least 12 characters. Your existing sessions will be signed out.</p>
            <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                <label>
                    <span className="field-label">Email address</span>
                    <input
                        className="field"
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(event) => form.setData('email', event.target.value)}
                    />
                    {form.errors.email && <p className="field-error">{form.errors.email}</p>}
                </label>
                <label>
                    <span className="field-label">New password</span>
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
                    {form.errors.password && <p className="field-error">{form.errors.password}</p>}
                </label>
                <label>
                    <span className="field-label">Confirm new password</span>
                    <input
                        className="field"
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    />
                </label>
                <button className="button-primary w-full" disabled={form.processing}>
                    <CheckCircle2 size={16} /> Reset password
                </button>
            </form>
        </AuthLayout>
    );
}
