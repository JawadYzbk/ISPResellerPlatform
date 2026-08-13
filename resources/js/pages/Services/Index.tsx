import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, Wifi } from 'lucide-react';
import { useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator, Service } from '@/types';

type Props = PageProps & { services: Paginator<Service>; filters: { search?: string; status?: string } };

export default function ServicesIndex({ services, filters }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/services',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppLayout>
            <Head title={t('Services')} />
            <div>
                <p className="eyebrow">{t('Subscriber operations')}</p>
                <h1 className="page-title">{t('Services')}</h1>
                <p className="page-subtitle">{t('Track entitlement, expiry, and network state from one queue.')}</p>
            </div>
            <div className="mt-8 card overflow-hidden">
                <form
                    onSubmit={submitSearch}
                    className="flex flex-col gap-4 border-b border-line px-5 py-4 sm:flex-row sm:items-end"
                >
                    <label className="block sm:min-w-80">
                        <span className="field-label">{t('Search username or customer')}</span>
                        <div className="relative">
                            <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder={t('Username, customer name, phone')}
                                className="field ps-10"
                            />
                        </div>
                    </label>
                    <label className="block sm:min-w-48">
                        <span className="field-label">{t('Service status')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">{t('All statuses')}</option>
                            <option value="pending">{t('Pending')}</option>
                            <option value="active">{t('Active')}</option>
                            <option value="suspended">{t('Suspended')}</option>
                            <option value="terminated">{t('Terminated')}</option>
                        </ResponsiveSelect>
                    </label>
                    <button type="submit" className="button-primary">
                        {t('Apply filters')}
                    </button>
                </form>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Service')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Plan')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Expiry')}</th>
                                <th className="px-5 py-3.5 text-start">{t('State')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {services.data.map((service) => (
                                <tr key={service.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={`/services/${service.public_id}`}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {service.username}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">
                                            {service.plan.download_kbps / 1000} / {service.plan.upload_kbps / 1000} Mbps
                                        </p>
                                    </td>
                                    <td className="px-5 py-4 text-sm">
                                        {service.customer ? (
                                            <Link
                                                href={`/customers/${service.customer.public_id}`}
                                                className="font-semibold hover:text-brand"
                                            >
                                                {service.customer.first_name} {service.customer.last_name ?? ''}
                                            </Link>
                                        ) : (
                                            <span className="text-muted">{t('Customer unavailable')}</span>
                                        )}
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
                                        <p className="mt-3 font-semibold">{t('No services found')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {services.total.toLocaleString()} {t('service(s)')} · {t('Page')} {services.current_page}{' '}
                        {t('of')} {services.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {services.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === services.links.length - 1;
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
                                    aria-label={isPrevious ? t('Previous page') : isNext ? t('Next page') : undefined}
                                    aria-current={link.active ? 'page' : undefined}
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
