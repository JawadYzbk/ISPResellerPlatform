import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, CheckCircle2, ExternalLink, XCircle } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Status = 'PASS' | 'WARN' | 'FAIL';
type Check = { name: string; status: Status; detail: string };
type Props = { checks: Check[] };

const links: Record<string, string> = {
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
    'Stripe gateway': '/settings/integrations#stripe',
    'Whish Pay gateway': '/settings/integrations#whish',
    'WhatsApp channel': '/settings/whatsapp',
};

const steps = [
    {
        number: '01',
        title: 'Workspace identity',
        detail: 'Name the workspace, upload its logo, and choose locale and timezone.',
        href: '/settings/general#workspace-identity',
    },
    {
        number: '02',
        title: 'Locations and staff',
        detail: 'Create the default branch and service zone, then invite your operators.',
        href: '/settings/locations',
    },
    {
        number: '03',
        title: 'Currencies and plans',
        detail: 'Choose base and collection currencies, import FX rates, and publish a billable plan price.',
        href: '/settings/general#money-display',
    },
    {
        number: '04',
        title: 'Payment integrations',
        detail: 'Configure Stripe or Whish without editing .env, while keeping cash collection available.',
        href: '/settings/integrations',
    },
    {
        number: '05',
        title: 'Messaging and delivery',
        detail: 'Configure WhatsApp, pair each account with its QR code, and assign delivery jobs.',
        href: '/settings/whatsapp',
    },
];

function StatusIcon({ status }: { status: Status }) {
    if (status === 'PASS') return <CheckCircle2 size={19} className="shrink-0 text-emerald-700" />;
    if (status === 'WARN') return <AlertTriangle size={19} className="shrink-0 text-amber-700" />;

    return <XCircle size={19} className="shrink-0 text-coral" />;
}

export default function Setup({ checks }: Props) {
    const failed = checks.filter((check) => check.status === 'FAIL').length;
    const passed = checks.filter((check) => check.status === 'PASS').length;

    return (
        <AppLayout>
            <Head title="First-time setup" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Workspace settings
            </Link>
            <div className="max-w-5xl">
                <p className="eyebrow">Workspace launch</p>
                <h1 className="page-title">First-time setup</h1>
                <p className="page-subtitle">
                    Set up the workspace in the order operators need it. Nothing is considered ready until the live
                    tenant checks below pass.
                </p>

                <div className="mt-6 grid gap-4 sm:grid-cols-3">
                    <div className="card p-5">
                        <p className="eyebrow">Checks passing</p>
                        <p className="mt-2 text-2xl font-semibold text-emerald-700">{passed}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">Action required</p>
                        <p className="mt-2 text-2xl font-semibold text-coral">{failed}</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">Launch rule</p>
                        <p className="mt-2 text-sm font-semibold">Resolve failed checks before pilot</p>
                    </div>
                </div>

                <section className="mt-6 grid gap-4 md:grid-cols-2">
                    {steps.map((step) => (
                        <Link
                            key={step.number}
                            href={step.href}
                            className="card group p-5 transition hover:-translate-y-0.5 hover:border-brand/40"
                        >
                            <div className="flex items-start gap-4">
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-sm font-bold text-brand">
                                    {step.number}
                                </span>
                                <div>
                                    <h2 className="text-base font-semibold group-hover:text-brand">{step.title}</h2>
                                    <p className="mt-1 text-sm text-muted">{step.detail}</p>
                                    <span className="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand">
                                        Open setup <ExternalLink size={13} />
                                    </span>
                                </div>
                            </div>
                        </Link>
                    ))}
                </section>

                <section className="card mt-6 overflow-hidden">
                    <div className="border-b border-line px-5 py-5">
                        <p className="eyebrow">Live readiness</p>
                        <h2 className="mt-1 text-base font-semibold">Tenant launch checks</h2>
                        <p className="mt-1 text-sm text-muted">
                            These are the same checks used by the release handoff command.
                        </p>
                    </div>
                    <div className="divide-y divide-line">
                        {checks.map((check) => (
                            <div key={check.name} className="flex items-start gap-3 px-5 py-4">
                                <StatusIcon status={check.status} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-semibold">{check.name}</p>
                                    <p className="mt-1 text-sm text-muted">{check.detail}</p>
                                    {links[check.name] && check.status !== 'PASS' ? (
                                        <Link
                                            href={links[check.name]}
                                            className="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand"
                                        >
                                            Fix this check <ExternalLink size={13} />
                                        </Link>
                                    ) : null}
                                </div>
                                <span
                                    className={`shrink-0 text-xs font-semibold ${check.status === 'PASS' ? 'text-emerald-700' : check.status === 'WARN' ? 'text-amber-700' : 'text-coral'}`}
                                >
                                    {check.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
