import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Archive,
    ArrowLeft,
    CheckCircle2,
    ExternalLink,
    LoaderCircle,
    RefreshCw,
    XCircle,
} from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Status = 'PASS' | 'WARN' | 'FAIL';
type Check = { name: string; status: Status; detail: string };
type ProviderStatus = 'ready' | 'disabled' | 'not_configured' | 'failed';
type ProviderCheck = { status: ProviderStatus; detail: string };
type BackupDestination = {
    name: string;
    disk: string;
    reachable: boolean;
    healthy: boolean;
    backup_count: number;
    newest_at: string | null;
    newest_age_hours: number | null;
    used_storage_bytes: number;
    failures: { check: string; message: string }[];
};
type BackupHealth = {
    status: Status;
    detail: string;
    checked_at: string;
    verify_backup: boolean;
    encryption: string;
    destinations: BackupDestination[];
};
type Props = {
    overall: Status;
    checks: Check[];
    providerChecks?: Record<string, ProviderCheck> | null;
    backupHealth: BackupHealth;
};

const checkLinks: Record<string, string> = {
    'Tenant status': '/settings/general',
    'Owner capability': '/settings/users',
    'Default branch': '/settings/locations',
    'Service zone': '/settings/locations',
    'Base currency': '/settings/general#money-display',
    'Collection currency': '/settings/general#money-display',
    'Collection FX rate': '/billing/exchange-rates',
    'Billable plan': '/plans',
    'Notification templates': '/settings/ticket-responses',
    'Tenant logo': '/settings/general#workspace-identity',
    'Cash collection': '/billing/shifts',
    'Stripe gateway': '/settings/general#payment-channels',
    'Whish Pay gateway': '/settings/general#payment-channels',
    'WhatsApp channel': '/settings/whatsapp',
    'Backup health': '/settings/readiness#backup-health',
};

const statusCopy: Record<Status, { label: string; detail: string; className: string }> = {
    PASS: {
        label: 'Ready',
        detail: 'All required checks are passing.',
        className: 'text-emerald-700',
    },
    WARN: {
        label: 'Ready with warnings',
        detail: 'Core operations pass; review the remaining handoff warnings.',
        className: 'text-amber-700',
    },
    FAIL: {
        label: 'Action required',
        detail: 'Resolve failed checks before starting the pilot.',
        className: 'text-coral',
    },
};

function StatusIcon({ status }: { status: Status }) {
    if (status === 'PASS') return <CheckCircle2 size={20} className="shrink-0 text-emerald-700" />;
    if (status === 'WARN') return <AlertTriangle size={20} className="shrink-0 text-amber-700" />;

    return <XCircle size={20} className="shrink-0 text-coral" />;
}

const providerLabels: Record<string, string> = {
    frankfurter: 'Frankfurter FX',
    stripe: 'Stripe',
    whish: 'Whish Pay',
    whatsapp_web: 'WhatsApp Web.js',
};

function ProviderIcon({ status }: { status: ProviderStatus }) {
    if (status === 'ready') return <CheckCircle2 size={18} className="shrink-0 text-emerald-700" />;
    if (status === 'disabled') return <AlertTriangle size={18} className="shrink-0 text-muted" />;
    if (status === 'not_configured') return <AlertTriangle size={18} className="shrink-0 text-amber-700" />;

    return <XCircle size={18} className="shrink-0 text-coral" />;
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;

    return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}

function formatBackupDate(value: string | null, locale: string, translate: (key: string) => string): string {
    if (!value) return translate('No archive found');

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? translate('Unknown') : date.toLocaleString(locale);
}

