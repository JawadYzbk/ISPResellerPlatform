import { Head, useForm } from '@inertiajs/react';

import AuthLayout from '@/layouts/AuthLayout';

type Props = {
    provisioningUri: string;
    secret: string;
    recoveryCodes: string[];
};

export default function Setup({ provisioningUri, secret, recoveryCodes }: Props) {
    const form = useForm({ code: '' });

    return (
        <AuthLayout>
            <Head title="Secure your account" />
            <div className="space-y-6">
                <div>
                    <p className="mb-2 text-sm font-semibold uppercase tracking-[0.18em] text-brand">
                        Required security step
                    </p>
                    <h1 className="font-display text-3xl font-semibold tracking-tight">
                        Set up two-factor authentication
                    </h1>
                    <p className="mt-3 text-sm leading-6 text-muted">
                        Add this account to an authenticator app, then confirm the six-digit code.
                    </p>
                </div>
                <div className="space-y-2 rounded-2xl border border-line bg-panel p-4 text-sm">
                    <p className="font-semibold">Manual setup key</p>
                    <code className="block break-all text-xs text-muted">{secret}</code>
                    <p className="break-all text-xs text-muted">{provisioningUri}</p>
                </div>
                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p className="font-semibold">Save these recovery codes</p>
                    <p className="mt-1 text-xs">Each code works once if you lose access to your authenticator.</p>
                    <div className="mt-3 grid grid-cols-2 gap-2 font-mono text-xs">
                        {recoveryCodes.map((code) => (
                            <span key={code}>{code}</span>
                        ))}
                    </div>
                </div>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/two-factor/setup');
                    }}
                    className="space-y-4"
                >
                    <label className="block text-sm font-semibold" htmlFor="code">
                        Authenticator code
                    </label>
                    <input
                        id="code"
                        className="field"
                        inputMode="numeric"
                        maxLength={6}
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value)}
                    />
                    {form.errors.code && <p className="text-xs text-coral">{form.errors.code}</p>}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        Confirm and continue
                    </button>
                </form>
            </div>
        </AuthLayout>
    );
}
