import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Edit3, Plus, Save, WalletCards, X } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import type { PageProps } from '@/types';

type Partner = {
    id: string;
    name: string;
    code: string;
    currency?: string;
    credit_limit?: number;
    low_balance_threshold?: number;
    status?: string;
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

type Props = PageProps & {
    partners: Partner[];
    selectedPartner: Partner | null;
    catalog: CatalogItem[];
    settlements: Settlement[];
    showCost: boolean;
    canManage: boolean;
    currencies: CurrencyOption[];
};

export default function Commercial({
    partners,
    selectedPartner,
    catalog,
    settlements,
    showCost,
    canManage,
    currencies,
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
                                {editForm.errors.status && <span className="field-error">{editForm.errors.status}</span>}
                            </label>
                            <div className="flex items-end gap-2 md:col-span-2 xl:col-span-5">
                                <button type="submit" className="button-primary" disabled={editForm.processing}>
                                    <Save size={15} /> Save changes
                                </button>
                                <button type="button" className="button-quiet" disabled={editForm.processing} onClick={cancelEdit}>
                                    <X size={15} /> Cancel
                                </button>
                            </div>
                        </form>
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
