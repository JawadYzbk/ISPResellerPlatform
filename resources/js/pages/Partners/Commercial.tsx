import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, CalendarRange, Check, Edit3, Plus, Save, WalletCards, X } from 'lucide-react';
import { useEffect, useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { currencyFractionDigits, formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { createIdempotencyKey } from '@/lib/idempotency';
import type { PageProps } from '@/types';

type Partner = {
    id: string;
    name: string;
    code: string;
    currency?: string;
    credit_limit?: number;
    low_balance_threshold?: number;
    status?: string;
    wallet?: {
        currency: string;
        balance_amount: number;
        available_amount: number;
    } | null;
};
type CatalogItem = {
    id: string;
    name: string;
    duration_days: number;
    currency: string;
    sell_amount_minor: number;
    buy_amount_minor: number | null;
    price_book: string | null;
};
type Settlement = {
    id: string;
    period_start: string;
    period_end: string;
    currency: string;
    opening_amount: number;
    activity_amount: number;
    closing_amount: number;
    due_amount: number;
    status: string;
};
type PriceBookOverride = {
    id: number;
    buy_amount_minor: number;
    sell_amount_minor: number;
    min_amount_minor: number | null;
    max_amount_minor: number | null;
    effective_from: string;
};
type PricingPlan = {
    id: string;
    name: string;
    duration_days: number;
    currency: string;
    base_amount_minor: number | null;
    override: PriceBookOverride | null;
};

type Props = PageProps & {
    partners: Partner[];
    selectedPartner: Partner | null;
    catalog: CatalogItem[];
    settlements: Settlement[];
    showCost: boolean;
    canManage: boolean;
    canFund: boolean;
    canApprove: boolean;
    currencies: CurrencyOption[];
    pricingPlans: PricingPlan[];
};

function PriceBookEditorRow({
    partnerId,
    plan,
    currencies,
}: {
    partnerId: string;
    plan: PricingPlan;
    currencies: CurrencyOption[];
}) {
    const form = useForm({
        plan_id: plan.id,
        currency: plan.currency,
        buy_amount_minor: String(plan.override?.buy_amount_minor ?? plan.base_amount_minor ?? ''),
        sell_amount_minor: String(plan.override?.sell_amount_minor ?? plan.base_amount_minor ?? ''),
        min_amount_minor:
            plan.override?.min_amount_minor === null || plan.override === null
                ? ''
                : String(plan.override.min_amount_minor),
        max_amount_minor:
            plan.override?.max_amount_minor === null || plan.override === null
                ? ''
                : String(plan.override.max_amount_minor),
        effective_from: plan.override?.effective_from ?? new Date().toISOString().slice(0, 10),
    });

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(`/partners/${partnerId}/price-books/items`, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={submit}
            className="grid gap-3 border-t border-line px-6 py-5 lg:grid-cols-[1.2fr_repeat(6,minmax(0,1fr))]"
        >
            <div className="min-w-0">
                <p className="font-semibold">{plan.name}</p>
                <p className="mt-1 text-xs text-muted">
                    {plan.duration_days} days · Base{' '}
                    {plan.base_amount_minor === null ? 'not set' : formatMoney(plan.base_amount_minor, plan.currency)}
                </p>
                {plan.override && (
                    <p className="mt-1 text-xs font-semibold text-brand">
                        Override from {plan.override.effective_from}
                    </p>
                )}
            </div>
            <label>
                <span className="field-label">Buy</span>
                <input
                    className="field"
                    type="number"
                    min="0"
                    value={form.data.buy_amount_minor}
                    onChange={(event) => form.setData('buy_amount_minor', event.target.value)}
                    required
                />
                {form.errors.buy_amount_minor && <p className="field-error">{form.errors.buy_amount_minor}</p>}
            </label>
            <label>
                <span className="field-label">Sell</span>
                <input
                    className="field"
                    type="number"
                    min="0"
                    value={form.data.sell_amount_minor}
                    onChange={(event) => form.setData('sell_amount_minor', event.target.value)}
                    required
                />
                {form.errors.sell_amount_minor && <p className="field-error">{form.errors.sell_amount_minor}</p>}
            </label>
            <label>
                <span className="field-label">Floor</span>
                <input
                    className="field"
                    type="number"
                    min="0"
                    value={form.data.min_amount_minor}
                    onChange={(event) => form.setData('min_amount_minor', event.target.value)}
                />
                {form.errors.min_amount_minor && <p className="field-error">{form.errors.min_amount_minor}</p>}
            </label>
            <label>
                <span className="field-label">Ceiling</span>
                <input
                    className="field"
                    type="number"
                    min="0"
                    value={form.data.max_amount_minor}
                    onChange={(event) => form.setData('max_amount_minor', event.target.value)}
                />
                {form.errors.max_amount_minor && <p className="field-error">{form.errors.max_amount_minor}</p>}
            </label>
            <label>
                <span className="field-label">Currency</span>
                <CurrencyCombobox
                    className="field"
                    value={form.data.currency}
                    currencies={currencies}
                    onChange={(value) => form.setData('currency', value)}
                />
                {form.errors.currency && <p className="field-error">{form.errors.currency}</p>}
            </label>
            <label>
                <span className="field-label">Effective from</span>
                <input
                    className="field"
                    type="date"
                    value={form.data.effective_from}
                    onChange={(event) => form.setData('effective_from', event.target.value)}
                    required
                />
                {form.errors.effective_from && <p className="field-error">{form.errors.effective_from}</p>}
            </label>
            <div className="flex items-end">
                <button type="submit" className="button-primary w-full" disabled={form.processing}>
                    <Save size={15} /> Save price
                </button>
            </div>
        </form>
    );
}

export default function Commercial({
    partners,
    selectedPartner,
    catalog,
    settlements,
    showCost,
    canManage,
    canFund,
    canApprove,
    currencies,
    pricingPlans,
}: Props) {
    const [editOpen, setEditOpen] = useState(false);
    const form = useForm({
        name: '',
        code: '',
        currency: selectedPartner?.currency ?? 'USD',
        parent_id: selectedPartner?.id ?? '',
        credit_limit: 0,
        low_balance_threshold: 0,
    });
    const editForm = useForm({
        name: selectedPartner?.name ?? '',
        code: selectedPartner?.code ?? '',
        credit_limit: selectedPartner?.credit_limit ?? 0,
        low_balance_threshold: selectedPartner?.low_balance_threshold ?? 0,
        status: selectedPartner?.status ?? 'active',
    });
    const walletForm = useForm({
        amount: '',
        idempotency_key: createIdempotencyKey('partner-wallet'),
    });
    const settlementForm = useForm({
        period_start: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
        period_end: new Date().toISOString().slice(0, 10),
        currency: selectedPartner?.currency ?? 'USD',
    });
    const settlementActionForm = useForm<{ settlement_id: string; status: string }>({ settlement_id: '', status: '' });

    useEffect(() => {
        settlementForm.setData('currency', selectedPartner?.currency ?? 'USD');
    }, [selectedPartner?.currency, settlementForm]);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/partners', { preserveScroll: true, onSuccess: () => form.reset() });
    };

    const startEdit = () => {
        if (!selectedPartner) return;
        editForm.setData({
            name: selectedPartner.name,
            code: selectedPartner.code,
            credit_limit: selectedPartner.credit_limit ?? 0,
            low_balance_threshold: selectedPartner.low_balance_threshold ?? 0,
            status: selectedPartner.status ?? 'active',
        });
        editForm.clearErrors();
        setEditOpen(true);
    };

    const cancelEdit = () => {
        setEditOpen(false);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitEdit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedPartner) return;
        editForm.patch(`/partners/${selectedPartner.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditOpen(false),
        });
    };

    const submitWalletFunding = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedPartner) return;
        const currency = selectedPartner.wallet?.currency ?? selectedPartner.currency ?? 'USD';
        const amount = parseMoneyToMinor(walletForm.data.amount, currency);
        if (amount === null) {
            walletForm.setError('amount', 'Enter a valid positive amount.');
            return;
        }

        walletForm.clearErrors('amount');
        walletForm.transform((data) => ({ ...data, amount }));
        walletForm.post(`/partners/${selectedPartner.id}/wallet/fund`, {
            preserveScroll: true,
            onSuccess: () => {
                walletForm.reset('amount');
                walletForm.setData('idempotency_key', createIdempotencyKey('partner-wallet'));
            },
        });
    };

    const submitSettlement = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selectedPartner) return;
        settlementForm.post(`/partners/${selectedPartner.id}/settlements`, { preserveScroll: true });
    };

    const actOnSettlement = (settlementId: string, action: 'approve' | 'pay') => {
        settlementActionForm.setData('settlement_id', settlementId);
        settlementActionForm.post(`/settlements/${settlementId}/${action}`, {
            preserveScroll: true,
            onFinish: () => settlementActionForm.reset('settlement_id'),
        });
    };

    return (
        <AppLayout>
            <Head title="Partner commercial" />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} />
                Back to overview
            </Link>
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Partner commercial</p>
                    <h1 className="page-title">Prices and settlements</h1>
                    <p className="page-subtitle">
                        {selectedPartner
                            ? `${selectedPartner.name} · ${selectedPartner.code}`
                            : 'No partner accounts configured'}
                    </p>
                </div>
                {partners.length > 1 && (
                    <div className="flex flex-wrap gap-2">
                        {partners.map((partner) => (
                            <Link
                                key={partner.id}
                                href={`/partners/commercial?partner=${partner.id}`}
                                className={`button-quiet ${partner.id === selectedPartner?.id ? 'bg-brand-soft text-brand' : ''}`}
                            >
                                {partner.code}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
            {canManage && (
                <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                            <Plus size={19} />
                        </div>
                        <div>
                            <h2 className="section-title">Add reseller account</h2>
                            <p className="mt-1 text-sm text-muted">
                                Create a partner wallet and place it in the visible hierarchy.
                            </p>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <label>
                            <span className="field-label">Name</span>
                            <input
                                className="field"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                            />
                            {form.errors.name && <span className="field-error">{form.errors.name}</span>}
                        </label>
                        <label>
                            <span className="field-label">Code</span>
                            <input
                                className="field"
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value)}
                            />
                            {form.errors.code && <span className="field-error">{form.errors.code}</span>}
                        </label>
                        <label>
                            <span className="field-label">Currency</span>
                            <CurrencyCombobox
                                id="partner_currency"
                                className="field uppercase"
                                value={form.data.currency}
                                currencies={currencies}
                                onChange={(value) => form.setData('currency', value)}
                            />
                            {form.errors.currency && <span className="field-error">{form.errors.currency}</span>}
                        </label>
                    </div>
                    <div className="grid gap-4 md:grid-cols-3">
                        <label>
                            <span className="field-label">Parent account</span>
                            <ResponsiveSelect
                                className="field"
                                value={form.data.parent_id}
                                onChange={(event) => form.setData('parent_id', event.target.value)}
                            >
                                <option value="">Tenant account</option>
                                {partners.map((partner) => (
                                    <option key={partner.id} value={partner.id}>
                                        {partner.name} · {partner.code}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {form.errors.parent_id && <span className="field-error">{form.errors.parent_id}</span>}
                        </label>
                        <label>
                            <span className="field-label">Credit limit</span>
                            <input
                                className="field"
                                type="number"
                                min="0"
                                value={form.data.credit_limit}
                                onChange={(event) => form.setData('credit_limit', Number(event.target.value))}
                            />
                            {form.errors.credit_limit && (
                                <span className="field-error">{form.errors.credit_limit}</span>
                            )}
                        </label>
                        <label>
                            <span className="field-label">Low balance alert</span>
                            <input
                                className="field"
                                type="number"
                                min="0"
                                value={form.data.low_balance_threshold}
                                onChange={(event) => form.setData('low_balance_threshold', Number(event.target.value))}
                            />
                            {form.errors.low_balance_threshold && (
                                <span className="field-error">{form.errors.low_balance_threshold}</span>
                            )}
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={form.processing}>
                            Create partner
                        </button>
                    </div>
                </form>
            )}
            {selectedPartner && canManage && (
                <section className="card mt-8 p-6">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <p className="section-title">Partner account</p>
                            <p className="mt-1 text-sm text-muted">
                                Update operating limits and status. Currency and hierarchy stay fixed after creation.
                            </p>
                        </div>
                        {!editOpen && (
                            <button type="button" className="button-quiet" onClick={startEdit}>
                                <Edit3 size={15} /> Edit account
                            </button>
                        )}
                    </div>
                    {editOpen && (
                        <form onSubmit={submitEdit} className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <label>
                                <span className="field-label">Name</span>
                                <input
                                    className="field"
                                    value={editForm.data.name}
                                    onChange={(event) => editForm.setData('name', event.target.value)}
                                    required
                                />
                                {editForm.errors.name && <span className="field-error">{editForm.errors.name}</span>}
                            </label>
                            <label>
                                <span className="field-label">Code</span>
                                <input
                                    className="field uppercase"
                                    value={editForm.data.code}
                                    onChange={(event) => editForm.setData('code', event.target.value)}
                                    required
                                />
                                {editForm.errors.code && <span className="field-error">{editForm.errors.code}</span>}
                            </label>
                            <label>
                                <span className="field-label">Credit limit</span>
                                <input
                                    className="field"
                                    type="number"
                                    min="0"
                                    value={editForm.data.credit_limit}
                                    onChange={(event) => editForm.setData('credit_limit', Number(event.target.value))}
                                    required
                                />
                                {editForm.errors.credit_limit && (
                                    <span className="field-error">{editForm.errors.credit_limit}</span>
                                )}
                            </label>
                            <label>
                                <span className="field-label">Low balance alert</span>
                                <input
                                    className="field"
                                    type="number"
                                    min="0"
                                    value={editForm.data.low_balance_threshold}
                                    onChange={(event) =>
                                        editForm.setData('low_balance_threshold', Number(event.target.value))
                                    }
                                    required
                                />
                                {editForm.errors.low_balance_threshold && (
                                    <span className="field-error">{editForm.errors.low_balance_threshold}</span>
                                )}
                            </label>
                            <label>
                                <span className="field-label">Status</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={editForm.data.status}
                                    onChange={(event) => editForm.setData('status', event.target.value)}
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                </ResponsiveSelect>
                                {editForm.errors.status && (
                                    <span className="field-error">{editForm.errors.status}</span>
                                )}
                            </label>
                            <div className="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                                <button type="submit" className="button-primary" disabled={editForm.processing}>
                                    <Save size={15} /> Save changes
                                </button>
                                <button
                                    type="button"
                                    className="button-quiet"
                                    disabled={editForm.processing}
                                    onClick={cancelEdit}
                                >
                                    <X size={15} /> Cancel
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            )}
            {selectedPartner && (
                <section className="card mt-8 p-6">
                    <div className="flex items-start gap-3">
                        <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                            <WalletCards size={19} />
                        </div>
                        <div>
                            <h2 className="section-title">Wallet operations</h2>
                            <p className="mt-1 text-sm text-muted">
                                Fund the reseller balance and close commission periods with an auditable settlement.
                            </p>
                        </div>
                    </div>
                    <div className="mt-5 grid gap-4 lg:grid-cols-[0.8fr_1.2fr_1.2fr]">
                        <div className="rounded-xl border border-line bg-sand p-4">
                            <p className="field-label">Current balance</p>
                            <p className="mt-2 font-display text-2xl font-semibold">
                                {formatMoney(
                                    selectedPartner.wallet?.balance_amount ?? 0,
                                    selectedPartner.wallet?.currency ?? selectedPartner.currency ?? 'USD',
                                )}
                            </p>
                            <p className="mt-1 text-xs text-muted">
                                Available with credit:{' '}
                                {formatMoney(
                                    selectedPartner.wallet?.available_amount ?? 0,
                                    selectedPartner.wallet?.currency ?? selectedPartner.currency ?? 'USD',
                                )}
                            </p>
                        </div>
                        {canFund && (
                            <form onSubmit={submitWalletFunding} className="rounded-xl border border-line p-4">
                                <p className="font-semibold">Fund wallet</p>
                                <p className="mt-1 text-xs text-muted">Cash funding is posted to the tenant ledger.</p>
                                <label className="mt-4 block">
                                    <span className="field-label">
                                        Amount ({selectedPartner.wallet?.currency ?? selectedPartner.currency ?? 'USD'})
                                    </span>
                                    <input
                                        className="field"
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step={
                                            currencyFractionDigits(
                                                selectedPartner.wallet?.currency ?? selectedPartner.currency ?? 'USD',
                                            ) === 0
                                                ? '1'
                                                : '0.01'
                                        }
                                        value={walletForm.data.amount}
                                        onChange={(event) => walletForm.setData('amount', event.target.value)}
                                        required
                                    />
                                    {walletForm.errors.amount && (
                                        <p className="field-error">{walletForm.errors.amount}</p>
                                    )}
                                </label>
                                <button type="submit" className="button-primary mt-4" disabled={walletForm.processing}>
                                    <Plus size={15} /> Fund wallet
                                </button>
                            </form>
                        )}
                        {canApprove && (
                            <form onSubmit={submitSettlement} className="rounded-xl border border-line p-4">
                                <p className="font-semibold">Create settlement</p>
                                <p className="mt-1 text-xs text-muted">
                                    Capture wallet activity and accrued commission for a period.
                                </p>
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    <label>
                                        <span className="field-label">From</span>
                                        <input
                                            className="field"
                                            type="date"
                                            value={settlementForm.data.period_start}
                                            onChange={(event) =>
                                                settlementForm.setData('period_start', event.target.value)
                                            }
                                            required
                                        />
                                    </label>
                                    <label>
                                        <span className="field-label">Through</span>
                                        <input
                                            className="field"
                                            type="date"
                                            value={settlementForm.data.period_end}
                                            onChange={(event) =>
                                                settlementForm.setData('period_end', event.target.value)
                                            }
                                            required
                                        />
                                    </label>
                                </div>
                                {(settlementForm.errors.period_start ||
                                    settlementForm.errors.period_end ||
                                    settlementForm.errors.currency) && (
                                    <p className="field-error">
                                        {settlementForm.errors.period_start ??
                                            settlementForm.errors.period_end ??
                                            settlementForm.errors.currency}
                                    </p>
                                )}
                                <button
                                    type="submit"
                                    className="button-primary mt-4"
                                    disabled={settlementForm.processing}
                                >
                                    <CalendarRange size={15} /> Create statement
                                </button>
                            </form>
                        )}
                    </div>
                </section>
            )}
            {selectedPartner && canManage && (
                <section className="card mt-8 overflow-hidden">
                    <div className="flex items-start justify-between gap-4 px-6 py-5">
                        <div>
                            <p className="section-title">Reseller price book</p>
                            <p className="mt-1 text-sm text-muted">
                                Set the partner buy price, retail price, floor, ceiling, and effective date. Existing
                                renewals keep their original snapshot.
                            </p>
                        </div>
                        <span className="status-badge">{pricingPlans.length} plans</span>
                    </div>
                    {pricingPlans.length > 0 ? (
                        pricingPlans.map((plan) => (
                            <PriceBookEditorRow
                                key={plan.id}
                                partnerId={selectedPartner.id}
                                plan={plan}
                                currencies={currencies}
                            />
                        ))
                    ) : (
                        <p className="border-t border-line px-6 py-8 text-sm text-muted">
                            Create an active plan before setting reseller prices.
                        </p>
                    )}
                </section>
            )}
            {selectedPartner ? (
                <div className="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                    <section className="card overflow-hidden">
                        <div className="flex items-center gap-3 border-b border-line px-6 py-5">
                            <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                <BookOpen size={19} />
                            </div>
                            <div>
                                <h2 className="section-title">Reseller catalog</h2>
                                <p className="mt-1 text-sm text-muted">Effective sell prices for this partner.</p>
                            </div>
                        </div>
                        <div className="divide-y divide-line">
                            {catalog.map((item) => (
                                <div key={item.id} className="flex items-center justify-between gap-4 px-6 py-4">
                                    <div>
                                        <p className="font-semibold">{item.name}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {item.duration_days} days · {item.price_book ?? 'Default price book'}
                                        </p>
                                    </div>
                                    <div className="text-end">
                                        <p className="font-display text-lg font-semibold">
                                            {formatMoney(item.sell_amount_minor, item.currency)}
                                        </p>
                                        {showCost && item.buy_amount_minor !== null && (
                                            <p className="mt-1 text-xs text-muted">
                                                Buy {formatMoney(item.buy_amount_minor, item.currency)}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                            {catalog.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">
                                    No effective price book items are available.
                                </p>
                            )}
                        </div>
                    </section>
                    <section className="card overflow-hidden">
                        <div className="flex items-center gap-3 border-b border-line px-6 py-5">
                            <div className="grid size-10 place-items-center rounded-xl bg-sand text-ink">
                                <WalletCards size={19} />
                            </div>
                            <div>
                                <h2 className="section-title">Settlement statements</h2>
                                <p className="mt-1 text-sm text-muted">Wallet activity and commission due.</p>
                            </div>
                        </div>
                        <div className="divide-y divide-line">
                            {settlements.map((settlement) => (
                                <div key={settlement.id} className="px-6 py-4">
                                    <div className="flex items-center justify-between gap-4">
                                        <p className="font-semibold">
                                            {formatDate(settlement.period_start)} – {formatDate(settlement.period_end)}
                                        </p>
                                        <span className="rounded-full bg-sand px-2.5 py-1 text-xs font-semibold capitalize">
                                            {settlement.status}
                                        </span>
                                    </div>
                                    <div className="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p className="text-xs text-muted">Closing wallet</p>
                                            <p className="mt-1 font-semibold">
                                                {formatMoney(settlement.closing_amount, settlement.currency)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">Commission due</p>
                                            <p className="mt-1 font-semibold">
                                                {formatMoney(settlement.due_amount, settlement.currency)}
                                            </p>
                                        </div>
                                    </div>
                                    {canApprove && settlement.status !== 'paid' && (
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            {settlement.status === 'draft' && (
                                                <ConfirmDialog
                                                    title="Approve settlement statement?"
                                                    description="Approval freezes the commission amount for this period so it can be paid against the ledger."
                                                    confirmLabel="Approve statement"
                                                    onConfirm={() => actOnSettlement(settlement.id, 'approve')}
                                                >
                                                    <button
                                                        type="button"
                                                        className="button-quiet"
                                                        disabled={settlementActionForm.processing}
                                                    >
                                                        <Check size={15} /> Approve
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {settlement.status === 'approved' && (
                                                <ConfirmDialog
                                                    title="Pay settlement statement?"
                                                    description={`Post the approved commission settlement of ${formatMoney(settlement.due_amount, settlement.currency)} to the tenant ledger.`}
                                                    confirmLabel="Pay settlement"
                                                    onConfirm={() => actOnSettlement(settlement.id, 'pay')}
                                                >
                                                    <button
                                                        type="button"
                                                        className="button-primary"
                                                        disabled={settlementActionForm.processing}
                                                    >
                                                        <Check size={15} /> Pay settlement
                                                    </button>
                                                </ConfirmDialog>
                                            )}
                                            {settlementActionForm.errors.status && (
                                                <p className="field-error">{settlementActionForm.errors.status}</p>
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}
                            {settlements.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">No settlement statements yet.</p>
                            )}
                        </div>
                    </section>
                </div>
            ) : (
                <div className="card mt-8 p-6">
                    <h2 className="section-title">Partner setup required</h2>
                    <p className="mt-2 max-w-2xl text-sm leading-6 text-muted">
                        No partner accounts exist for this tenant yet. Create a partner through the partner provisioning
                        workflow before maintaining reseller prices or settlement statements.
                    </p>
                </div>
            )}
        </AppLayout>
    );
}
