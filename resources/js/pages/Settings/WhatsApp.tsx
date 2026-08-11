import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, MessageCircle, QrCode, RefreshCw, ShieldAlert, WifiOff } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Setup = {
    mode: 'cloud' | 'web';
    enabled: boolean;
    configured: boolean;
    status: string;
    detail: string | null;
    qr_code: string | null;
    webhook_configured: boolean;
};

type Props = { setup: Setup };

const statusLabels: Record<string, string> = {
    configured: 'Configured',
    disabled: 'Disabled',
    not_configured: 'Needs configuration',
    starting: 'Starting',
    qr: 'Waiting for QR scan',
    authenticated: 'Authenticated',
    ready: 'Ready',
    auth_failure: 'Authentication failed',
    disconnected: 'Disconnected',
    unreachable: 'Bridge unreachable',
    unknown: 'Unknown',
};

export default function WhatsAppSettings({ setup }: Props) {
    const ready = setup.status === 'ready' || setup.status === 'configured';
    const problem =
        setup.status === 'unreachable' || setup.status === 'auth_failure' || setup.status === 'disconnected';
    const testForm = useForm({ phone: '' });

    const submitTest = (event: React.FormEvent) => {
        event.preventDefault();
        testForm.post('/settings/whatsapp/test', {
            preserveScroll: true,
            onSuccess: () => testForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title="WhatsApp setup" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to workspace settings
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">Messaging setup</p>
                <h1 className="page-title">WhatsApp delivery</h1>
                <p className="page-subtitle">
                    Check the configured provider and pair the private WhatsApp Web.js bridge without exposing its token
                    to the browser.
                </p>

                <div className="mt-8 grid gap-5 sm:grid-cols-3">
                    <div className="card p-5">
                        <p className="eyebrow">Provider</p>
                        <p className="mt-3 text-lg font-semibold">
                            {setup.mode === 'web' ? 'WhatsApp Web.js' : 'Cloud API'}
                        </p>
                        <p className="mt-1 text-sm text-muted">{setup.enabled ? 'Enabled' : 'Not enabled'}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">Bridge status</p>
                        <p
                            className={`mt-3 text-lg font-semibold ${problem ? 'text-coral' : ready ? 'text-emerald-700' : 'text-amber-700'}`}
                        >
                            {statusLabels[setup.status] ?? setup.status}
                        </p>
                        <p className="mt-1 text-sm text-muted">Status is read server-side.</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">Signed callback</p>
                        <p
                            className={`mt-3 text-lg font-semibold ${setup.webhook_configured ? 'text-emerald-700' : 'text-amber-700'}`}
                        >
                            {setup.webhook_configured ? 'Configured' : 'Missing'}
                        </p>
                        <p className="mt-1 text-sm text-muted">Required for delivery receipts.</p>
                    </div>
                </div>

                <div className="card mt-6 grid gap-8 p-6 sm:grid-cols-[minmax(0,1fr)_240px] sm:items-center">
                    <div>
                        <div className="flex items-center gap-2 text-brand">
                            <MessageCircle size={18} />
                            <h2 className="section-title">Pairing and delivery</h2>
                        </div>
                        <p className="mt-3 text-sm text-muted">
                            {setup.mode === 'web'
                                ? setup.status === 'qr'
                                    ? 'Open WhatsApp on the phone for this workspace and scan the QR code shown here from Linked devices.'
                                    : 'The bridge status is refreshed when this page is opened. Use refresh after starting or pairing the bridge.'
                                : 'Cloud API credentials are managed in the deployment environment. No private token is stored in tenant settings.'}
                        </p>
                        {setup.detail && (
                            <p className="mt-4 rounded-xl bg-sand px-4 py-3 text-sm text-muted">{setup.detail}</p>
                        )}
                        <div className="mt-5 flex flex-wrap gap-2">
                            <Link href="/settings/whatsapp" className="button-secondary">
                                <RefreshCw size={16} /> Refresh status
                            </Link>
                            <Link href="/settings/general" className="button-secondary">
                                Workspace settings
                            </Link>
                        </div>
                    </div>
                    {setup.qr_code ? (
                        <div className="mx-auto rounded-2xl border border-line bg-white p-3 shadow-sm">
                            <img src={setup.qr_code} alt="Scan to pair WhatsApp Web.js" className="block size-48" />
                            <p className="mt-2 text-center text-xs text-muted">
                                QR expires when the bridge refreshes it.
                            </p>
                        </div>
                    ) : (
                        <div className="mx-auto grid size-48 place-items-center rounded-2xl border border-dashed border-line bg-sand text-center">
                            {problem ? (
                                <WifiOff size={28} className="text-coral" />
                            ) : ready ? (
                                <CheckCircle2 size={28} className="text-emerald-700" />
                            ) : (
                                <ShieldAlert size={28} className="text-amber-700" />
                            )}
                            <span className="sr-only">{statusLabels[setup.status] ?? setup.status}</span>
                        </div>
                    )}
                </div>

                <section className="card mt-6 p-6">
                    <div>
                        <p className="eyebrow">Controlled delivery check</p>
                        <h2 className="section-title mt-2">Send one test message</h2>
                        <p className="mt-2 text-sm text-muted">
                            Verify delivery to a dedicated operator phone before enabling customer notifications. The
                            recipient is normalized server-side and the test is recorded in the message ledger.
                        </p>
                    </div>
                    <form onSubmit={submitTest} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label className="min-w-0 flex-1">
                            <span className="field-label">Recipient phone with country code</span>
                            <input
                                className="field"
                                type="tel"
                                inputMode="tel"
                                placeholder="+961 70 123 456"
                                value={testForm.data.phone}
                                onChange={(event) => testForm.setData('phone', event.target.value)}
                            />
                            {testForm.errors.phone && <p className="field-error">{testForm.errors.phone}</p>}
                        </label>
                        <button className="button-primary" disabled={testForm.processing || !ready}>
                            Send test message
                        </button>
                    </form>
                    {!ready && (
                        <p className="mt-3 text-xs text-amber-700">
                            Pair the bridge and wait for a ready status before sending a test message.
                        </p>
                    )}
                </section>

                <div className="mt-6 rounded-xl border border-line bg-white px-5 py-4 text-sm text-muted">
                    <div className="flex items-center gap-2 font-semibold text-ink">
                        <QrCode size={16} className="text-brand" /> Operational boundary
                    </div>
                    <p className="mt-2">
                        WhatsApp Web.js is an unofficial client. Pair a dedicated business account, keep the bridge
                        session volume private, and verify a controlled test message before enabling customer
                        notifications.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
