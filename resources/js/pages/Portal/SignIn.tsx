import { Head, usePage } from '@inertiajs/react';
import { ArrowRight, LockKeyhole, Wifi } from 'lucide-react';
import { useEffect, useState } from 'react';

import { createTranslator } from '@/lib/i18n';
import type { PageProps, PublicTenant } from '@/types';

type Props = { tenant: PublicTenant };

export default function PortalSignIn({ tenant }: Props) {
    const { props } = usePage<PageProps>();
   const t = createTranslator(tenant.locale || props.app.locale);
    useEffect(() => {
        document.documentElement.lang = tenant.locale;
        document.documentElement.dir = tenant.locale === 'ar' ? 'rtl' : 'ltr';
    }, [tenant.locale]);
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
            setError(payload.detail ? t(payload.detail) : t('portal.sign_in_error'));
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
            setError(payload.detail ? t(payload.detail) : t('portal.invalid_code'));
            return;
        }
        sessionStorage.setItem(`portal_token:${tenant.slug}`, payload.token);
        window.location.assign(`/portal/${tenant.slug}/dashboard`);
    };

    return (
        <div className="min-h-screen bg-canvas px-5 py-10 text-ink sm:grid sm:place-items-center">
            <Head title={`${tenant.name} · ${t('Customer portal')}`} />
            <main className="mx-auto w-full max-w-md">
                <div className="mb-8 flex items-center gap-3">
                    <div className="grid size-10 place-items-center overflow-hidden rounded-xl bg-brand text-white">
                        {tenant.logo_url ? (
                            <img src={tenant.logo_url} alt="" className="size-full object-cover" />
                        ) : (
                            <Wifi size={19} />
                        )}
                    </div>
                    <div>
                        <p className="font-display font-bold">{tenant.name}</p>
                        <p className="text-sm text-muted">{t('portal.customer_portal')}</p>
                    </div>
                </div>
                <div className="card p-6 sm:p-8">
                    <div className="flex items-center gap-2 text-brand">
                        <LockKeyhole size={18} />
                        <span className="eyebrow">{t('portal.secure_access')}</span>
                    </div>
                    <h1 className="mt-4 page-title">{t('portal.manage_connection')}</h1>
                    <p className="page-subtitle">{t('portal.subtitle')}</p>
                    {challengeId === null ? (
                        <form onSubmit={requestOtp} className="mt-8 space-y-5">
                            <label className="block">
                                <span className="field-label">{t('Phone number')}</span>
                                <input
                                    required
                                    value={phone}
                                    onChange={(event) => setPhone(event.target.value)}
                                    className="field"
                                    placeholder="+961 70 123 456"
                                />
                            </label>
                            <button type="submit" disabled={busy} className="button-primary w-full justify-center">
                                {busy ? t('portal.sending') : t('portal.send_code')}
                                <ArrowRight size={16} />
                            </button>
                        </form>
                    ) : (
                        <form onSubmit={verifyOtp} className="mt-8 space-y-5">
                            <label className="block">
                                <span className="field-label">{t('portal.verification_code')}</span>
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
                            <button type="submit" disabled={busy} className="button-primary w-full justify-center">
                                {busy ? t('portal.checking') : t('portal.open_portal')}
                                <ArrowRight size={16} />
                            </button>
                            <button
                                type="button"
                                onClick={() => setChallengeId(null)}
                                className="button-quiet w-full justify-center"
                            >
                                {t('portal.different_number')}
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
