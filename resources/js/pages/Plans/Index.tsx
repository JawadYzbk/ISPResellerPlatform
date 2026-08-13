import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ChevronLeft, ChevronRight, Edit3, Gauge, Plus, Search, Tags } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { currencyFractionDigits, formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
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
type UsageRate = {
    public_id: string;
    plan_id: string;
    plan_name: string;
    name: string;
    metric: 'total_octets';
    included_bytes: number;
    unit_bytes: number;
    amount_minor: number;
    currency: string;
    rounding: 'ceil' | 'floor' | 'half_up';
    effective_from: string;
    effective_to: string | null;
    status: 'active' | 'inactive';
};
type AvailablePlan = { public_id: string; name: string };

type Props = PageProps & {
    plans: Paginator<Plan>;
    filters: { status?: string; search?: string };
    addons: Addon[];
    usageRates: UsageRate[];
    promotions: Promotion[];
    availablePlans: AvailablePlan[];
    currencies: CurrencyOption[];
};

export default function PlansIndex({
    plans,
    filters,
    addons,
    usageRates,
    promotions,
    availablePlans,
    currencies,
}: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedPromoPlans, setSelectedPromoPlans] = useState<string[]>([]);
    const [editingAddonId, setEditingAddonId] = useState<string | null>(null);
    const [editingUsageRateId, setEditingUsageRateId] = useState<string | null>(null);
    const [editingPromotionId, setEditingPromotionId] = useState<string | null>(null);
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
    const usageRateForm = useForm({
        plan_public_id: availablePlans[0]?.public_id ?? '',
        name: 'Data overage',
        included_gb: '0',
        unit_gb: '1',
        amount: '',
        currency: 'USD',
        rounding: 'ceil',
        effective_from: new Date().toISOString().slice(0, 10),
        effective_to: '',
        status: 'active',
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
            addonForm.setError('amount', t('plan.valid_non_negative_amount'));
            return;
        }
        addonForm.transform((data) => ({ ...data, amount_minor: amountMinor }));
        const options = {
            onSuccess: () => {
                addonForm.reset();
                setEditingAddonId(null);
            },
        };
        if (editingAddonId) {
            addonForm.put(`/plans/addons/${editingAddonId}`, options);
        } else {
            addonForm.post('/plans/addons', options);
        }
    };

    const submitPromotion = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const rawValue = Number(promotionForm.data.value);
        if (!Number.isFinite(rawValue) || rawValue <= 0) {
            promotionForm.setError('value', t('plan.positive_promotion_value'));
            return;
        }
        promotionForm.transform((data) => ({
            ...data,
            value: data.type === 'percent' ? Math.round(rawValue * 100) : Math.round(rawValue),
            applies_to: selectedPromoPlans,
        }));
        const options = {
            onSuccess: () => {
                promotionForm.reset();
                setSelectedPromoPlans([]);
                setEditingPromotionId(null);
            },
        };
        if (editingPromotionId) {
            promotionForm.put(`/plans/promotions/${editingPromotionId}`, options);
        } else {
            promotionForm.post('/plans/promotions', options);
        }
    };

    const submitUsageRate = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const amountMinor = parseMoneyToMinor(usageRateForm.data.amount, usageRateForm.data.currency);
        if (amountMinor === null) {
            usageRateForm.setError('amount', t('plan.valid_non_negative_amount'));
            return;
        }
        usageRateForm.transform((data) => ({ ...data, amount_minor: amountMinor }));
        const options = {
            onSuccess: () => {
                usageRateForm.reset();
                setEditingUsageRateId(null);
            },
        };
        if (editingUsageRateId) {
            usageRateForm.put(`/plans/usage-rates/${editingUsageRateId}`, options);
        } else {
            usageRateForm.post('/plans/usage-rates', options);
        }
    };

    const editAddon = (addon: Addon) => {
        setEditingAddonId(addon.public_id);
        addonForm.setData({
            name: addon.name,
            slug: addon.slug,
            description: addon.description ?? '',
            amount: (addon.amount_minor / 10 ** currencyFractionDigits(addon.currency)).toString(),
            currency: addon.currency,
            billing_period_days: addon.billing_period_days?.toString() ?? '',
            status: addon.status,
        });
    };

    const editUsageRate = (rate: UsageRate) => {
        setEditingUsageRateId(rate.public_id);
        usageRateForm.setData({
            plan_public_id: rate.plan_id,
            name: rate.name,
            included_gb: (rate.included_bytes / 1_000_000_000).toString(),
            unit_gb: (rate.unit_bytes / 1_000_000_000).toString(),
            amount: (rate.amount_minor / 10 ** currencyFractionDigits(rate.currency)).toString(),
            currency: rate.currency,
            rounding: rate.rounding,
            effective_from: rate.effective_from,
            effective_to: rate.effective_to ?? '',
            status: rate.status,
        });
    };

    const editPromotion = (promotion: Promotion) => {
        setEditingPromotionId(promotion.public_id);
        promotionForm.setData({
            name: promotion.name,
            code: promotion.code,
            type: promotion.type,
            value: promotion.type === 'percent' ? (promotion.value / 100).toString() : promotion.value.toString(),
            starts_at: promotion.starts_at.slice(0, 10),
            ends_at: promotion.ends_at?.slice(0, 10) ?? '',
            max_redemptions: promotion.max_redemptions?.toString() ?? '',
            is_active: promotion.is_active,
        });
        setSelectedPromoPlans(promotion.applies_to);
    };

    return (
        <AppLayout>
            <Head title={t('Plans')} />
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('plan.commercial_catalog')}</p>
                    <h1 className="page-title">{t('Plans')}</h1>
                    <p className="page-subtitle">{t('plan.manage_catalog_prices')}</p>
                </div>
                <Link href="/plans/create" className="button-primary">
                    <Plus size={16} /> {t('plan.new_plan')}
                </Link>
            </div>

            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">{t('plan.search_plans')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('plan.plan_name_or_slug')}
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
                        <option value="">{t('plan.all_statuses')}</option>
                        <option value="active">{t('Active')}</option>
                        <option value="inactive">{t('Inactive')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('plan.apply_filters')}
                </button>
            </form>

            <div className="mt-6 grid gap-6 xl:grid-cols-2">
                <section className="card p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="section-title">
                                {editingAddonId ? t('plan.edit_addon') : t('plan.addons')}
                            </h2>
                            <p className="mt-1 text-sm text-muted">{t('plan.addon_description')}</p>
                        </div>
                        <Tags size={17} className="text-brand" />
                    </div>
                    <form onSubmit={submitAddon} className="mt-5 grid gap-3 sm:grid-cols-2">
                        <label>
                            <span className="field-label">{t('Name')}</span>
                            <input
                                className="field"
                                value={addonForm.data.name}
                                onChange={(event) => addonForm.setData('name', event.target.value)}
                                placeholder={t('plan.static_ip')}
                            />
                            {addonForm.errors.name && <p className="field-error">{addonForm.errors.name}</p>}
                        </label>
                        <label>
                            <span className="field-label">{t('Price')}</span>
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
                            <span className="field-label">{t('Currency')}</span>
                            <CurrencyCombobox
                                id="addon_currency"
                                className="field uppercase"
                                value={addonForm.data.currency}
                                currencies={currencies}
                                onChange={(value) => addonForm.setData('currency', value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('plan.billing_period_days')}</span>
                            <input
                                className="field"
                                type="number"
                                min="1"
                                value={addonForm.data.billing_period_days}
                                onChange={(event) => addonForm.setData('billing_period_days', event.target.value)}
                                placeholder={t('plan.one_off_if_blank')}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('Status')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={addonForm.data.status}
                                onChange={(event) => addonForm.setData('status', event.target.value)}
                            >
                                <option value="active">{t('Active')}</option>
                                <option value="inactive">{t('Archived')}</option>
                            </ResponsiveSelect>
                            {addonForm.errors.status && <p className="field-error">{addonForm.errors.status}</p>}
                        </label>
                        <label className="sm:col-span-2">
                            <span className="field-label">{t('Description')}</span>
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
                            <Plus size={15} /> {editingAddonId ? t('plan.save_addon') : t('plan.add_addon')}
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
                                            ? `${addon.billing_period_days}-day ${t('plan.recurring')}`
                                            : t('plan.one_off')}
                                        · {t(addon.status === 'active' ? 'Active' : 'Archived')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <button
                                        type="button"
                                        className="text-muted hover:text-brand"
                                        onClick={() => editAddon(addon)}
                                        aria-label={t('Edit') + ' ' + addon.name}
                                    >
                                        <Edit3 size={15} />
                                    </button>
                                    {addon.status === 'active' && (
                                        <ConfirmDialog
                                            title={`${t('Archive')} ${addon.name}?`}
                                            description={t('plan.archive_addon_description')}
                                            confirmLabel={t('plan.archive_addon')}
                                            destructive
                                            onConfirm={() => router.delete(`/plans/addons/${addon.public_id}`)}
                                        >
                                            <button
                                                type="button"
                                                className="text-muted hover:text-coral"
                                                aria-label={t('Archive') + ' ' + addon.name}
                                            >
                                                <Archive size={15} />
                                            </button>
                                        </ConfirmDialog>
                                    )}
                                </div>
                            </div>
                        ))}
                        {addons.length === 0 && <p className="text-sm text-muted">{t('plan.no_addons')}</p>}
                    </div>
                </section>
                <section className="card p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="section-title">
                                {editingPromotionId ? t('plan.edit_promotion') : t('plan.promotions')}
                            </h2>
                            <p className="mt-1 text-sm text-muted">{t('plan.promotion_description')}</p>
                        </div>
                        <Tags size={17} className="text-brand" />
                    </div>
                    <form onSubmit={submitPromotion} className="mt-5 grid gap-3 sm:grid-cols-2">
                        <label>
                            <span className="field-label">{t('Name')}</span>
                            <input
                                className="field"
                                value={promotionForm.data.name}
                                onChange={(event) => promotionForm.setData('name', event.target.value)}
                                placeholder={t('plan.summer_discount')}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('Code')}</span>
                            <input
                                className="field uppercase"
                                value={promotionForm.data.code}
                                onChange={(event) => promotionForm.setData('code', event.target.value.toUpperCase())}
                                placeholder="SUMMER10"
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('Type')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={promotionForm.data.type}
                                onChange={(event) => promotionForm.setData('type', event.target.value)}
                            >
                                <option value="percent">{t('plan.percent')}</option>
                                <option value="fixed">{t('plan.fixed_minor_units')}</option>
                                <option value="free_days">{t('plan.free_days')}</option>
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">
                                {t('Value')} {promotionForm.data.type === 'percent' ? '(%)' : ''}
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
                            <span className="field-label">{t('plan.starts')}</span>
                            <input
                                className="field"
                                type="date"
                                value={promotionForm.data.starts_at}
                                onChange={(event) => promotionForm.setData('starts_at', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('plan.ends_optional')}</span>
                            <input
                                className="field"
                                type="date"
                                value={promotionForm.data.ends_at}
                                onChange={(event) => promotionForm.setData('ends_at', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('plan.max_redemptions')}</span>
                            <input
                                className="field"
                                type="number"
                                min="1"
                                value={promotionForm.data.max_redemptions}
                                onChange={(event) => promotionForm.setData('max_redemptions', event.target.value)}
                                placeholder={t('Unlimited')}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('Status')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={promotionForm.data.is_active ? 'active' : 'inactive'}
                                onChange={(event) =>
                                    promotionForm.setData('is_active', event.target.value === 'active')
                                }
                            >
                                <option value="active">{t('Active')}</option>
                                <option value="inactive">{t('Archived')}</option>
                            </ResponsiveSelect>
                        </label>
                        <fieldset className="sm:col-span-2">
                            <legend className="field-label">{t('plan.apply_to_plans')}</legend>
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
                            <Plus size={15} /> {editingPromotionId ? t('plan.save_promotion') : t('plan.add_promotion')}
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
                                        {t(promotion.is_active ? 'Active' : 'Archived')}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <button
                                        type="button"
                                        className="text-muted hover:text-brand"
                                        onClick={() => editPromotion(promotion)}
                                        aria-label={t('Edit') + ' ' + promotion.code}
                                    >
                                        <Edit3 size={15} />
                                    </button>
                                    {promotion.is_active && (
                                        <ConfirmDialog
                                            title={`${t('Archive')} ${promotion.code}?`}
                                            description={t('plan.archive_promotion_description')}
                                            confirmLabel={t('plan.archive_promotion')}
                                            destructive
                                            onConfirm={() => router.delete(`/plans/promotions/${promotion.public_id}`)}
                                        >
                                            <button
                                                type="button"
                                                className="text-muted hover:text-coral"
                                                aria-label={t('Archive') + ' ' + promotion.code}
                                            >
                                                <Archive size={15} />
                                            </button>
                                        </ConfirmDialog>
                                    )}
                                </div>
                            </div>
                        ))}
                        {promotions.length === 0 && <p className="text-sm text-muted">{t('plan.no_promotions')}</p>}
                    </div>
                </section>
            </div>

            <section className="card mt-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h2 className="section-title text-balance">{t('plan.usage_billing')}</h2>
                        <p className="mt-1 text-sm text-muted text-pretty">{t('plan.usage_description')}</p>
                    </div>
                    <Gauge size={18} className="text-brand" />
                </div>
                <form onSubmit={submitUsageRate} className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label>
                        <span className="field-label">{t('Plan')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={usageRateForm.data.plan_public_id}
                            onChange={(event) => usageRateForm.setData('plan_public_id', event.target.value)}
                        >
                            {availablePlans.map((plan) => (
                                <option key={plan.public_id} value={plan.public_id}>
                                    {plan.name}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {usageRateForm.errors.plan_public_id && (
                            <p className="field-error">{usageRateForm.errors.plan_public_id}</p>
                        )}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.rate_name')}</span>
                        <input
                            className="field"
                            value={usageRateForm.data.name}
                            onChange={(event) => usageRateForm.setData('name', event.target.value)}
                            placeholder={t('plan.data_overage')}
                        />
                        {usageRateForm.errors.name && <p className="field-error">{usageRateForm.errors.name}</p>}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.included_gb')}</span>
                        <input
                            className="field"
                            type="number"
                            min="0"
                            step="0.01"
                            value={usageRateForm.data.included_gb}
                            onChange={(event) => usageRateForm.setData('included_gb', event.target.value)}
                        />
                        {usageRateForm.errors.included_gb && (
                            <p className="field-error">{usageRateForm.errors.included_gb}</p>
                        )}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.price_per_gb')}</span>
                        <input
                            className="field"
                            inputMode="decimal"
                            value={usageRateForm.data.amount}
                            onChange={(event) => usageRateForm.setData('amount', event.target.value)}
                            placeholder="1.00"
                        />
                        {usageRateForm.errors.amount && <p className="field-error">{usageRateForm.errors.amount}</p>}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.billing_unit_gb')}</span>
                        <input
                            className="field"
                            type="number"
                            min="0.01"
                            step="0.01"
                            value={usageRateForm.data.unit_gb}
                            onChange={(event) => usageRateForm.setData('unit_gb', event.target.value)}
                        />
                        {usageRateForm.errors.unit_gb && <p className="field-error">{usageRateForm.errors.unit_gb}</p>}
                    </label>
                    <label>
                        <span className="field-label">{t('Currency')}</span>
                        <CurrencyCombobox
                            id="usage_rate_currency"
                            className="field uppercase"
                            value={usageRateForm.data.currency}
                            currencies={currencies}
                            onChange={(value) => usageRateForm.setData('currency', value)}
                        />
                        {usageRateForm.errors.currency && (
                            <p className="field-error">{usageRateForm.errors.currency}</p>
                        )}
                    </label>
                    <label>
                        <span className="field-label">{t('Rounding')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={usageRateForm.data.rounding}
                            onChange={(event) => usageRateForm.setData('rounding', event.target.value)}
                        >
                            <option value="ceil">{t('Round up')}</option>
                            <option value="half_up">{t('Half up')}</option>
                            <option value="floor">{t('Round down')}</option>
                        </ResponsiveSelect>
                    </label>
                    <label>
                        <span className="field-label">{t('plan.effective_from')}</span>
                        <input
                            className="field"
                            type="date"
                            value={usageRateForm.data.effective_from}
                            onChange={(event) => usageRateForm.setData('effective_from', event.target.value)}
                        />
                        {usageRateForm.errors.effective_from && (
                            <p className="field-error">{usageRateForm.errors.effective_from}</p>
                        )}
                    </label>
                    <label>
                        <span className="field-label">{t('plan.effective_to_optional')}</span>
                        <input
                            className="field"
                            type="date"
                            value={usageRateForm.data.effective_to}
                            onChange={(event) => usageRateForm.setData('effective_to', event.target.value)}
                        />
                        {usageRateForm.errors.effective_to && (
                            <p className="field-error">{usageRateForm.errors.effective_to}</p>
                        )}
                    </label>
                    <label>
                        <span className="field-label">{t('Status')}</span>
                        <ResponsiveSelect
                            className="field"
                            value={usageRateForm.data.status}
                            onChange={(event) => usageRateForm.setData('status', event.target.value)}
                        >
                            <option value="active">{t('Active')}</option>
                            <option value="inactive">{t('Archived')}</option>
                        </ResponsiveSelect>
                    </label>
                    <button type="submit" className="button-secondary self-end" disabled={usageRateForm.processing}>
                        <Plus size={15} /> {editingUsageRateId ? t('plan.save_usage_rate') : t('plan.add_usage_rate')}
                    </button>
                </form>
                <div className="mt-5 space-y-2 border-t border-line pt-5">
                    {usageRates.map((rate) => (
                        <div
                            key={rate.public_id}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-sm"
                        >
                            <div>
                                <p className="font-semibold">
                                    {rate.plan_name} · {rate.name}
                                </p>
                                <p className="text-xs text-muted">
                                    {rate.included_bytes / 1_000_000_000} GB {t('plan.included')} ·{' '}
                                    {formatMoney(rate.amount_minor, rate.currency)} / {rate.unit_bytes / 1_000_000_000}{' '}
                                    GB · {rate.rounding} · {t('from')} {formatDate(rate.effective_from)} ·{' '}
                                    {t(rate.status === 'active' ? 'Active' : 'Archived')}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    className="button-quiet"
                                    onClick={() => editUsageRate(rate)}
                                    aria-label={t('Edit') + ' ' + rate.name}
                                >
                                    <Edit3 size={15} /> {t('Edit')}
                                </button>
                                {rate.status === 'active' && (
                                    <ConfirmDialog
                                        title={`${t('Archive')} ${rate.name}?`}
                                        description={t('plan.archive_usage_rate_description')}
                                        confirmLabel={t('plan.archive_usage_rate')}
                                        destructive
                                        onConfirm={() => router.delete(`/plans/usage-rates/${rate.public_id}`)}
                                    >
                                        <button
                                            type="button"
                                            className="button-quiet text-danger"
                                            aria-label={t('Archive') + ' ' + rate.name}
                                        >
                                            <Archive size={15} />
                                        </button>
                                    </ConfirmDialog>
                                )}
                            </div>
                        </div>
                    ))}
                    {usageRates.length === 0 && <p className="text-sm text-muted">{t('plan.no_usage_rates')}</p>}
                </div>
            </section>

            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Tags size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {plans.total.toLocaleString()} {t('plan.count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('plan.historical_prices')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[980px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Plan')}</th>
                                <th className="px-5 py-3.5 text-start">{t('plan.speed')}</th>
                                <th className="px-5 py-3.5 text-start">{t('plan.current_price')}</th>
                                <th className="px-5 py-3.5 text-start">{t('plan.term')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Services')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-end">{t('Actions')}</th>
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
                                            : t('plan.no_effective_price')}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {plan.duration_days} {t('days')}
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">{plan.services_count}</td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={plan.status} />
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        <Link href={`/plans/${plan.public_id}/edit`} className="button-quiet">
                                            <Edit3 size={15} /> {t('Edit')}
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {plans.data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-5 py-16 text-center">
                                        <Tags className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('plan.no_matching_plans')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {plans.current_page} {t('of')} {plans.last_page}
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
