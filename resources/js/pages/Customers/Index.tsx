import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    CalendarClock,
    CheckSquare,
    ChevronLeft,
    ChevronRight,
    Download,
    Filter,
    Search,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { useState } from 'react';

import { StatusBadge } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { Customer, PageProps, Paginator } from '@/types';

type Zone = { id: number; name: string; code: string };
type ColumnKey = 'zone' | 'services' | 'balance' | 'expiry' | 'status';
type SavedView = {
    id: number;
    name: string;
    filters: { search?: string; status?: string; zone_id?: string; expires_from?: string; expires_to?: string };
    columns: ColumnKey[];
};
type Props = PageProps & {
    customers: Paginator<Customer>;
    filters: { search?: string; status?: string; zone_id?: string; expires_from?: string; expires_to?: string };
    zones: Zone[];
    savedViews: SavedView[];
    canExport?: boolean;
};

const columnOptions: { key: ColumnKey; label: string }[] = [
    { key: 'zone', label: 'Zone' },
    { key: 'services', label: 'Services' },
    { key: 'balance', label: 'Balance' },
    { key: 'expiry', label: 'Next expiry' },
    { key: 'status', label: 'Status' },
];

function getNextExpiry(customer: Customer): string | null {
    return (
        customer.services
            .filter((service) => service.status !== 'terminated' && service.expires_at)
            .map((service) => service.expires_at as string)
            .sort()[0] ?? null
    );
}

