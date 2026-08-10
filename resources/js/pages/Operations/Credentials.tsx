import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, KeyRound, Search } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type Credential = {
    id: number;
    identifier: string;
    status: 'available' | 'reserved' | 'assigned' | 'expired' | 'revoked';
    expires_at: string | null;
    supplier: { name: string; code: string } | null;
    batch_reference: string | null;
    assigned_service: {
        public_id: string;
        username: string;
        customer_public_id: string | null;
        customer: string | null;
    } | null;
};

type AssignableService = {
    public_id: string;
    username: string;
    customer: string | null;
};

type Props = PageProps & {
    credentials: Paginator<Credential>;
    filters: { status?: string; search?: string };
    canAssign?: boolean;
    assignableServices?: AssignableService[];
};

export default function CredentialsPage({
    credentials,
    filters,
    canAssign = false,
    assignableServices = [],
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/credentials',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    const assignCredential = (credential: Credential) => {
        const servicePublicId = selectedServices[credential.id];
        if (!servicePublicId) {
            return;
        }

        if (window.confirm(`Assign ${credential.identifier} to this service?`)) {
            router.post(`/operations/credentials/${credential.id}/assign`, {
                service_public_id: servicePublicId,
            });
        }
    };

    return (
        <AppLayout>
            <Head title="Credentials" />
            <div>
                <p className="eyebrow">Supplier operations</p>
                <h1 className="page-title">Upstream credentials</h1>
                <p className="page-subtitle">
                    Track imported credential inventory and assignments without exposing secrets.
                </p>
            </div>
            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">Search credential inventory</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Identifier, batch, username"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Credential status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="assigned">Assigned</option>
                        <option value="expired">Expired</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <KeyRound size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{credentials.total.toLocaleString()} credential(s)</p>
                    </div>
                    <p className="text-xs text-muted">Secrets require a separate audited reveal flow.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1160px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Identifier</th>
                                <th className="px-5 py-3.5 text-start">Supplier / batch</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Expiry</th>
                                <th className="px-5 py-3.5 text-start">Assigned service</th>
                                <th className="px-5 py-3.5 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {credentials.data.map((credential) => (
                                <tr key={credential.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{credential.identifier}</p>
                                        <p className="mt-1 text-xs text-muted">Inventory #{credential.id}</p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{credential.supplier?.name ?? 'No supplier'}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {credential.batch_reference ?? 'No batch reference'}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={credential.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {formatDate(credential.expires_at)}
                                    </td>
                                    <td className="px-5 py-4">
                                        {credential.assigned_service ? (
                                            <>
                                                {credential.assigned_service.customer_public_id ? (
                                                    <Link
                                                        href={`/customers/${credential.assigned_service.customer_public_id}`}
                                                        className="text-sm font-semibold hover:text-brand"
                                                    >
                                                        {credential.assigned_service.username}
                                                    </Link>
                                                ) : (
                                                    <span className="text-sm font-semibold">
                                                        {credential.assigned_service.username}
                                                    </span>
                                                )}
                                                {credential.assigned_service.customer && (
                                                    <p className="mt-1 text-xs text-muted">
                                                        {credential.assigned_service.customer}
                                                    </p>
                                                )}
                                            </>
                                        ) : (
                                            <span className="text-sm text-muted">Unassigned</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canAssign && credential.status === 'available' && assignableServices.length > 0 && (
                                            <div className="flex items-center justify-end gap-2">
                                                <select
                                                    className="field max-w-56 py-2 text-xs"
                                                    value={selectedServices[credential.id] ?? ''}
                                                    onChange={(event) =>
                                                        setSelectedServices((current) => ({
                                                            ...current,
                                                            [credential.id]: event.target.value,
                                                        }))
                                                    }
                                                >
                                                    <option value="">Select service</option>
                                                    {assignableServices.map((service) => (
                                                        <option key={service.public_id} value={service.public_id}>
                                                            {service.username} · {service.customer ?? 'No customer'}
                                                        </option>
                                                    ))}
                                                </select>
                                                <button
                                                    type="button"
                                                    className="text-sm font-semibold text-brand"
                                                    onClick={() => assignCredential(credential)}
                                                >
                                                    Assign
                                                </button>
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {credentials.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <KeyRound className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No credentials match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {credentials.current_page} of {credentials.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {credentials.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === credentials.links.length - 1;
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
