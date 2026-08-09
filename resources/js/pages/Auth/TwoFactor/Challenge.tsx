import { Head, useForm } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';

export default function Challenge() {
    const form = useForm({ code: '' });

    return (
        <AuthLayout>
            <Head title="Verify your account" />
            <div>
                <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">
                    Additional verification
                </p>
                <h1 className="font-display text-3xl font-semibold tracking-tight">Enter your authentication code</h1>
                <p className="mt-3 text-sm leading-6 text-muted">
                    Use your authenticator code or one unused recovery code.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/two-factor/challenge');
                    }}
                    className="mt-8 space-y-4"
                >
                    <label className="block text-sm font-semibold" htmlFor="code">
                        Code
                    </label>
                    <input
                        id="code"
                        className="field"
                        autoFocus
                        inputMode="numeric"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                    />
                    {form.errors.code && <p className="text-xs text-coral">{form.errors.code}</p>}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        Verify and continue
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
