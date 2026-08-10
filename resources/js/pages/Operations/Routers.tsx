import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, RefreshCw, Router as RouterIcon, ShieldCheck } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type RouterRow = {
    public_id: string;
    name: string;
    host: string;
    api_port: number;
    status: 'online' | 'offline' | 'unknown';
    tls_verify: boolean;
    last_seen_at: string | null;
    consecutive_failures: number;
    services_count: number;
    pop: { name: string; code: string } | null;
};

type Props = PageProps & {
    routers: Paginator<RouterRow>;
    filters: { status?: string };
    canCheckHealth?: boolean;
};

export default function RoutersPage({ routers, filters, canCheckHealth = false }: Props) {
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/operations/routers', { status: status || undefined }, { preserveState: true, replace: true });
    };

    return (
        <AppLayout>
            <Head title="Routers" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Network operations</p>
                    <h1 className="page-title">Routers</h1>
                    <p className="page-subtitle">Inspect device reachability and the services assigned to each router.</p>
                </div>
                <Link href="/operations/network-commands" className="button-secondary">
                    Command queue
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-52">
                    <span className="field-label">Router status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="online">Online</option>
                        <option value="offline">Offline</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <RouterIcon size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{routers.total.toLocaleString()} router(s)</p>
                    </div>
                    <p className="text-xs text-muted">Credentials are never rendered in the operations surface.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Router</th>
                                <th className="px-5 py-3.5 text-start">Location</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Services</th>
                                <th className="px-5 py-3.5 text-start">Last seen</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {routers.data.map((device) => (
                                <tr key={device.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{device.name}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {device.host}:{device.api_port}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {device.pop ? `${device.pop.name} (${device.pop.code})` : 'No POP assigned'}
                                    </td>
                                    <td className="px-5 py-4">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge status={device.status} />
                                            {!device.tls_verify && (
                                                <span className="text-xs font-semibold text-amber-700">TLS verify off</span>
                                            )}
                                            {device.consecutive_failures > 0 && (
                                                <span className="text-xs text-muted">
                                                    {device.consecutive_failures} failed check(s)
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{device.services_count}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(device.last_seen_at)}</td>
                                    <td className="px-5 py-4 text-end">
                                        {canCheckHealth && (
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                onClick={() => router.post(`/operations/routers/${device.public_id}/health`)}
                                            >
                                                <RefreshCw size={14} /> Check health
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {routers.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <ShieldCheck className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No routers match this filter</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {routers.current_page} of {routers.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {routers.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === routers.links.length - 1;
                            if (!link.url) {
                                return (
                                    <span key={index} className="grid size-8 place-items-center text-muted/40">
                                        {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                    </span>
                                );
                            }
                            return (
                                <Link
                                    key={index}
                                    href={link.url}
                                    className={`grid size-8 place-items-center rounded-lg text-xs ${link.active ? 'bg-brand text-white' : 'text-muted hover:bg-sand'}`}
                                >
                                    {isPrevious ? <ChevronLeft size={16} /> : isNext ? <ChevronRight size={16} /> : link.label}
                                </Link>
                            );
                        })}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
