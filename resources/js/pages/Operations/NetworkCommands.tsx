import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, ChevronLeft, ChevronRight, RefreshCw, Terminal } from 'lucide-react';
import { useEffect, useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type NetworkCommand = {
    public_id: string;
    action: string;
    status: 'pending' | 'running' | 'failed' | 'abandoned' | 'completed' | 'awaiting_confirmation' | 'stale';
    attempts: number;
    desired_state_version: number;
    available_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    last_error: string | null;
    service: {
        public_id: string;
        username: string;
        network_state: 'unknown' | 'pending_sync' | 'in_sync' | 'drifted' | 'failed';
        customer: { public_id: string; name: string } | null;
    } | null;
};

type Props = PageProps & {
    commands: Paginator<NetworkCommand>;
    filters: { status?: string; network_state?: string };
    canRetry?: boolean;
};

export default function NetworkCommandsPage({ commands, filters, canRetry = false }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [status, setStatus] = useState(filters.status ?? '');
    const [networkState, setNetworkState] = useState(filters.network_state ?? '');

    useEffect(() => {
        const reloadWhenVisible = () => {
            if (document.visibilityState !== 'visible') return;
            router.reload({ only: ['commands'] });
        };
        const interval = window.setInterval(reloadWhenVisible, 10_000);
        document.addEventListener('visibilitychange', reloadWhenVisible);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', reloadWhenVisible);
        };
    }, []);

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/network-commands',
            { status: status || undefined, network_state: networkState || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('network_commands.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('network_commands.eyebrow')}</p>
                    <h1 className="page-title">{t('network_commands.title')}</h1>
                    <p className="page-subtitle">{t('network_commands.subtitle')}</p>
                </div>
                <Link href="/reports/operations" className="button-secondary">
                    {t('network_commands.report')}
                </Link>
            </div>
            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-52">
                    <span className="field-label">{t('network_commands.command_status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('network_commands.all_statuses')}</option>
                        <option value="pending">{t('Pending')}</option>
                        <option value="running">{t('Running')}</option>
                        <option value="awaiting_confirmation">{t('network_commands.awaiting_confirmation')}</option>
                        <option value="completed">{t('Completed')}</option>
                        <option value="failed">{t('Failed')}</option>
                        <option value="abandoned">{t('Abandoned')}</option>
                        <option value="stale">{t('Stale')}</option>
                    </ResponsiveSelect>
                </label>
                <label className="block sm:min-w-52">
                    <span className="field-label">{t('network_commands.network_state')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={networkState}
                        onChange={(event) => setNetworkState(event.target.value)}
                    >
                        <option value="">{t('network_commands.all_network_states')}</option>
                        <option value="pending_sync">{t('network_commands.pending_sync')}</option>
                        <option value="in_sync">{t('network_commands.in_sync')}</option>
                        <option value="drifted">{t('network_commands.drifted')}</option>
                        <option value="failed">{t('Failed')}</option>
                        <option value="unknown">{t('Unknown')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Terminal size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {commands.total.toLocaleString()} {t('network_commands.count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('network_commands.commercial_note')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Service')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Command')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Network')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Attempts')}</th>
                                <th className="px-5 py-3.5 text-start">{t('network_commands.last_error')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {commands.data.map((command) => (
                                <tr key={command.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        {command.service?.customer ? (
                                            <Link
                                                href={`/customers/${command.service.customer.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {command.service.customer.name}
                                            </Link>
                                        ) : (
                                            <span className="text-muted">
                                                {t('network_commands.service_unavailable')}
                                            </span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">
                                            {command.service?.username ?? t('network_commands.unknown_service')}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{enumLabel(command.action, t)}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            v{command.desired_state_version} · {command.public_id}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={command.status} />
                                    </td>
                                    <td className="px-5 py-4">
                                        {command.service ? (
                                            <StatusBadge status={command.service.network_state} />
                                        ) : (
                                            <span className="text-muted">—</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{command.attempts}</td>
                                    <td className="max-w-xs px-5 py-4 text-sm text-muted">
                                        {command.last_error ? (
                                            <span className="inline-flex items-center gap-1.5">
                                                <AlertTriangle size={14} className="shrink-0 text-coral" />
                                                {command.last_error}
                                            </span>
                                        ) : (
                                            '—'
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canRetry && ['failed', 'abandoned'].includes(command.status) && (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                onClick={() =>
                                                    router.post(
                                                        `/operations/network-commands/${command.public_id}/retry`,
                                                    )
                                                }
                                            >
                                                <RefreshCw size={14} /> {t('Retry')}
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {commands.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-16 text-center">
                                        <Terminal className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('network_commands.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {commands.current_page} {t('of')} {commands.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {commands.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === commands.links.length - 1;
                            if (!link.url)
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? (
                                            <ChevronLeft size={16} />
                                        ) : isNext ? (
                                            <ChevronRight size={16} />
                                        ) : (
                                            link.label
                                        )}
                                    </span>
                                );
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? (
                                        <ChevronLeft size={16} />
                                    ) : isNext ? (
                                        <ChevronRight size={16} />
                                    ) : (
                                        link.label
                                    )}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
