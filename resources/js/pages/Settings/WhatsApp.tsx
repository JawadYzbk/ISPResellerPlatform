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
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useMediaQuery } from '@/hooks/useMediaQuery';
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
    qr_code: string | null;
    is_active: boolean;
};

type Props = { setup: Setup };

const jobOptions = [
    { value: 'general', label: 'General delivery' },
    { value: 'billing', label: 'Billing and receipts' },
    { value: 'collections', label: 'Collections' },
    { value: 'support', label: 'Support and incidents' },
    { value: 'operations', label: 'Operations' },
    { value: 'marketing', label: 'Marketing' },
] as const;

type SelectOption = { value: string; label: string };

type ResponsiveSelectProps = {
    name?: string;
    options: readonly SelectOption[];
    value: string;
    onValueChange: (value: string) => void;
};

function ResponsiveSelect({ name, options, value, onValueChange }: ResponsiveSelectProps) {
    const isMobile = useMediaQuery('(max-width: 767px)');

    if (isMobile) {
        return (
            <select className="field" name={name} value={value} onChange={(event) => onValueChange(event.target.value)}>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        );
    }

    return (
        <>
            <Select value={value} onValueChange={onValueChange}>
                <SelectTrigger aria-label={name}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {name && <input type="hidden" name={name} value={value} />}
        </>
    );
}

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

    useEffect(() => {
        if (
            setup.mode !== 'web' ||
            (setup.accounts.length > 0 && setup.accounts.every((account) => account.status === 'ready'))
        ) {
            return;
        }

        const interval = window.setInterval(() => {
            router.reload({ only: ['setup'] });
        }, 5000);

        return () => window.clearInterval(interval);
    }, [setup.mode, setup.status, setup.accounts]);

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
        router.patch(
            `/settings/whatsapp/accounts/${account.id}`,
            {
                label: String(data.get('label') ?? ''),
                job: String(data.get('job') ?? 'general'),
            },
            { preserveScroll: true },
        );
    };

    const disconnectAccount = (account: WhatsAppAccount) => {
        router.post(`/settings/whatsapp/accounts/${account.id}/disconnect`, {}, { preserveScroll: true });
    };

    const requestDeleteAccount = (account: WhatsAppAccount) => {
        if (setup.accounts.length <= 1) {
            return;
        }

        setAccountToDelete(account);
    };

    const deleteAccount = () => {
        if (!accountToDelete) {
            return;
        }

        const account = accountToDelete;
        setAccountToDelete(null);
        router.delete(`/settings/whatsapp/accounts/${account.id}`, { preserveScroll: true });
    };

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        createForm.post('/settings/whatsapp/accounts', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
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
                            {setup.mode === 'web' ? 'WhatsApp Web.js' : 'Cloud API'}
                        </p>
                        <p className="mt-1 text-sm text-muted">{setup.enabled ? t('Enabled') : t('Not enabled')}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">{t('Bridge status')}</p>
                        <p
                            className={`mt-3 text-lg font-semibold ${problem ? 'text-coral' : ready ? 'text-emerald-700' : 'text-amber-700'}`}
                        >
                            {statusLabels[setup.status] ?? setup.status}
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
                                    ? 'On the phone for this workspace, open WhatsApp → Linked devices → Link a device, then scan the QR code shown here.'
                                    : setup.status === 'ready'
                                      ? 'This workspace is already paired. The QR code appears when the private bridge needs a new device link.'
                                      : 'The bridge status refreshes automatically while it starts or waits for pairing. You can also refresh manually.'
                                : 'Cloud API credentials are managed in the deployment environment. No private token is stored in tenant settings.'}
                        </p>
                        {setup.detail && (
                            <p className="mt-4 rounded-xl bg-sand px-4 py-3 text-sm text-muted">{setup.detail}</p>
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

                {setup.mode === 'web' && (
                    <section className="card mt-6 p-6">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p className="eyebrow">{t('Workspace accounts')}</p>
                                <h2 className="section-title mt-2">{t('Pair and assign delivery accounts')}</h2>
                                <p className="mt-2 text-sm text-muted">
                                    Each account has its own private bridge session. Assign a job so billing, support,
                                    or operations messages use the intended phone number.
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
                                    className="field"
                                    placeholder={t('Billing phone')}
                                    value={createForm.data.label}
                                    onChange={(event) => createForm.setData('label', event.target.value)}
                                />
                                {createForm.errors.label && <p className="field-error">{createForm.errors.label}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('Assigned job')}</span>
                                <ResponsiveSelect
                                    name="job"
                                    options={jobOptions}
                                    value={createForm.data.job}
                                    onValueChange={(value) => createForm.setData('job', value)}
                                />
                                {createForm.errors.job && <p className="field-error">{createForm.errors.job}</p>}
                            </label>
                            <button className="button-primary" disabled={createForm.processing}>
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
                                            {statusLabels[account.status] ?? account.status}
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
                                                options={jobOptions}
                                                value={accountJobs[account.id] ?? account.job}
                                                onValueChange={(value) =>
                                                    setAccountJobs((current) => ({ ...current, [account.id]: value }))
                                                }
                                            />
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
                                            disabled={setup.accounts.length <= 1}
                                            title={
                                                setup.accounts.length <= 1
                                                    ? 'Keep one WhatsApp account configured for this workspace.'
                                                    : 'Delete this WhatsApp account and its private bridge session.'
                                            }
                                            onClick={() => requestDeleteAccount(account)}
                                        >
                                            <Trash2 size={16} /> {t('Delete account')}
                                        </button>
                                        {account.last_ready_at && (
                                            <span className="text-xs text-muted">
                                                Last ready {new Date(account.last_ready_at).toLocaleString()}
                                            </span>
                                        )}
                                    </div>

                                    {account.qr_code && (
                                        <div className="mt-4 flex items-center gap-4 rounded-xl border border-line bg-sand/50 p-3">
                                            <img
                                                src={account.qr_code}
                                                alt={`Scan to pair ${account.label}`}
                                                className="size-36 rounded-lg bg-white p-2"
                                            />
                                            <p className="text-xs text-muted">
                                                On this account's phone, open WhatsApp → Linked devices → Link a device,
                                                then scan this QR code.
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
                                    ? `This removes ${accountToDelete.label}, its bridge session, and its pairing data. Message history stays in the ledger.`
                                    : 'This removes the selected WhatsApp account and its bridge session.'}
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
                            Verify delivery to a dedicated operator phone before enabling customer notifications. The
                            recipient is normalized server-side and the test is recorded in the message ledger.
                        </p>
                    </div>
                    <form
                        onSubmit={submitTest}
                        className="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_220px_auto] sm:items-end"
                    >
                        <label className="min-w-0 flex-1">
                            <span className="field-label">{t('Recipient phone with country code')}</span>
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
                        {setup.accounts.length > 0 && (
                            <label>
                                <span className="field-label">{t('Send through')}</span>
                                <ResponsiveSelect
                                    name="account_id"
                                    options={setup.accounts.map((account) => ({
                                        value: account.id,
                                        label: account.label,
                                    }))}
                                    value={testForm.data.account_id}
                                    onValueChange={(value) => testForm.setData('account_id', value)}
                                />
                            </label>
                        )}
                        <button className="button-primary" disabled={testForm.processing || !ready}>
                            {t('Send test message')}
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
                        <QrCode size={16} className="text-brand" /> {t('Operational boundary')}
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