export default function CustomersIndex({ customers, filters, zones, savedViews, canExport = false }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [zoneId, setZoneId] = useState(filters.zone_id?.toString() ?? '');
    const [expiresFrom, setExpiresFrom] = useState(filters.expires_from ?? '');
    const [expiresTo, setExpiresTo] = useState(filters.expires_to ?? '');
    const [showFilters, setShowFilters] = useState(false);
    const [showColumns, setShowColumns] = useState(false);
    const [visibleColumns, setVisibleColumns] = useState<ColumnKey[]>(columnOptions.map(({ key }) => key));
    const [selectedViewId, setSelectedViewId] = useState('');
    const [selectedCustomerIds, setSelectedCustomerIds] = useState<string[]>([]);
    const saveViewForm = useForm({ name: '' });
    const translatedColumnOptions = columnOptions.map((option) => ({ ...option, label: t(option.label) }));

    const applyFilters = () => {
        router.get(
            '/customers',
            {
                search: search || undefined,
                status: status || undefined,
                zone_id: zoneId || undefined,
                expires_from: expiresFrom || undefined,
                expires_to: expiresTo || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const submitSearch = (event: React.FormEvent) => {
        event.preventDefault();
        applyFilters();
    };

    const toggleColumn = (column: ColumnKey) => {
        setVisibleColumns((current) =>
            current.includes(column) ? current.filter((item) => item !== column) : [...current, column],
        );
    };

    const applySavedView = () => {
        const view = savedViews.find((item) => item.id.toString() === selectedViewId);
        if (!view) return;
        setSearch(view.filters.search ?? '');
        setStatus(view.filters.status ?? '');
        setZoneId(view.filters.zone_id ?? '');
        setExpiresFrom(view.filters.expires_from ?? '');
        setExpiresTo(view.filters.expires_to ?? '');
        setVisibleColumns(view.columns);
        router.get('/customers', view.filters, { preserveState: true, replace: true });
    };

    const submitSavedView = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        saveViewForm.transform(() => ({
            name: saveViewForm.data.name,
            filters: {
                search: search || undefined,
                status: status || undefined,
                zone_id: zoneId || undefined,
                expires_from: expiresFrom || undefined,
                expires_to: expiresTo || undefined,
            },
            columns: visibleColumns,
        }));
        saveViewForm.post('/customers/saved-views', { onSuccess: () => saveViewForm.reset() });
    };

    const pageCustomerIds = customers.data.map((customer) => customer.public_id);
    const allPageSelected =
        pageCustomerIds.length > 0 && pageCustomerIds.every((id) => selectedCustomerIds.includes(id));
    const toggleCustomer = (publicId: string) => {
        setSelectedCustomerIds((current) =>
            current.includes(publicId) ? current.filter((id) => id !== publicId) : [...current, publicId],
        );
    };
    const togglePage = () => {
        setSelectedCustomerIds((current) =>
            allPageSelected
                ? current.filter((id) => !pageCustomerIds.includes(id))
                : [...new Set([...current, ...pageCustomerIds])],
        );
    };
    const exportCustomers = () => {
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (status) params.set('status', status);
        if (zoneId) params.set('zone_id', zoneId);
        if (expiresFrom) params.set('expires_from', expiresFrom);
        if (expiresTo) params.set('expires_to', expiresTo);
        selectedCustomerIds.forEach((id) => params.append('selected[]', id));
        window.location.assign(`/customers/export?${params.toString()}`);
    };

    return (
        <AppLayout>
            <Head title={t('Customers')} />
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Subscriber CRM')}</p>
                    <h1 className="page-title">{t('Customers')}</h1>
                    <p className="page-subtitle">{t('A clear view of everyone you keep connected.')}</p>
                </div>
                <Link href="/customers/create" className="button-primary">
                    <Users size={17} />
                    {t('Add customer')}
                </Link>
            </div>
            <div className="card mt-6 flex flex-col gap-4 p-5 lg:flex-row lg:items-end lg:justify-between">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label className="block min-w-56">
                        <span className="field-label">{t('Saved view')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={selectedViewId}
                            onChange={(event) => setSelectedViewId(event.target.value)}
                        >
                            <option value="">{t('Choose a saved view')}</option>
                            {savedViews.map((view) => (
                                <option key={view.id} value={view.id}>
                                    {view.name}
                                </option>
                            ))}
                        </ResponsiveSelect>
                    </label>
                    <button
                        type="button"
                        className="button-secondary"
                        onClick={applySavedView}
                        disabled={!selectedViewId}
                    >
                        {t('Apply view')}
                    </button>
                    <button
                        type="button"
                        className="button-secondary text-coral"
                        onClick={() => {
                            if (selectedViewId)
                                router.delete('/customers/saved-views/' + selectedViewId, {
                                    onSuccess: () => setSelectedViewId(''),
                                });
                        }}
                        disabled={!selectedViewId}
                    >
                        {t('Delete')}
                    </button>
                </div>
                <form onSubmit={submitSavedView} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label className="block sm:min-w-56">
                        <span className="field-label">{t('Save current filters and columns')}</span>
                        <input
                            className="field"
                            value={saveViewForm.data.name}
                            onChange={(event) => saveViewForm.setData('name', event.target.value)}
                            placeholder={t('Renewal queue')}
                        />
                        {saveViewForm.errors.name && <p className="field-error">{saveViewForm.errors.name}</p>}
                    </label>
                    <button type="submit" className="button-secondary" disabled={saveViewForm.processing}>
                        {t('Save view')}
                    </button>
                </form>
            </div>
            <div className="mt-8 card overflow-hidden">
                <div className="flex flex-col gap-4 border-b border-line px-5 py-4 md:flex-row md:items-center md:justify-between">
                    <form onSubmit={submitSearch} className="relative max-w-md flex-1">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search name, phone or customer code')}
                            className="field ps-10"
                        />
                    </form>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className={`button-secondary ${showFilters ? 'bg-sand' : ''}`}
                            onClick={() => setShowFilters((open) => !open)}
                            aria-expanded={showFilters}
                        >
                            <Filter size={16} />
                            {t('Filters')}
                        </button>
                        <button
                            type="button"
                            className={`button-secondary ${showColumns ? 'bg-sand' : ''}`}
                            onClick={() => setShowColumns((open) => !open)}
                            aria-expanded={showColumns}
                        >
                            <SlidersHorizontal size={16} />
                            {t('Columns')}
                        </button>
                    </div>
                </div>
                {canExport && (
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-sand/20 px-5 py-3">
                        <p className="text-sm text-muted">
                            {selectedCustomerIds.length
                                ? `${selectedCustomerIds.length} ${t('customer(s) selected across pages')}`
                                : t('Export all customers matching the current filters')}
                        </p>
                        <button type="button" className="button-secondary" onClick={exportCustomers}>
                            <Download size={16} /> {t('Export CSV')}
                        </button>
                    </div>
                )}
                {(showFilters || showColumns) && (
                    <div className="flex flex-col gap-5 border-b border-line bg-sand/30 px-5 py-4 sm:flex-row sm:items-end">
                        {showFilters && (
                            <div className="flex flex-wrap items-end gap-3">
                                <label className="block min-w-44">
                                    <span className="field-label">{t('Customer status')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={status}
                                        onChange={(event) => setStatus(event.target.value)}
                                    >
                                        <option value="">{t('All statuses')}</option>
                                        <option value="active">{t('Active')}</option>
                                        <option value="inactive">{t('Inactive')}</option>
                                        <option value="archived">{t('Archived')}</option>
                                    </ResponsiveSelect>
                                </label>
                                <label className="block min-w-48">
                                    <span className="field-label">{t('Zone')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={zoneId}
                                        onChange={(event) => setZoneId(event.target.value)}
                                    >
                                        <option value="">{t('All zones')}</option>
                                        {zones.map((zone) => (
                                            <option key={zone.id} value={zone.id}>
                                                {zone.name}
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                </label>
                                <label className="block min-w-40">
                                    <span className="field-label">{t('Expiry from')}</span>
                                    <input
                                        className="field"
                                        type="date"
                                        value={expiresFrom}
                                        onChange={(event) => setExpiresFrom(event.target.value)}
                                    />
                                </label>
                                <label className="block min-w-40">
                                    <span className="field-label">{t('Expiry to')}</span>
                                    <input
                                        className="field"
                                        type="date"
                                        value={expiresTo}
                                        onChange={(event) => setExpiresTo(event.target.value)}
                                    />
                                </label>
                                <button type="button" className="button-primary" onClick={applyFilters}>
                                    {t('Apply')}
                                </button>
                            </div>
                        )}
                        {showColumns && (
                            <fieldset className="flex flex-wrap gap-x-4 gap-y-2">
                                <legend className="field-label w-full">{t('Visible columns')}</legend>
                                {translatedColumnOptions.map(({ key, label }) => (
                                    <label key={key} className="inline-flex items-center gap-2 text-sm text-muted">
                                        <input
                                            type="checkbox"
                                            checked={visibleColumns.includes(key)}
                                            onChange={() => toggleColumn(key)}
                                        />
                                        {label}
                                    </label>
                                ))}
                            </fieldset>
                        )}
                    </div>
                )}
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="w-12 px-5 py-3.5 text-start">
                                    <button
                                        type="button"
                                        onClick={togglePage}
                                        aria-label={
                                            allPageSelected
                                                ? t('Clear current page selection')
                                                : t('Select current page')
                                        }
                                    >
                                        <CheckSquare
                                            size={16}
                                            className={allPageSelected ? 'text-brand' : 'text-muted'}
                                        />
                                    </button>
                                </th>
                                <th className="px-5 py-3.5 text-start">{t('Customer')}</th>
                                {visibleColumns.includes('zone') && (
                                    <th className="px-5 py-3.5 text-start">{t('Zone')}</th>
                                )}
                                {visibleColumns.includes('services') && (
                                    <th className="px-5 py-3.5 text-start">{t('Services')}</th>
                                )}
                                {visibleColumns.includes('balance') && (
                                    <th className="px-5 py-3.5 text-start">{t('Balance')}</th>
                                )}
                                {visibleColumns.includes('expiry') && (
                                    <th className="px-5 py-3.5 text-start">{t('Next expiry')}</th>
                                )}
                                {visibleColumns.includes('status') && (
                                    <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                )}
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {customers.data.map((customer) => {
                                const nextExpiry = getNextExpiry(customer);

                                return (
                                    <tr key={customer.public_id} className="group transition hover:bg-sand/30">
                                        <td className="px-5 py-4">
                                            <input
                                                type="checkbox"
                                                aria-label={`Select ${customer.first_name} ${customer.last_name}`}
                                                checked={selectedCustomerIds.includes(customer.public_id)}
                                                onChange={() => toggleCustomer(customer.public_id)}
                                            />
                                        </td>
                                        <td className="px-5 py-4">
                                            <Link
                                                href={`/customers/${customer.public_id}`}
                                                className="flex items-center gap-3"
                                            >
                                                <span className="grid size-9 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand">
                                                    {customer.first_name.slice(0, 1)}
                                                    {customer.last_name?.slice(0, 1) ?? ''}
                                                </span>
                                                <span>
                                                    <span className="block text-sm font-semibold group-hover:text-brand">
                                                        {customer.first_name} {customer.last_name}
                                                    </span>
                                                    <span className="mt-0.5 block text-xs text-muted">
                                                        {customer.code} · {customer.phone}
                                                    </span>
                                                </span>
                                            </Link>
                                        </td>
                                        {visibleColumns.includes('zone') && (
                                            <td className="px-5 py-4 text-sm text-muted">
                                                {customer.zone?.name ?? t('Unassigned')}
                                            </td>
                                        )}
                                        {visibleColumns.includes('services') && (
                                            <td className="px-5 py-4 text-sm text-muted">
                                                {customer.services.length}{' '}
                                                {customer.services.length === 1 ? t('service') : t('services')}
                                            </td>
                                        )}
                                        {visibleColumns.includes('balance') && (
                                            <td className="px-5 py-4 text-sm font-semibold">
                                                {formatMoney(customer.balance_amount, customer.balance_currency)}
                                            </td>
                                        )}
                                        {visibleColumns.includes('expiry') && (
                                            <td className="px-5 py-4 text-sm text-muted">
                                                <span className="inline-flex items-center gap-1.5">
                                                    <CalendarClock size={14} /> {formatDate(nextExpiry)}
                                                </span>
                                            </td>
                                        )}
                                        {visibleColumns.includes('status') && (
                                            <td className="px-5 py-4">
                                                <StatusBadge status={customer.status} />
                                            </td>
                                        )}
                                        <td className="px-5 py-4 text-end">
                                            <Link
                                                href={`/customers/${customer.public_id}`}
                                                className="text-sm font-semibold text-brand opacity-0 transition group-hover:opacity-100"
                                            >
                                                {t('Open')}
                                            </Link>
                                        </td>
                                    </tr>
                                );
                            })}
                            {customers.data.length === 0 && (
                                <tr>
                                    <td colSpan={visibleColumns.length + 3} className="px-5 py-16 text-center">
                                        <Users className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('No customers found')}</p>
                                        <p className="mt-1 text-sm text-muted">
                                            {t('Try a different search or add your first customer.')}
                                        </p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Showing')}{' '}
                        {customers.data.length ? (customers.current_page - 1) * customers.per_page + 1 : 0}–
                        {Math.min(customers.current_page * customers.per_page, customers.total)} {t('of')}{' '}
                        {customers.total}
                    </p>
                    <div className="flex items-center gap-1">
                        {customers.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === customers.links.length - 1;
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
