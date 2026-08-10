import { Head, Link, router } from '@inertiajs/react';
import { Search, Wifi } from 'lucide-react';
import { useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator, Service } from '@/types';

type Props = PageProps & { services: Paginator<Service>; filters: { search?: string; status?: string } };

export default function ServicesIndex({ services, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/services',
            { search: search || undefined, status: filters.status || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Services" />
            <div>
                <p className="eyebrow">Subscriber operations</p>
                <h1 className="page-title">Services</h1>
                <p className="page-subtitle">Track entitlement, expiry, and network state from one queue.</p>
            </div>
            <div className="mt-8 card overflow-hidden">
                <div className="border-b border-line px-5 py-4">
                    <form onSubmit={submitSearch} className="relative max-w-md">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search username or customer"
                            className="field ps-10"
                        />
                    </form>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Service</th>
                                <th className="px-5 py-3.5 text-start">Customer</th>
                                <th className="px-5 py-3.5 text-start">Plan</th>
                                <th className="px-5 py-3.5 text-start">Expiry</th>
                                <th className="px-5 py-3.5 text-start">State</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {services.data.map((service) => (
                                <tr key={service.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{service.username}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {service.plan.download_kbps / 1000} / {service.plan.upload_kbps / 1000} Mbps
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        <Link
                                            href={`/customers/${service.customer?.public_id}`}
                                            className="font-semibold hover:text-brand"
                                        >
                                            {service.customer?.first_name} {service.customer?.last_name ?? ''}
                                        </Link>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{service.plan.name}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{formatDate(service.expires_at)}</td>
                                    <td className="px-5 py-4">
                                        <div className="flex gap-2">
                                            <StatusBadge status={service.status} />
                                            <StatusBadge status={service.network_state} />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {services.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-16 text-center">
                                        <Wifi className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No services found</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="border-t border-line px-5 py-4 text-sm text-muted">
                    {services.total.toLocaleString()} service(s)
                </div>
            </div>
        </AppLayout>
    );
}
