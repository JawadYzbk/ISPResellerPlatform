import ConfirmDialog from '@/components/ui/confirm-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, CalendarRange, MapPinned, Route, Search, UserRoundCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

import StatusBadge, { type Status } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Collector = {
    id: number;
    name: string;
    email: string;
    all_zones: boolean;
    customer_ids: number[];
};

type Customer = {
    id: number;
    public_id: string;
    code: string;
    name: string;
    phone: string | null;
    address: string | null;
    zone: string | null;
    balance_amount: number;
    balance_currency: string;
};

type RouteStop = {
    id: string;
    position: number;
    outcome: string;
    customer: { id: string; code: string; name: string; zone: string | null };
};

type CollectorRoute = {
    id: string;
    route_date: string;
    status: Status;
    collector: { id: number; name: string; email: string };
    stop_count: number;
    completed_count: number;
    stops: RouteStop[];
};

type Props = {
    date: string;
    collectors: Collector[];
    customers: Customer[];
    routes: CollectorRoute[];
};

export default function CollectorRoutes({ date, collectors, customers, routes }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState('');
    const initialCollectorId = collectors[0]?.id ?? 0;
    const initialRoute = routes.find((item) => item.collector.id === initialCollectorId);
    const initialCustomerIds = initialRoute
        ? initialRoute.stops
              .map((stop) => customers.find((customer) => customer.public_id === stop.customer.id)?.id)
              .filter((value): value is number => value !== undefined)
        : [];
    const form = useForm({
        collector_id: initialCollectorId,
        route_date: date,
        customer_ids: initialCustomerIds,
    });
    const collector = collectors.find((item) => item.id === form.data.collector_id) ?? null;
    const existingRoute = routes.find((item) => item.collector.id === form.data.collector_id) ?? null;
    const eligibleIds = useMemo(() => new Set(collector?.customer_ids ?? []), [collector]);
    const query = search.trim().toLocaleLowerCase();
    const eligibleCustomers = customers.filter(
        (customer) =>
            eligibleIds.has(customer.id) &&
            (query === '' ||
                `${customer.code} ${customer.name} ${customer.phone ?? ''} ${customer.zone ?? ''}`
                    .toLocaleLowerCase()
                    .includes(query)),
    );
    const selectedCustomers = form.data.customer_ids
        .map((id) => customers.find((customer) => customer.id === id))
        .filter((customer): customer is Customer => customer !== undefined);

    const selectCollector = (id: number) => {
        const route = routes.find((item) => item.collector.id === id);
        const stopIds = route
            ? route.stops
                  .map((stop) => customers.find((customer) => customer.public_id === stop.customer.id)?.id)
                  .filter((value): value is number => value !== undefined)
            : [];
        form.setData({ ...form.data, collector_id: id, customer_ids: stopIds });
        form.clearErrors();
    };

    const toggleCustomer = (customerId: number, checked: boolean) => {
        form.setData(
            'customer_ids',
            checked
                ? [...form.data.customer_ids, customerId]
                : form.data.customer_ids.filter((id) => id !== customerId),
        );
    };

    const move = (index: number, direction: -1 | 1) => {
        const target = index + direction;
        if (target < 0 || target >= form.data.customer_ids.length) return;
        const ordered = [...form.data.customer_ids];
        [ordered[index], ordered[target]] = [ordered[target], ordered[index]];
        form.setData('customer_ids', ordered);
    };

    const save = () => {
        form.post('/operations/collector-routes', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={t('collector_routes.title')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('collector_routes.eyebrow')}</p>
                    <h1 className="page-title text-balance">{t('collector_routes.title')}</h1>
                    <p className="page-subtitle text-pretty">{t('collector_routes.subtitle')}</p>
                </div>
                <label className="field-label">
                    {t('collector_routes.route_date')}
                    <input
                        className="field mt-1"
                        type="date"
                        value={date}
                        onChange={(event) =>
                            router.get(
                                '/operations/collector-routes',
                                { date: event.target.value },
                                { preserveState: false, replace: true },
                            )
                        }
                    />
                </label>
            </div>

            <div className="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
                <section className="card p-6">
                    <div className="flex items-center gap-2">
                        <Route size={18} className="text-brand" />
                        <h2 className="section-title">{t('collector_routes.plan_route')}</h2>
                    </div>
                    <label className="mt-5 block">
                        <span className="field-label">{t('Collector')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={form.data.collector_id}
                            onChange={(event) => selectCollector(Number(event.target.value))}
                        >
                            {collectors.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name} ·{' '}
                                    {item.all_zones
                                        ? t('collector_routes.all_zones')
                                        : t('collector_routes.restricted')}
                                </option>
                            ))}
                        </ResponsiveSelect>
                    </label>

                    {collectors.length === 0 && (
                        <div className="mt-5 rounded-xl border border-dashed border-line p-8 text-center">
                            <UserRoundCheck className="mx-auto text-muted" size={26} />
                            <p className="mt-3 text-sm font-semibold">{t('collector_routes.no_collectors')}</p>
                            <Link href="/settings/users" className="button-primary mt-4">
                                {t('collector_routes.invite_collector')}
                            </Link>
                        </div>
                    )}

                    {collector && (
                        <div className="mt-5">
                            <div className="relative">
                                <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                                <input
                                    className="field ps-10"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    placeholder={t('collector_routes.search_customers')}
                                    aria-label={t('collector_routes.search_customers')}
                                />
                            </div>
                            <div className="mt-3 max-h-80 divide-y divide-line overflow-y-auto rounded-xl border border-line">
                                {eligibleCustomers.map((customer) => (
                                    <label key={customer.id} className="flex items-start gap-3 px-4 py-3 hover:bg-sand">
                                        <input
                                            type="checkbox"
                                            className="mt-1"
                                            checked={form.data.customer_ids.includes(customer.id)}
                                            onChange={(event) => toggleCustomer(customer.id, event.target.checked)}
                                        />
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate text-sm font-semibold">
                                                {customer.name} · {customer.code}
                                            </span>
                                            <span className="mt-1 block truncate text-xs text-muted">
                                                {customer.zone ?? t('collector_routes.no_zone')} ·{' '}
                                                {customer.phone ?? t('collector_routes.no_phone')}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs font-semibold tabular-nums text-muted">
                                            {formatMoney(customer.balance_amount, customer.balance_currency)}
                                        </span>
                                    </label>
                                ))}
                                {eligibleCustomers.length === 0 && (
                                    <div className="p-8 text-center">
                                        <MapPinned className="mx-auto text-muted" size={25} />
                                        <p className="mt-3 text-sm font-semibold">
                                            {t('collector_routes.no_customers')}
                                        </p>
                                        <p className="mt-1 text-pretty text-xs text-muted">
                                            {t('collector_routes.no_customers_description')}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {form.errors.customer_ids && <p className="field-error mt-3">{t(form.errors.customer_ids)}</p>}
                    {form.errors.collector_id && <p className="field-error mt-3">{t(form.errors.collector_id)}</p>}
                </section>

                <section className="card p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="eyebrow">{t('collector_routes.planned_order')}</p>
                            <h2 className="section-title mt-1">
                                {selectedCustomers.length} {t('collector_routes.stops')}
                            </h2>
                        </div>
                        {existingRoute && <StatusBadge status={existingRoute.status} />}
                    </div>
                    <div className="mt-5 space-y-2">
                        {selectedCustomers.map((customer, index) => (
                            <div
                                key={customer.id}
                                className="flex items-center gap-3 rounded-xl border border-line p-3"
                            >
                                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-soft text-xs font-bold tabular-nums text-brand">
                                    {index + 1}
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-semibold">{customer.name}</span>
                                    <span className="block truncate text-xs text-muted">
                                        {customer.address ?? customer.zone ?? t('collector_routes.no_address')}
                                    </span>
                                </span>
                                <button
                                    type="button"
                                    className="button-quiet px-2"
                                    onClick={() => move(index, -1)}
                                    disabled={index === 0}
                                    aria-label={`Move ${customer.name} earlier`}
                                >
                                    <ArrowUp size={15} />
                                </button>
                                <button
                                    type="button"
                                    className="button-quiet px-2"
                                    onClick={() => move(index, 1)}
                                    disabled={index === selectedCustomers.length - 1}
                                    aria-label={`Move ${customer.name} later`}
                                >
                                    <ArrowDown size={15} />
                                </button>
                            </div>
                        ))}
                        {selectedCustomers.length === 0 && (
                            <div className="rounded-xl border border-dashed border-line p-10 text-center">
                                <CalendarRange className="mx-auto text-muted" size={28} />
                                <p className="mt-3 font-semibold">{t('collector_routes.choose_first_stop')}</p>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {t('collector_routes.order_description')}
                                </p>
                            </div>
                        )}
                    </div>
                    <div className="mt-5 flex justify-end border-t border-line pt-5">
                        {existingRoute ? (
                            <ConfirmDialog
                                title={t('collector_routes.replace_title')}
                                description={t('collector_routes.replace_description')}
                                confirmLabel={t('collector_routes.replace_route')}
                                onConfirm={save}
                            >
                                <button
                                    type="button"
                                    className="button-primary"
                                    disabled={form.processing || selectedCustomers.length === 0}
                                >
                                    {t('collector_routes.save_revised')}
                                </button>
                            </ConfirmDialog>
                        ) : (
                            <button
                                type="button"
                                className="button-primary"
                                onClick={save}
                                disabled={form.processing || selectedCustomers.length === 0}
                            >
                                {t('collector_routes.plan_route')}
                            </button>
                        )}
                    </div>
                </section>
            </div>

            <section className="mt-6 grid gap-4 lg:grid-cols-2">
                {routes.map((route) => (
                    <article key={route.id} className="card p-5">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex min-w-0 items-start gap-3">
                                <UserRoundCheck className="mt-0.5 shrink-0 text-brand" size={18} />
                                <div className="min-w-0">
                                    <h2 className="truncate font-semibold">{route.collector.name}</h2>
                                    <p className="mt-1 text-xs text-muted tabular-nums">
                                        {route.completed_count}/{route.stop_count} {t('Completed').toLocaleLowerCase()}
                                    </p>
                                </div>
                            </div>
                            <StatusBadge status={route.status} />
                        </div>
                        <div className="mt-4 divide-y divide-line rounded-xl border border-line">
                            {route.stops.map((stop) => (
                                <div key={stop.id} className="flex items-center gap-3 px-4 py-3">
                                    <span className="text-xs font-bold tabular-nums text-muted">{stop.position}</span>
                                    <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                        {stop.customer.name}
                                    </span>
                                    <span className="text-xs capitalize text-muted">
                                        {enumLabel(stop.outcome, t)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </article>
                ))}
                {routes.length === 0 && (
                    <div className="card p-10 text-center lg:col-span-2">
                        <Route className="mx-auto text-muted" size={28} />
                        <p className="mt-3 font-semibold">{t('collector_routes.no_routes')}</p>
                        <p className="mt-1 text-sm text-muted">{t('collector_routes.no_routes_description')}</p>
                    </div>
                )}
            </section>
        </AppLayout>
    );
}
