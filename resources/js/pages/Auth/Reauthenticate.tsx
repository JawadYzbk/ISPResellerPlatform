import { Head, useForm } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';

export default function Reauthenticate() {
    const form = useForm({ password: '' });

    return (
        <AuthLayout>
            <Head title="Confirm your identity" />
            <div>
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">Protected action</p>
                <h1 className="font-display text-3xl font-semibold tracking-tight">Confirm your identity</h1>
                <p className="mt-3 text-sm leading-6 text-muted">
                    Enter your password to continue with this sensitive action.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/security/reauthenticate');
                    }}
                    className="mt-8 space-y-4"
                >
                    <label className="block text-sm font-semibold" htmlFor="password">
                        Password
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
                    {form.errors.password && <p className="text-xs text-coral">{form.errors.password}</p>}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        Confirm
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
