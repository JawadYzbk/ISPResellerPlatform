import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Network, Plus, Search } from 'lucide-react';
import { useState } from 'react';

import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';

type Pop = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    status: Status;
    routers_count: number;
    upstream_links_count: number;
};

type Props = PageProps & {
    pops: Paginator<Pop>;
    filters: { status?: string; search?: string };
    canManage: boolean;
    statuses: Status[];
};

export default function PopsPage({ pops, filters, canManage, statuses }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const popForm = useForm({ name: '', code: '', address: '', status: 'active' });

    const applyFilters = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(
            '/operations/pops',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    const submitPop = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        popForm.post('/operations/pops', { onSuccess: () => popForm.reset() });
    };

    return (
        <AppLayout>
            <Head title={t('pops.title')} />

            <div>
                <p className="eyebrow">{t('pops.eyebrow')}</p>
                <h1 className="page-title">{t('pops.title')}</h1>
                <p className="page-subtitle">{t('pops.subtitle')}</p>
            </div>

            {canManage && (
                <form onSubmit={submitPop} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Plus size={17} className="text-brand" />
                        <h2 className="section-title">{t('pops.add')}</h2>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">{t('Name')}</span>
                            <input
                                className="field"
                                value={popForm.data.name}
                                onChange={(event) => popForm.setData('name', event.target.value)}
                                placeholder={t('pops.name_placeholder')}
                            />
                            {popForm.errors.name && <p className="field-error">{t(popForm.errors.name)}</p>}
                        </label>
                        <label>
                            <span className="field-label">{t('Code')}</span>
                            <input
                                className="field uppercase"
                                value={popForm.data.code}
                                onChange={(event) => popForm.setData('code', event.target.value)}
                                placeholder="CENTRAL"
                            />
                            {popForm.errors.code && <p className="field-error">{t(popForm.errors.code)}</p>}
                        </label>
                        <label>
                            <span className="field-label">{t('Address')}</span>
                            <input
                                className="field"
                                value={popForm.data.address}
                                onChange={(event) => popForm.setData('address', event.target.value)}
                                placeholder={t('pops.address_placeholder')}
                            />
                            {popForm.errors.address && <p className="field-error">{t(popForm.errors.address)}</p>}
                        </label>
                        <label>
                            <span className="field-label">{t('Status')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={popForm.data.status}
                                onChange={(event) => popForm.setData('status', event.target.value)}
                            >
                                {statuses.map((option) => (
                                    <option key={option} value={option}>
                                        {t(option.replace('_', ' '))}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {popForm.errors.status && <p className="field-error">{t(popForm.errors.status)}</p>}
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={popForm.processing}>
                            <Plus size={16} /> {t('pops.add')}
                        </button>
                    </div>
                </form>
            )}

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-72">
                    <span className="field-label">{t('pops.search')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('pops.search_placeholder')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('Status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('pops.all_statuses')}</option>
                        {statuses.map((option) => (
                            <option key={option} value={option}>
                                {t(option.replace('_', ' '))}
                            </option>
                        ))}
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                    <Network size={17} className="text-brand" />
                    <p className="text-sm font-semibold">
                        {pops.total.toLocaleString()} {t('pops.count')}
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[820px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">POP</th>
                                <th className="px-5 py-3.5 text-start">{t('Address')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Routers')}</th>
                                <th className="px-5 py-3.5 text-start">{t('pops.upstream_links')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {pops.data.map((pop) => (
                                <tr key={pop.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <Link
                                            href={'/operations/pops/' + pop.id}
                                            className="text-sm font-semibold hover:text-brand"
                                        >
                                            {pop.name}
                                        </Link>
                                        <p className="mt-1 text-xs text-muted">{pop.code}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {pop.address ?? t('pops.no_address')}
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={pop.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{pop.routers_count}</td>
                                    <td className="px-5 py-4 text-sm text-muted">{pop.upstream_links_count}</td>
                                </tr>
                            ))}
                            {pops.data.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-5 py-16 text-center">
                                        <Network className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('pops.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {pops.current_page} {t('of')} {pops.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {pops.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === pops.links.length - 1;
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
                                    className={
                                        link.active
                                            ? 'grid size-8 place-items-center rounded-lg bg-brand text-xs text-white'
                                            : 'grid size-8 place-items-center rounded-lg text-xs text-muted hover:bg-sand'
                                    }
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
