import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ChevronLeft, ChevronRight, Plus, Search, Tags } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type Plan = {
    public_id: string;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    services_count: number;
    price: { amount_minor: number; currency: string; effective_from: string } | null;
};
type Addon = {
    public_id: string;
    name: string;
    slug: string;
    description: string | null;
    amount_minor: number;
    currency: string;
    billing_period_days: number | null;
    status: 'active' | 'inactive';
};
type Promotion = {
    public_id: string;
    name: string;
    code: string;
    type: 'percent' | 'fixed' | 'free_days';
    value: number;
    applies_to: string[];
    starts_at: string;
    ends_at: string | null;
    max_redemptions: number | null;
    redemptions_count: number;
    is_active: boolean;
};
type AvailablePlan = { public_id: string; name: string };

type Props = PageProps & {
    plans: Paginator<Plan>;
    filters: { status?: string; search?: string };
    addons: Addon[];
    promotions: Promotion[];
    availablePlans: AvailablePlan[];
};

export default function PlansIndex({ plans, filters, addons, promotions, availablePlans }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedPromoPlans, setSelectedPromoPlans] = useState<string[]>([]);
    const addonForm = useForm({
        name: '',
        slug: '',
        description: '',
        amount: '',
        currency: 'USD',
        billing_period_days: '',
        status: 'active',
    });
    const promotionForm = useForm({
        name: '',
        code: '',
        type: 'percent',
        value: '',
        starts_at: new Date().toISOString().slice(0, 10),
        ends_at: '',
        max_redemptions: '',
        is_active: true,
    });

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/plans',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    const submitAddon = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(addonForm.data.amount, addonForm.data.currency);
        if (amountMinor === null) {
            addonForm.setError('amount', 'Enter a valid non-negative amount.');
            return;
        }
        addonForm.transform((data) => ({ ...data, amount_minor: amountMinor }));
        addonForm.post('/plans/addons', { onSuccess: () => addonForm.reset() });
    };

    const submitPromotion = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const rawValue = Number(promotionForm.data.value);
        if (!Number.isFinite(rawValue) || rawValue <= 0) {
            promotionForm.setError('value', 'Enter a positive promotion value.');
            return;
        }
        promotionForm.transform((data) => ({
            ...data,
            value: data.type === 'percent' ? Math.round(rawValue * 100) : Math.round(rawValue),
            applies_to: selectedPromoPlans,
        }));
        promotionForm.post('/plans/promotions', {
            onSuccess: () => {
                promotionForm.reset();
                setSelectedPromoPlans([]);
            },
        });
    };

    return (
        <AppLayout>
            <Head title="Plans" />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Commercial catalog</p>
                    <h1 className="page-title">Plans</h1>
                    <p className="page-subtitle">
                        Manage service speeds, billing duration, and effective catalog prices.
                    </p>
                </div>
                <Link href="/plans/create" className="button-primary">
                    <Plus size={16} /> New plan
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">Search plans</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Plan name or slug"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Status</span>
                    <select className="field" value={status} onChange={(event) => setStatus(event.target.value)}>
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <section className="card p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="section-title">Add-ons</h2>
                            <p className="mt-1 text-sm text-muted">
                                Recurring or one-off extras such as static IPs and equipment rental.
                            </p>
                        </div>
                        <Tags size={17} className="text-brand" />
                    </div>
                    <form onSubmit={submitAddon} className="mt-5 grid gap-3 sm:grid-cols-2">
                        <label>
                            <span className="field-label">Name</span>
                            <input
                                className="field"
                                value={addonForm.data.name}
                                onChange={(event) => addonForm.setData('name', event.target.value)}
                                placeholder="Static IP"
                            />
                            {addonForm.errors.name && <p className="field-error">{addonForm.errors.name}</p>}
                        </label>
                        <label>
                            <span className="field-label">Price</span>
                            <input
                                className="field"
                                inputMode="decimal"
                                value={addonForm.data.amount}
                                onChange={(event) => addonForm.setData('amount', event.target.value)}
                                placeholder="5.00"
                            />
                            {addonForm.errors.amount && <p className="field-error">{addonForm.errors.amount}</p>}
                        </label>
                        <label>
                            <span className="field-label">Currency</span>
                            <input
                                className="field uppercase"
                                maxLength={3}
                                value={addonForm.data.currency}
                                onChange={(event) => addonForm.setData('currency', event.target.value.toUpperCase())}
                            />
                        </label>
                        <label>
                            <span className="field-label">Billing period days</span>
                            <input
                                className="field"
                                type="number"
                                min="1"
                                value={addonForm.data.billing_period_days}
                                onChange={(event) => addonForm.setData('billing_period_days', event.target.value)}
                                placeholder="One-off if blank"
                            />
                        </label>
                        <label className="sm:col-span-2">
                            <span className="field-label">Description</span>
                            <input
                                className="field"
                                value={addonForm.data.description}
                                onChange={(event) => addonForm.setData('description', event.target.value)}
                            />
                        </label>
                        <button
                            type="submit"
                            className="button-secondary sm:col-span-2"
                            disabled={addonForm.processing}
                        >
                            <Plus size={15} /> Add add-on
                        </button>
                    </form>
                    <div className="mt-5 space-y-2 border-t border-line pt-5">
                        {addons.map((addon) => (
                            <div
                                key={addon.public_id}
                                className="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm"
                            >
                                <div>
                                    <p className="font-semibold">{addon.name}</p>
                                    <p className="text-xs text-muted">
                                        {formatMoney(addon.amount_minor, addon.currency)} ·{' '}
                                        {addon.billing_period_days
                                            ? `${addon.billing_period_days}-day recurring`
                                            : 'one-off'}{' '}
                                        · {addon.status}
                                    </p>
                                </div>
                                {addon.status === 'active' && (
                                    <button
                                        type="button"
                                        className="text-muted hover:text-coral"
                                        onClick={() => router.delete(`/plans/addons/${addon.public_id}`)}
                                        aria-label={`Archive ${addon.name}`}
                                    >
                                        <Archive size={15} />
                                    </button>
                                )}
                            </div>
                        ))}
                        {addons.length === 0 && <p className="text-sm text-muted">No add-ons yet.</p>}
                    </div>
                </section>
                <section className="card p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="section-title">Promotions</h2>
                            <p className="mt-1 text-sm text-muted">
                                Use basis points for percentage discounts: 1000 equals 10%.
                            </p>
                        </div>
                        <Tags size={17} className="text-brand" />
                    </div>
                    <form onSubmit={submitPromotion} className="mt-5 grid gap-3 sm:grid-cols-2">
                        <label>
                            <span className="field-label">Name</span>
                            <input
                                className="field"
                                value={promotionForm.data.name}
                                onChange={(event) => promotionForm.setData('name', event.target.value)}
                                placeholder="Summer discount"
                            />
                        </label>
                        <label>
                            <span className="field-label">Code</span>
                            <input
                                className="field uppercase"
                                value={promotionForm.data.code}
                                onChange={(event) => promotionForm.setData('code', event.target.value.toUpperCase())}
                                placeholder="SUMMER10"
                            />
                        </label>
                        <label>
                            <span className="field-label">Type</span>
                            <select
                                className="field"
                                value={promotionForm.data.type}
                                onChange={(event) => promotionForm.setData('type', event.target.value)}
                            >
                                <option value="percent">Percent</option>
                                <option value="fixed">Fixed minor units</option>
                                <option value="free_days">Free days</option>
                            </select>
                        </label>
                        <label>
                            <span className="field-label">
                                Value {promotionForm.data.type === 'percent' ? '(%)' : ''}
                            </span>
                            <input
                                className="field"
                                type="number"
                                min="0"
                                step={promotionForm.data.type === 'percent' ? '0.01' : '1'}
                                value={promotionForm.data.value}
                                onChange={(event) => promotionForm.setData('value', event.target.value)}
                            />
                            {promotionForm.errors.value && <p className="field-error">{promotionForm.errors.value}</p>}
                        </label>
                        <label>
                            <span className="field-label">Starts</span>
                            <input
                                className="field"
                                type="date"
                                value={promotionForm.data.starts_at}
                                onChange={(event) => promotionForm.setData('starts_at', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">Ends (optional)</span>
                            <input
                                className="field"
                                type="date"
                                value={promotionForm.data.ends_at}
                                onChange={(event) => promotionForm.setData('ends_at', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">Max redemptions</span>
                            <input
                                className="field"
                                type="number"
                                min="1"
                                value={promotionForm.data.max_redemptions}
                                onChange={(event) => promotionForm.setData('max_redemptions', event.target.value)}
                                placeholder="Unlimited"
                            />
                        </label>
                        <fieldset className="sm:col-span-2">
                            <legend className="field-label">Apply to plans (blank means all plans)</legend>
                            <div className="mt-2 flex flex-wrap gap-3">
                                {availablePlans.map((plan) => (
                                    <label
                                        key={plan.public_id}
                                        className="inline-flex items-center gap-2 text-sm text-muted"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={selectedPromoPlans.includes(plan.public_id)}
                                            onChange={() =>
                                                setSelectedPromoPlans((current) =>
                                                    current.includes(plan.public_id)
                                                        ? current.filter((id) => id !== plan.public_id)
                                                        : [...current, plan.public_id],
                                                )
                                            }
                                        />
                                        {plan.name}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <button
                            type="submit"
                            className="button-secondary sm:col-span-2"
                            disabled={promotionForm.processing}
                        >
                            <Plus size={15} /> Add promotion
                        </button>
                    </form>
                    <div className="mt-5 space-y-2 border-t border-line pt-5">
                        {promotions.map((promotion) => (
                            <div
                                key={promotion.public_id}
                                className="flex items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm"
                            >
                                <div>
                                    <p className="font-semibold">
                                        {promotion.name} · {promotion.code}
                                    </p>
                                    <p className="text-xs text-muted">
                                        {promotion.type === 'percent' ? `${promotion.value / 100}%` : promotion.value} ·{' '}
                                        {formatDate(promotion.starts_at)}
                                        {promotion.ends_at ? ` → ${formatDate(promotion.ends_at)}` : ''} ·{' '}
                                        {promotion.is_active ? 'active' : 'archived'}
                                    </p>
                                </div>
                                {promotion.is_active && (
                                    <button
                                        type="button"
                                        className="text-muted hover:text-coral"
                                        onClick={() => router.delete(`/plans/promotions/${promotion.public_id}`)}
                                        aria-label={`Archive ${promotion.code}`}
                                    >
                                        <Archive size={15} />
                                    </button>
                                )}
                            </div>
                        ))}
                        {promotions.length === 0 && <p className="text-sm text-muted">No promotions yet.</p>}
                    </div>
                </section>
            </div>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Tags size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{plans.total.toLocaleString()} plan(s)</p>
                    </div>
                    <p className="text-xs text-muted">Historical prices stay attached to their effective dates.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Plan</th>
                                <th className="px-5 py-3.5 text-start">Speed</th>
                                <th className="px-5 py-3.5 text-start">Current price</th>
                                <th className="px-5 py-3.5 text-start">Term</th>
                                <th className="px-5 py-3.5 text-start">Services</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {plans.data.map((plan) => (
                                <tr key={plan.public_id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{plan.name}</p>
                                        <p className="mt-1 text-xs text-muted">{plan.slug}</p>
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {plan.download_kbps / 1000} / {plan.upload_kbps / 1000} Mbps
                                    </td>
                                    <td className="px-5 py-4 text-sm font-semibold">
                                        {plan.price
                                            ? formatMoney(plan.price.amount_minor, plan.price.currency)
                                            : 'No effective price'}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.duration_days} days</td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.services_count}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={plan.status} />
                                    </td>
                                </tr>
                            ))}
                            {plans.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <Tags className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">No plans match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {plans.current_page} of {plans.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {plans.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === plans.links.length - 1;
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