export default function Readiness({ overall, checks, providerChecks = null, backupHealth }: Props) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const summary = statusCopy[overall];
    const providerForm = useForm({});
    const backupForm = useForm({});

    return (
        <AppLayout>
            <Head title={t('Pilot readiness')} />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to workspace settings')}
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">{t('Handoff checklist')}</p>
                <h1 className="page-title">{t('Pilot readiness')}</h1>
                <p className="page-subtitle">
                    {t(
                        'Review the same tenant, billing, messaging, and provider checks used by the release handoff command.',
                    )}
                </p>

                <section className="card mt-6 flex items-start gap-4 p-6">
                    <StatusIcon status={overall} />
                    <div>
                        <p className="eyebrow">{t('Overall status')}</p>
                        <h2 className={`mt-2 text-xl font-semibold ${summary.className}`}>{t(summary.label)}</h2>
                        <p className="mt-1 text-sm text-muted">{t(summary.detail)}</p>
                    </div>
                </section>

                <section className="card mt-6 divide-y divide-line overflow-hidden">
                    {checks.map((check) => {
                        const link = checkLinks[check.name];

                        return (
                            <div key={check.name} className="flex items-start gap-4 px-5 py-4">
                                <StatusIcon status={check.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold">{t(check.name)}</p>
                                    <p className="mt-1 text-sm text-muted">{t(check.detail)}</p>
                                    {link && check.status !== 'PASS' && (
                                        <Link
                                            href={link}
                                            className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand"
                                        >
                                            {t('Review setup')} <ExternalLink size={13} />
                                        </Link>
                                    )}
                                </div>
                                <span
                                    className={`shrink-0 text-xs font-semibold ${
                                        check.status === 'PASS'
                                            ? 'text-emerald-700'
                                            : check.status === 'WARN'
                                              ? 'text-amber-700'
                                              : 'text-coral'
                                    }`}
                                >
                                    {check.status}
                                </span>
                            </div>
                        );
                    })}
                </section>

                <section id="backup-health" className="card mt-6 overflow-hidden">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line px-5 py-5">
                        <div className="flex items-start gap-3">
                            <Archive className="mt-0.5 shrink-0 text-brand" size={19} />
                            <div>
                                <p className="eyebrow">{t('Recovery')}</p>
                                <h2 className="mt-1 text-base font-semibold">{t('Backup health')}</h2>
                                <p className="mt-1 text-sm text-muted text-pretty">{t(backupHealth.detail)}</p>
                            </div>
                        </div>
                        <span className={`text-xs font-semibold ${statusCopy[backupHealth.status].className}`}>
                            {backupHealth.status}
                        </span>
                    </div>
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-sand/30 px-5 py-4">
                        <p className="max-w-2xl text-sm text-muted">
                            {t(
                                'Create a fresh encrypted archive after configuration changes. Restore operations stay server-controlled and follow the documented isolated restore runbook.',
                            )}
                        </p>
                        <button
                            type="button"
                            className="button-secondary inline-flex items-center gap-2"
                            disabled={backupForm.processing}
                            onClick={() => backupForm.post('/settings/readiness/backup', { preserveScroll: true })}
                        >
                            {backupForm.processing ? (
                                <LoaderCircle size={16} className="animate-spin" />
                            ) : (
                                <Archive size={16} />
                            )}
                            {backupForm.processing ? t('Creating backup…') : t('Create backup now')}
                        </button>
                    </div>
                    <div className="grid gap-3 border-b border-line px-5 py-4 text-sm sm:grid-cols-3">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                {t('Archive verification')}
                            </p>
                            <p className="mt-1 font-semibold">
                                {backupHealth.verify_backup ? t('Enabled') : t('Disabled')}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted">
                                {t('Encryption')}
                            </p>
                            <p className="mt-1 font-semibold">{backupHealth.encryption}</p>
                        </div>
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-muted">{t('Checked')}</p>
                            <p className="mt-1 font-semibold">
                                {formatBackupDate(backupHealth.checked_at, page.props.app.locale, t)}
                            </p>
                        </div>
                    </div>
                    <div className="divide-y divide-line">
                        {backupHealth.destinations.map((destination) => {
                            const destinationStatus: Status = destination.healthy
                                ? 'PASS'
                                : destination.reachable
                                  ? 'WARN'
                                  : 'FAIL';

                            return (
                                <div key={`${destination.name}-${destination.disk}`} className="px-5 py-4">
                                    <div className="flex items-start gap-3">
                                        <StatusIcon status={destinationStatus} />
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                                <p className="text-sm font-semibold">{destination.name}</p>
                                                <span className="text-xs font-medium text-muted">
                                                    {destination.disk}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-sm text-muted">
                                                {destination.backup_count} {t('archive(s)')} · {t('Latest')}:{' '}
                                                {formatBackupDate(destination.newest_at, page.props.app.locale, t)} ·{' '}
                                                {formatBytes(destination.used_storage_bytes)}
                                            </p>
                                            {destination.failures.length > 0 && (
                                                <div className="mt-2 space-y-1 text-xs text-amber-800">
                                                    {destination.failures.map((failure) => (
                                                        <p key={`${destination.disk}-${failure.check}`}>
                                                            {t(failure.message)}
                                                        </p>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <section className="card mt-6 overflow-hidden">
                    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-line px-5 py-5">
                        <div>
                            <p className="eyebrow">{t('External services')}</p>
                            <h2 className="mt-1 text-base font-semibold">{t('Provider connectivity')}</h2>
                            <p className="mt-1 text-sm text-muted">
                                {t(
                                    'Read-only probes use server-side credentials and never create a payment or send a message.',
                                )}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="button-secondary inline-flex items-center gap-2"
                            disabled={providerForm.processing}
                            onClick={() =>
                                providerForm.post('/settings/readiness/provider-check', { preserveScroll: true })
                            }
                        >
                            {providerForm.processing ? (
                                <LoaderCircle size={16} className="animate-spin" />
                            ) : (
                                <RefreshCw size={16} />
                            )}
                            {providerForm.processing ? t('Checking…') : t('Run provider checks')}
                        </button>
                    </div>
                    {providerChecks ? (
                        <div className="divide-y divide-line">
                            {Object.entries(providerChecks).map(([provider, check]) => (
                                <div key={provider} className="flex items-start gap-3 px-5 py-4">
                                    <ProviderIcon status={check.status} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-semibold">
                                            {t(providerLabels[provider] ?? provider)}
                                        </p>
                                        <p className="mt-1 text-sm text-muted">{check.detail}</p>
                                    </div>
                                    <span
                                        className={`shrink-0 text-xs font-semibold ${
                                            check.status === 'ready'
                                                ? 'text-emerald-700'
                                                : check.status === 'disabled'
                                                  ? 'text-muted'
                                                  : check.status === 'not_configured'
                                                    ? 'text-amber-700'
                                                    : 'text-coral'
                                        }`}
                                    >
                                        {check.status.replace('_', ' ').toUpperCase()}
                                    </span>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <p className="px-5 py-5 text-sm text-muted">
                            {t('Run the probe after loading your provider configuration.')}
                        </p>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
