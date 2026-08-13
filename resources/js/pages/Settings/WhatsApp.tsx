import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Link2Off,
    MessageCircle,
    Plus,
    QrCode,
    RefreshCw,
    Save,
    ShieldAlert,
    Trash2,
    WifiOff,
} from 'lucide-react';
import { useEffect, useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Setup = {
    mode: 'cloud' | 'web';
    enabled: boolean;
    configured: boolean;
    status: string;
    detail: string | null;
    qr_code: string | null;
    webhook_configured: boolean;
    accounts: WhatsAppAccount[];
};

type WhatsAppAccount = {
    id: string;
    label: string;
    job: string;
    status: string;
    phone: string | null;
    push_name: string | null;
    last_error: string | null;
    last_ready_at: string | null;
    next_send_at: string | null;
    cooldown_until: string | null;
    failure_streak: number;
    qr_code: string | null;
    is_active: boolean;
};

type Props = { setup: Setup };

const jobOptions = (t: (key: string) => string) =>
    [
        { value: 'general', label: t('General delivery') },
        { value: 'billing', label: t('Billing and receipts') },
        { value: 'collections', label: t('Collections') },
        { value: 'support', label: t('Support and incidents') },
        { value: 'operations', label: t('Operations') },
        { value: 'marketing', label: t('Marketing') },
    ] as const;

const statusLabels: Record<string, string> = {
    configured: 'Configured',
    disabled: 'Disabled',
    not_configured: 'Needs configuration',
    starting: 'Starting',
    idle: 'Waiting to start',
    qr: 'Waiting for QR scan',
    authenticated: 'Authenticated',
    ready: 'Ready',
    auth_failure: 'Authentication failed',
    disconnected: 'Disconnected',
    unreachable: 'Bridge unreachable',
    unknown: 'Unknown',
};

export default function WhatsAppSettings({ setup }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const jobs = jobOptions(t);
    const statusLabel = (status: string) => t(statusLabels[status] ?? status);
    const ready =
        setup.mode === 'web'
            ? setup.accounts.some((account) => account.status === 'ready')
            : setup.status === 'configured';
    const problem =
        setup.status === 'unreachable' || setup.status === 'auth_failure' || setup.status === 'disconnected';
    const testAccount = setup.accounts.find((account) => account.status === 'ready') ?? setup.accounts[0];
    const testForm = useForm({ phone: '', account_id: testAccount?.id ?? '' });
    const createForm = useForm({ label: '', job: 'general' });
    const [accountJobs, setAccountJobs] = useState<Record<string, string>>({});
    const [accountToDelete, setAccountToDelete] = useState<WhatsAppAccount | null>(null);
    const [accountMutationPending, setAccountMutationPending] = useState(false);
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;

    useEffect(() => {
        if (
            setup.mode !== 'web' ||
            (setup.accounts.length > 0 && setup.accounts.every((account) => account.status === 'ready')) ||
            accountMutationPending
        ) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ['setup'] });
        }, 5000);

        return () => window.clearInterval(interval);
    }, [accountMutationPending, setup.mode, setup.status, setup.accounts]);

    const beginAccountMutation = () => {
        router.cancelAll({ async: true });
        setAccountMutationPending(true);
    };

    const finishAccountMutation = () => {
        setAccountMutationPending(false);
    };

    const submitTest = (event: React.FormEvent) => {
        event.preventDefault();
        testForm.post('/settings/whatsapp/test', {
            preserveScroll: true,
            onSuccess: () => testForm.reset(),
        });
    };

    const submitAccountUpdate = (event: React.FormEvent<HTMLFormElement>, account: WhatsAppAccount) => {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        beginAccountMutation();
        router.patch(
            `/settings/whatsapp/accounts/${account.id}`,
            {
                label: String(data.get('label') ?? ''),
                job: String(data.get('job') ?? 'general'),
            },
            { preserveScroll: true, onFinish: finishAccountMutation },
        );
    };

    const disconnectAccount = (account: WhatsAppAccount) => {
        beginAccountMutation();
        router.post(
            `/settings/whatsapp/accounts/${account.id}/disconnect`,
            {},
            {
                preserveScroll: true,
                onFinish: finishAccountMutation,
            },
        );
    };

    const requestDeleteAccount = (account: WhatsAppAccount) => {
        setAccountToDelete(account);
    };

    const deleteAccount = () => {
        if (!accountToDelete) {
            return;
        }

        const account = accountToDelete;
        setAccountToDelete(null);
        beginAccountMutation();
        router.delete(`/settings/whatsapp/accounts/${account.id}`, {
            preserveScroll: true,
            onFinish: finishAccountMutation,
        });
    };

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        beginAccountMutation();
        createForm.post('/settings/whatsapp/accounts', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
            onFinish: finishAccountMutation,
        });
    };

    return (
        <AppLayout>
            <Head title={t('WhatsApp setup')} />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to workspace settings')}
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">{t('Messaging setup')}</p>
                <h1 className="page-title">{t('WhatsApp delivery')}</h1>
                <p className="page-subtitle">
                    {t(
                        'Check the configured provider and pair the private WhatsApp Web.js bridge without exposing its token to the browser.',
                    )}
                </p>

                <div className="mt-8 grid gap-5 sm:grid-cols-3">
                    <div className="card p-5">
                        <p className="eyebrow">{t('Provider')}</p>
                        <p className="mt-3 text-lg font-semibold">
                            {setup.mode === 'web' ? t('WhatsApp Web.js') : t('Cloud API')}
                        </p>
                        <p className="mt-1 text-sm text-muted">{setup.enabled ? t('Enabled') : t('Not enabled')}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">{t('Bridge status')}</p>
                        <p
                            className={`mt-3 text-lg font-semibold ${problem ? 'text-coral' : ready ? 'text-emerald-700' : 'text-amber-700'}`}
                        >
                            {statusLabel(setup.status)}
                        </p>
                        <p className="mt-1 text-sm text-muted">{t('Status is read server-side.')}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">{t('Signed callback')}</p>
                        <p
                            className={`mt-3 text-lg font-semibold ${setup.webhook_configured ? 'text-emerald-700' : 'text-amber-700'}`}
                        >
                            {setup.webhook_configured ? t('Configured') : t('Missing')}
                        </p>
                        <p className="mt-1 text-sm text-muted">{t('Required for delivery receipts.')}</p>
                    </div>
                </div>

                <div className="card mt-6 grid gap-8 p-6 sm:grid-cols-[minmax(0,1fr)_240px] sm:items-center">
                    <div>
                        <div className="flex items-center gap-2 text-brand">
                            <MessageCircle size={18} />
                            <h2 className="section-title">{t('Pairing and delivery')}</h2>
                        </div>
                        <p className="mt-3 text-sm text-muted">
                            {setup.mode === 'web'
                                ? setup.status === 'qr'
                                    ? `${t('On this workspace phone, open WhatsApp')} → ${t('Linked devices')} → ${t('Link a device')}, ${t('then scan the QR code shown here.')}`
                                    : setup.status === 'ready'
                                      ? t(
                                            'This workspace is already paired. The QR code appears when the private bridge needs a new device link.',
                                        )
                                      : t(
                                            'The bridge status refreshes automatically while it starts or waits for pairing. You can also refresh manually.',
                                        )
                                : t(
                                      'Cloud API credentials are managed in the deployment environment. No private token is stored in tenant settings.',
                                  )}
                        </p>
                        {setup.detail && (
                            <p className="mt-4 rounded-xl bg-sand px-4 py-3 text-sm text-muted">
                                {setup.detail ? t(setup.detail) : null}
                            </p>
                        )}
                        <div className="mt-5 flex flex-wrap gap-2">
                            <Link href="/settings/whatsapp" className="button-secondary">
                                <RefreshCw size={16} /> {t('Refresh status')}
                            </Link>
                            <Link href="/settings/general" className="button-secondary">
                                {t('Workspace settings')}
                            </Link>
                        </div>
                    </div>
                    {setup.qr_code ? (
                        <div className="mx-auto rounded-2xl border border-line bg-white p-3 shadow-sm">
                            <img src={setup.qr_code} alt={t('Scan to pair WhatsApp Web.js')} className="block size-48" />
                            <p className="mt-2 text-center text-xs text-muted">
                                {t('QR expires when the bridge refreshes it.')}
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
                            <span className="sr-only">{statusLabel(setup.status)}</span>
                        </div>
                    )}
                </div>

                {setup.mode === 'web' && (
                    <section className="card mt-6 p-6">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="eyebrow">{t('Workspace accounts')}</p>
                                <h2 className="section-title mt-2">{t('Pair and assign delivery accounts')}</h2>
                                <p className="mt-2 text-sm text-muted">
                                    {t(
                                        'Each account has its own private bridge session. Assign a job so billing, support, or operations messages use the intended phone number.',
                                    )}
                                </p>
                            </div>
                        </div>

                        <form
                            onSubmit={submitCreate}
                            className="mt-5 grid gap-3 rounded-xl border border-line bg-sand/50 p-4 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end"
                        >
                            <label>
                                <span className="field-label">{t('Account label')}</span>
                                <input
                                    id="account-label"
                                    className="field"
                                    {...fieldA11y('account-label', createForm.errors.label)}
                                    placeholder={t('Billing phone')}
                                    value={createForm.data.label}
                                    onChange={(event) => createForm.setData('label', event.target.value)}
                                />
                                {fieldError('account-label', createForm.errors.label)}
                            </label>
                            <label>
                                <span className="field-label">{t('Assigned job')}</span>
                                <ResponsiveSelect
                                    id="account-job"
                                    name="job"
                                    {...fieldA11y('account-job', createForm.errors.job)}
                                    value={createForm.data.job}
                                    onChange={(event) => createForm.setData('job', event.target.value)}
                                >
                                    {jobs.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                                {fieldError('account-job', createForm.errors.job)}
                            </label>
                            <button type="submit" className="button-primary" disabled={createForm.processing}>
                                <Plus size={16} /> {t('Add account')}
                            </button>
                        </form>

                        <div className="mt-5 grid gap-4 lg:grid-cols-2">
                            {setup.accounts.map((account) => (
                                <div key={account.id} className="rounded-2xl border border-line bg-white p-5">
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="min-w-0">
                                            <p className="text-lg font-semibold">{account.label}</p>
                                            <p className="mt-1 text-sm text-muted">
                                                {account.phone
                                                    ? `+${account.phone}`
                                                    : t('Phone will appear after pairing')}
                                                {account.push_name ? ` · ${account.push_name}` : ''}
                                            </p>
                                        </div>
                                        <span
                                            className={`shrink-0 text-xs font-semibold ${account.status === 'ready' ? 'text-emerald-700' : account.status === 'unreachable' || account.status === 'auth_failure' ? 'text-coral' : 'text-amber-700'}`}
                                        >
                                            {statusLabel(account.status)}
                                        </span>
                                    </div>

                                    <form
                                        onSubmit={(event) => submitAccountUpdate(event, account)}
                                        className="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_190px_auto] sm:items-end"
                                    >
                                        <label>
                                            <span className="field-label">{t('Label')}</span>
                                            <input
                                                className="field"
                                                name="label"
                                                defaultValue={account.label}
                                                maxLength={80}
                                            />
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Job')}</span>
                                            <ResponsiveSelect
                                                name="job"
                                                value={accountJobs[account.id] ?? account.job}
                                                onChange={(event) =>
                                                    setAccountJobs((current) => ({
                                                        ...current,
                                                        [account.id]: event.target.value,
                                                    }))
                                                }
                                            >
                                                {jobs.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </ResponsiveSelect>
                                        </label>
                                        <button className="button-secondary" type="submit">
                                            <Save size={16} /> {t('Save')}
                                        </button>
                                    </form>

                                    <div className="mt-4 flex flex-wrap items-center gap-3">
                                        <button
                                            className="button-secondary"
                                            type="button"
                                            onClick={() => disconnectAccount(account)}
                                        >
                                            <Link2Off size={16} /> {t('Disconnect and pair again')}
                                        </button>
                                        <button
                                            className="button-secondary text-coral"
                                            type="button"
                                            title={t('Delete this WhatsApp account and its private bridge session.')}
                                            onClick={() => requestDeleteAccount(account)}
                                        >
                                            <Trash2 size={16} /> {t('Delete account')}
                                        </button>
                                        {account.last_ready_at && (
                                            <span className="text-xs text-muted">
                                                {t('Last ready')} {new Date(account.last_ready_at).toLocaleString()}
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-3 rounded-lg bg-sand/60 px-3 py-2 text-xs text-muted">
                                        <span className="font-semibold text-ink">{t('Delivery safety')}</span>{' '}
                                        {account.cooldown_until
                                            ? `${t('Cooldown until')} ${new Date(account.cooldown_until).toLocaleString()}`
                                            : account.failure_streak > 0
                                              ? `${account.failure_streak} ${t('recent provider failures; delivery remains paced')}`
                                              : t('Paced and duplicate-protected')}
                                    </div>

                                    {account.qr_code && (
                                        <div className="mt-4 flex items-center gap-4 rounded-xl border border-line bg-sand/50 p-3">
                                            <img
                                                src={account.qr_code}
                                                alt={t('Scan to pair') + ' ' + account.label}
                                                className="size-36 rounded-lg bg-white p-2"
                                            />
                                            <p className="text-xs text-muted">
                                                {t('On this account phone, open WhatsApp')} → {t('Linked devices')} →{' '}
                                                {t('Link a device')}, {t('then scan this QR code.')}
                                            </p>
                                        </div>
                                    )}
                                    {account.last_error && (
                                        <p className="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-coral">
                                            {account.last_error}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <AlertDialog
                    open={accountToDelete !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setAccountToDelete(null);
                        }
                    }}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{t('Delete this WhatsApp account?')}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {accountToDelete
                                    ? `${t('This removes the WhatsApp account')} ${accountToDelete.label}. ${t('Message history stays in the ledger.')}`
                                    : t('This removes the selected WhatsApp account and its bridge session.')}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{t('Keep account')}</AlertDialogCancel>
                            <AlertDialogAction onClick={deleteAccount}>{t('Delete account')}</AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <section className="card mt-6 p-6">
                    <div>
                        <p className="eyebrow">{t('Controlled delivery check')}</p>
                        <h2 className="section-title mt-2">{t('Send one test message')}</h2>
                        <p className="mt-2 text-sm text-muted">
                            {t(
                                'Verify delivery to a dedicated operator phone before enabling customer notifications. The recipient is normalized server-side and the test is recorded in the message ledger.',
                            )}
                        </p>
                    </div>
                    <form
                        onSubmit={submitTest}
                        className="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end"
                    >
                        <label className="min-w-0 flex-1">
                            <span className="field-label">{t('Recipient phone with country code')}</span>
                            <input
                                id="test-phone"
                                className="field"
                                type="tel"
                                inputMode="tel"
                                {...fieldA11y('test-phone', testForm.errors.phone)}
                                placeholder="+961 70 123 456"
                                value={testForm.data.phone}
                                onChange={(event) => testForm.setData('phone', event.target.value)}
                            />
                            {fieldError('test-phone', testForm.errors.phone)}
                        </label>
                        {setup.accounts.length > 0 && (
                            <label>
                                <span className="field-label">{t('Send through')}</span>
                                <ResponsiveSelect
                                    id="test-account"
                                    name="account_id"
                                    value={testForm.data.account_id}
                                    onChange={(event) => testForm.setData('account_id', event.target.value)}
                                >
                                    {setup.accounts.map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.label}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                        )}
                        <button type="submit" className="button-primary" disabled={testForm.processing || !ready}>
                            {t('Send test message')}
                        </button>
                    </form>
                    {!ready && (
                        <p className="mt-3 text-xs text-amber-700">
                            {t('Pair the bridge and wait for a ready status before sending a test message.')}
                        </p>
                    )}
                </section>

                <div className="mt-6 rounded-xl border border-line bg-white px-5 py-4 text-sm text-muted">
                    <div className="flex items-center gap-2 font-semibold text-ink">
                        <QrCode size={16} className="text-brand" /> {t('Operational boundary')}
                    </div>
                    <p className="mt-2">
                        {t(
                            'WhatsApp Web.js is an unofficial client. Pair a dedicated business account, keep the bridge session volume private, and verify a controlled test message before enabling customer notifications.',
                        )}
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
