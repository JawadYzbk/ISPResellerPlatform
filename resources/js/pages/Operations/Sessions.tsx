import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Radio, RefreshCw, WifiOff } from 'lucide-react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatBytes, formatDate, formatDuration } from '@/lib/format';
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
            <Head title="Live sessions" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Network operations</p>
                    <h1 className="page-title">Live sessions</h1>
                    <p className="page-subtitle">
                        See who is online, where the session is anchored, and when the NAS last checked in.
                    </p>
                </div>
                <Link href="/operations/network-commands" className="button-secondary">
                    Network command queue
                </Link>
            </div>

            <form onSubmit={applySearch} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block flex-1">
                    <span className="field-label">Search sessions</span>
                    <input
                        className="field"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Username, customer, IP, NAS or session ID"
                    />
                </label>
                <button type="submit" className="button-primary">
                    Search
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Radio size={17} className="text-emerald-600" />
                        <p className="text-sm font-semibold">{sessions.total.toLocaleString()} active session(s)</p>
                    </div>
                    <span className="inline-flex items-center gap-1.5 text-xs text-muted">
                        <RefreshCw size={13} /> Refreshes every 10 seconds
                    </span>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1120px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Customer</th>
                                <th className="px-5 py-3.5 text-start">Session</th>
                                <th className="px-5 py-3.5 text-start">Network</th>
                                <th className="px-5 py-3.5 text-start">Uptime</th>
                                <th className="px-5 py-3.5 text-start">Traffic</th>
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
                                            <span className="text-muted">Customer unavailable</span>
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
                                        <p className="font-semibold">{session.framed_ip ?? 'No IP reported'}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {session.router ?? session.nasname ?? 'NAS unavailable'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            Last seen {formatDate(session.last_seen_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <p className="font-semibold">
                                            {formatDuration(session.started_at, session.last_seen_at)}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            Started {formatDate(session.started_at)}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        <p>↓ {formatBytes(session.input_octets)}</p>
                                        <p className="mt-1">↑ {formatBytes(session.output_octets)}</p>
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canDisconnect && session.service && (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Disconnect ${session.username}'s current session?`,
                                                    ) &&
                                                    router.post(
                                                        `/services/${session.service?.public_id}/disconnect-session`,
                                                    )
                                                }
                                            >
                                                <WifiOff size={14} /> Disconnect
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {sessions.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <Radio className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No active sessions match this search</p>
                                        <p className="mt-1 text-sm text-muted">
                                            The page will update automatically as accounting records arrive.
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {sessions.current_page} of {sessions.last_page}
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
