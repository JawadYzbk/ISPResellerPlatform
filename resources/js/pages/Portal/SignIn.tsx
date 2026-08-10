import { Head } from '@inertiajs/react';
import { ArrowRight, LockKeyhole, Wifi } from 'lucide-react';
import { useState } from 'react';

import type { PublicTenant } from '@/types';

type Props = { tenant: PublicTenant };

export default function PortalSignIn({ tenant }: Props) {
    const [phone, setPhone] = useState('');
    const [code, setCode] = useState('');
    const [challengeId, setChallengeId] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const requestOtp = async (event: React.FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/otp/request`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone }),
        });
        const payload = await response.json();
        setBusy(false);
        if (!response.ok) {
            setError(payload.detail ?? 'We could not start verification.');
            return;
        }
        setChallengeId(payload.challenge_id);
    };

    const verifyOtp = async (event: React.FormEvent) => {
        event.preventDefault();
        if (challengeId === null) return;
        setBusy(true);
        setError(null);
        const response = await fetch(`/api/v1/portal/${tenant.slug}/otp/verify`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ challenge_id: challengeId, code }),
        });
        const payload = await response.json();
        setBusy(false);
        if (!response.ok) {
            setError(payload.detail ?? 'That code is not valid.');
            return;
        }
        sessionStorage.setItem(`portal_token:${tenant.slug}`, payload.token);
        window.location.assign(`/portal/${tenant.slug}/dashboard`);
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-10 text-ink sm:grid sm:place-items-center">
            <Head title={`${tenant.name} customer portal`} />
            <main className="mx-auto w-full max-w-md">
                <div className="mb-8 flex items-center gap-3">
                    <div className="grid size-10 place-items-center rounded-xl bg-brand text-white">
                        <Wifi size={19} />
                    </div>
                    <div>
                        <p className="font-display font-bold">{tenant.name}</p>
                        <p className="text-sm text-muted">Customer portal</p>
                    </div>
                </div>
                <div className="card p-6 sm:p-8">
                    <div className="flex items-center gap-2 text-brand">
                        <LockKeyhole size={18} />
                        <span className="eyebrow">Secure access</span>
                    </div>
                    <h1 className="mt-4 page-title">Manage your connection.</h1>
                    <p className="page-subtitle">Use the phone number on your account. We will send a one-time code.</p>
                    {challengeId === null ? (
                        <form onSubmit={requestOtp} className="mt-8 space-y-5">
                            <label className="block">
                                <span className="field-label">Phone number</span>
                                <input
                                    required
                                    value={phone}
                                    onChange={(event) => setPhone(event.target.value)}
                                    className="field"
                                    placeholder="+961 70 123 456"
                                />
                            </label>
                            <button disabled={busy} className="button-primary w-full justify-center">
                                {busy ? 'Sending…' : 'Send code'}
                                <ArrowRight size={16} />
                            </button>
                        </form>
                    ) : (
                        <form onSubmit={verifyOtp} className="mt-8 space-y-5">
                            <label className="block">
                                <span className="field-label">Verification code</span>
                                <input
                                    required
                                    inputMode="numeric"
                                    pattern="[0-9]{6}"
                                    maxLength={6}
                                    value={code}
                                    onChange={(event) => setCode(event.target.value)}
                                    className="field tracking-[0.4em]"
                                    placeholder="000000"
                                />
                            </label>
                            <button disabled={busy} className="button-primary w-full justify-center">
                                {busy ? 'Checking…' : 'Open portal'}
                                <ArrowRight size={16} />
                            </button>
                            <button
                                type="button"
                                onClick={() => setChallengeId(null)}
                                className="button-quiet w-full justify-center"
                            >
                                Use a different number
                            </button>
                        </form>
                    )}
                    {error && (
                        <p className="field-error" role="alert">
                            {error}
                        </p>
                    )}
                </div>
            </main>
        </div>
    );
}
