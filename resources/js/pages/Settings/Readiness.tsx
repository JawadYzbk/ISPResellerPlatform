import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, ExternalLink, XCircle } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Status = 'PASS' | 'WARN' | 'FAIL';
type Check = { name: string; status: Status; detail: string };
type Props = { overall: Status; checks: Check[] };

const checkLinks: Record<string, string> = {
    'Owner capability': '/settings/users',
    'Base currency': '/settings/general#money-display',
    'Collection currency': '/settings/general#money-display',
    'Collection FX rate': '/billing/exchange-rates',
    'Tenant logo': '/settings/general#workspace-identity',
    'Stripe gateway': '/settings/general#payment-channels',
    'Whish Pay gateway': '/settings/general#payment-channels',
    'WhatsApp channel': '/settings/whatsapp',
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

export default function Readiness({ overall, checks }: Props) {
    const summary = statusCopy[overall];

    return (
        <AppLayout>
            <Head title="Pilot readiness" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to workspace settings
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">Handoff checklist</p>
                <h1 className="page-title">Pilot readiness</h1>
                <p className="page-subtitle">
                    Review the same tenant, billing, messaging, and provider checks used by the release handoff command.
                </p>

                <section className="card mt-6 flex items-start gap-4 p-6">
                    <StatusIcon status={overall} />
                    <div>
                        <p className="eyebrow">Overall status</p>
                        <h2 className={`mt-2 text-xl font-semibold ${summary.className}`}>{summary.label}</h2>
                        <p className="mt-1 text-sm text-muted">{summary.detail}</p>
                    </div>
                </section>

                <section className="card mt-6 divide-y divide-line overflow-hidden">
                    {checks.map((check) => {
                        const link = checkLinks[check.name];

                        return (
                            <div key={check.name} className="flex items-start gap-4 px-5 py-4">
                                <StatusIcon status={check.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold">{check.name}</p>
                                    <p className="mt-1 text-sm text-muted">{check.detail}</p>
                                    {link && check.status !== 'PASS' && (
                                        <Link
                                            href={link}
                                            className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand"
                                        >
                                            Review setup <ExternalLink size={13} />
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
            </div>
        </AppLayout>
    );
}
