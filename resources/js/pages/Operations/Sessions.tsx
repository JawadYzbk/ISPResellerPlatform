import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Radio, RefreshCw, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';

import ConfirmDialog from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/AppLayout';
import { formatBytes, formatDate, formatDuration } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type Session = {
    session_id: string;
    username: string;
    nasname: string | null;
    framed_ip: string | null;
    started_at: string | null;
    last_seen_at: string | null;
    input_octets: number;
    output_octets: number;
    service: { public_id: string; username: string; plan: string | null } | null;
    customer: { public_id: string; code: string; name: string } | null;
    router: string | null;
};

type Props = PageProps & {
    sessions: Paginator<Session>;
    filters: { search?: string };
    canDisconnect?: boolean;
};

export default function SessionsPage({ sessions, filters, canDisconnect = false }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const reloadWhenVisible = () => {
            if (document.visibilityState !== 'visible') return;
            router.reload({ only: ['sessions'] });
        };
        const interval = window.setInterval(reloadWhenVisible, 10_000);
        document.addEventListener('visibilitychange', reloadWhenVisible);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', reloadWhenVisible);
        };
    }, []);

    const applySearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/operations/sessions', { search: search || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title={t('sessions.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('sessions.eyebrow')}</p>
                    <h1 className="page-title">{t('sessions.title')}</h1>
                    <p className="page-subtitle">{t('sessions.subtitle')}</p>
                </div>
                <Link href="/operations/network-commands" className="button-secondary">
                    {t('sessions.command_queue')}
                </Link>
            </div>

            <form onSubmit={applySearch} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block flex-1">
                    <span className="field-label">{t('sessions.search')}</span>
                    <input
                        className="field"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('sessions.search_placeholder')}
                    />
                </label>
                <button type="submit" className="button-primary">
                    {t('Search')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Radio size={17} className="text-emerald-600" />
                        <p className="text-sm font-semibold">
                            {sessions.total.toLocaleString()} {t('sessions.active')}
                        </p>
                    </div>
                    <span className="inline-flex items-center gap-1.5 text-xs text-muted">
                        <RefreshCw size={13} /> {t('sessions.refreshes')}
                    </span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1120px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Session')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Network')}</th>
                                <th className="px-5 py-3.5 text-start">{t('sessions.uptime')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Traffic')}</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {sessions.data.map((session) => (
                                <tr key={`${session.session_id}-${session.username}`} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        {session.customer ? (
                                            <Link
                                                href={`/customers/${session.customer.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {session.customer.name}
                                            </Link>
                                        ) : (
                                            <span className="text-muted">{t('sessions.customer_unavailable')}</span>
                                        )}
                                        <p className="mt-1 text-xs text-muted">{session.customer?.code ?? '—'}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        {session.service ? (
                                            <Link
                                                href={`/services/${session.service.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {session.username}
                                            </Link>
                                        ) : (
                                            <span className="font-semibold">{session.username}</span>
                                        )}
                                        <p className="mt-1 font-mono text-xs text-muted">{session.session_id}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <p className="font-semibold">{session.framed_ip ?? t('sessions.no_ip')}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {session.router ?? session.nasname ?? t('sessions.nas_unavailable')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('sessions.last_seen')} {formatDate(session.last_seen_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <p className="font-semibold">
                                            {formatDuration(session.started_at, session.last_seen_at)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('sessions.started')} {formatDate(session.started_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        <p>↓ {formatBytes(session.input_octets)}</p>
                                        <p className="mt-1">↑ {formatBytes(session.output_octets)}</p>
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canDisconnect && session.service && (
                                            <ConfirmDialog
                                                title={t('sessions.disconnect_title')}
                                                description={t('sessions.disconnect_description')}
                                                confirmLabel={t('sessions.disconnect_session')}
                                                destructive
                                                onConfirm={() =>
                                                    router.post(
                                                        `/services/${session.service?.public_id}/disconnect-session`,
                                                    )
                                                }
                                            >
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                >
                                                    <WifiOff size={14} /> {t('Disconnect')}
                                                </button>
                                            </ConfirmDialog>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {sessions.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <Radio className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('sessions.no_matches')}</p>
                                        <p className="mt-1 text-sm text-muted">
                                            {t('sessions.no_matches_description')}
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {sessions.current_page} {t('of')} {sessions.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {sessions.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === sessions.links.length - 1;
                            if (!link.url) {
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
                            }

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
