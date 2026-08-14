import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit3, ExternalLink, Network, Plus, Router as RouterIcon, Save, X } from 'lucide-react';
import { useState } from 'react';

import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Pop = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    status: Status;
    routers: { public_id: string; name: string; host: string; status: Status }[];
    upstream_links: {
        id: number;
        provider_name: string;
        capacity_mbps: number | null;
        monthly_cost_amount: number;
        currency: string;
        contract_start: string;
        contract_end: string | null;
        notes: string | null;
    }[];
};

type Props = {
    pop: Pop;
    canManage: boolean;
    statuses: Status[];
    currencies: CurrencyOption[];
};

export default function PopShowPage({ pop, canManage, statuses, currencies }: Props) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;
    const popForm = useForm({ name: pop.name, code: pop.code, address: pop.address ?? '', status: pop.status });
    const [editingLinkId, setEditingLinkId] = useState<number | null>(null);
    const linkForm = useForm({
        provider_name: '',
        capacity_mbps: '',
        monthly_cost_amount: '',
        currency: 'USD',
        contract_start: '',
        contract_end: '',
        notes: '',
    });
    const linkEditForm = useForm({
        provider_name: '',
        capacity_mbps: '',
        monthly_cost_amount: '',
        currency: currencies[0]?.code ?? 'USD',
        contract_start: '',
        contract_end: '',
        notes: '',
    });

    const submitPop = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        popForm.put('/operations/pops/' + pop.id);
    };

    const submitLink = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        linkForm.post('/operations/pops/' + pop.id + '/upstream-links', { onSuccess: () => linkForm.reset() });
    };

    const startLinkEdit = (link: Pop['upstream_links'][number]) => {
        setEditingLinkId(link.id);
        linkEditForm.setData({
            provider_name: link.provider_name,
            capacity_mbps: link.capacity_mbps === null ? '' : String(link.capacity_mbps),
            monthly_cost_amount: String(link.monthly_cost_amount),
            currency: link.currency,
            contract_start: link.contract_start,
            contract_end: link.contract_end ?? '',
            notes: link.notes ?? '',
        });
        linkEditForm.clearErrors();
    };

    const cancelLinkEdit = () => {
        setEditingLinkId(null);
        linkEditForm.reset();
        linkEditForm.clearErrors();
    };

    const saveLink = (link: Pop['upstream_links'][number]) => {
        linkEditForm.patch('/operations/upstream-links/' + link.id, {
            preserveScroll: true,
            onSuccess: cancelLinkEdit,
        });
    };

    return (
        <AppLayout>
            <Head title={pop.name} />
            <Link
                href="/operations/pops"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to POPs')}
            </Link>

            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('Network inventory')}</p>
                    <div className="mt-2 flex items-center gap-3">
                        <h1 className="page-title">{pop.name}</h1>
                        <StatusBadge status={pop.status} />
                    </div>
                    <p className="page-subtitle">
                        {pop.code} · {pop.address ?? t('No address recorded')}
                    </p>
                </div>
            </div>

            {canManage && (
                <form onSubmit={submitPop} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Network size={17} className="text-brand" />
                        <h2 className="section-title">{t('Edit POP')}</h2>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">{t('Name')}</span>
                            <input
                                id="pop-edit-name"
                                className="field"
                                {...fieldA11y('pop-edit-name', popForm.errors.name)}
                                value={popForm.data.name}
                                onChange={(event) => popForm.setData('name', event.target.value)}
                            />
                            {fieldError('pop-edit-name', popForm.errors.name)}
                        </label>
                        <label>
                            <span className="field-label">{t('Code')}</span>
                            <input
                                id="pop-edit-code"
                                className="field uppercase"
                                {...fieldA11y('pop-edit-code', popForm.errors.code)}
                                value={popForm.data.code}
                                onChange={(event) => popForm.setData('code', event.target.value)}
                            />
                            {fieldError('pop-edit-code', popForm.errors.code)}
                        </label>
                        <label>
                            <span className="field-label">{t('Address')}</span>
                            <input
                                id="pop-edit-address"
                                className="field"
                                {...fieldA11y('pop-edit-address', popForm.errors.address)}
                                value={popForm.data.address}
                                onChange={(event) => popForm.setData('address', event.target.value)}
                            />
                            {fieldError('pop-edit-address', popForm.errors.address)}
                        </label>
                        <label>
                            <span className="field-label">{t('Status')}</span>
                            <ResponsiveSelect
                                id="pop-edit-status"
                                className="field"
                                {...fieldA11y('pop-edit-status', popForm.errors.status)}
                                value={popForm.data.status}
                                onChange={(event) => popForm.setData('status', event.target.value as Status)}
                            >
                                {statuses.map((option) => (
                                    <option key={option} value={option}>
                                        {t(option.replace('_', ' '))}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {fieldError('pop-edit-status', popForm.errors.status)}
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={popForm.processing}>
                            {t('Save changes')}
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <section className="card overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                        <RouterIcon size={17} className="text-brand" />
                        <h2 className="section-title">{t('Routers')}</h2>
                    </div>
                    <div className="divide-y divide-line">
                        {pop.routers.map((router) => (
                            <Link
                                key={router.public_id}
                                href={'/operations/routers/' + router.public_id}
                                className="flex items-center justify-between gap-4 px-5 py-4 hover:bg-sand/30"
                            >
                                <div>
                                    <p className="text-sm font-semibold">{router.name}</p>
                                    <p className="mt-1 text-xs text-muted">{router.host}</p>
                                </div>
                                <StatusBadge status={router.status} />
                            </Link>
                        ))}
                        {pop.routers.length === 0 && (
                            <p className="px-5 py-10 text-center text-sm text-muted">{t('No routers assigned.')}</p>
                        )}
                    </div>
                </section>

                <section className="card overflow-hidden">
                    <div className="flex items-center gap-2 border-b border-line px-5 py-4">
                        <Network size={17} className="text-brand" />
                        <h2 className="section-title">{t('Upstream links')}</h2>
                    </div>
                    {canManage && (
                        <form onSubmit={submitLink} className="space-y-4 border-b border-line bg-sand/30 p-5">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">{t('Provider')}</span>
                                    <input
                                        id="upstream-provider"
                                        className="field"
                                        {...fieldA11y('upstream-provider', linkForm.errors.provider_name)}
                                        value={linkForm.data.provider_name}
                                        onChange={(event) => linkForm.setData('provider_name', event.target.value)}
                                        placeholder={t('Transit provider')}
                                    />
                                    {fieldError('upstream-provider', linkForm.errors.provider_name)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Capacity (Mbps)')}</span>
                                    <input
                                        id="upstream-capacity"
                                        type="number"
                                        min="0"
                                        className="field"
                                        {...fieldA11y('upstream-capacity', linkForm.errors.capacity_mbps)}
                                        value={linkForm.data.capacity_mbps}
                                        onChange={(event) => linkForm.setData('capacity_mbps', event.target.value)}
                                        placeholder="1000"
                                    />
                                    {fieldError('upstream-capacity', linkForm.errors.capacity_mbps)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Monthly cost (minor units)')}</span>
                                    <input
                                        id="upstream-monthly-cost"
                                        type="number"
                                        min="0"
                                        className="field"
                                        {...fieldA11y('upstream-monthly-cost', linkForm.errors.monthly_cost_amount)}
                                        value={linkForm.data.monthly_cost_amount}
                                        onChange={(event) =>
                                            linkForm.setData('monthly_cost_amount', event.target.value)
                                        }
                                        placeholder="125000"
                                    />
                                    {fieldError('upstream-monthly-cost', linkForm.errors.monthly_cost_amount)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Currency')}</span>
                                    <CurrencyCombobox
                                        id="upstream-currency"
                                        className="field uppercase"
                                        {...fieldA11y('upstream-currency', linkForm.errors.currency)}
                                        value={linkForm.data.currency}
                                        currencies={currencies}
                                        onChange={(value) => linkForm.setData('currency', value)}
                                    />
                                    {fieldError('upstream-currency', linkForm.errors.currency)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Contract starts')}</span>
                                    <input
                                        id="upstream-contract-start"
                                        type="date"
                                        className="field"
                                        {...fieldA11y('upstream-contract-start', linkForm.errors.contract_start)}
                                        value={linkForm.data.contract_start}
                                        onChange={(event) => linkForm.setData('contract_start', event.target.value)}
                                    />
                                    {fieldError('upstream-contract-start', linkForm.errors.contract_start)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Contract ends')}</span>
                                    <input
                                        id="upstream-contract-end"
                                        type="date"
                                        className="field"
                                        {...fieldA11y('upstream-contract-end', linkForm.errors.contract_end)}
                                        value={linkForm.data.contract_end}
                                        onChange={(event) => linkForm.setData('contract_end', event.target.value)}
                                    />
                                    {fieldError('upstream-contract-end', linkForm.errors.contract_end)}
                                </label>
                            </div>
                            <label>
                                <span className="field-label">{t('Notes')}</span>
                                <textarea
                                    id="upstream-notes"
                                    className="field min-h-20"
                                    {...fieldA11y('upstream-notes', linkForm.errors.notes)}
                                    value={linkForm.data.notes}
                                    onChange={(event) => linkForm.setData('notes', event.target.value)}
                                    placeholder={t('Primary transit')}
                                />
                                {fieldError('upstream-notes', linkForm.errors.notes)}
                            </label>
                            <div className="flex justify-end">
                                <button type="submit" className="button-primary" disabled={linkForm.processing}>
                                    <Plus size={16} /> {t('Record link')}
                                </button>
                            </div>
                        </form>
                    )}
                    <div className="divide-y divide-line">
                        {pop.upstream_links.map((link) => (
                            <div key={link.id} className="px-5 py-4">
                                {editingLinkId === link.id ? (
                                    <div className="grid gap-3 rounded-lg bg-sand/50 p-4 md:grid-cols-2">
                                        <label>
                                            <span className="field-label">{t('Provider')}</span>
                                            <input
                                                id="upstream-edit-provider"
                                                className="field"
                                                {...fieldA11y('upstream-edit-provider', linkEditForm.errors.provider_name)}
                                                value={linkEditForm.data.provider_name}
                                                onChange={(event) =>
                                                    linkEditForm.setData('provider_name', event.target.value)
                                                }
                                                required
                                            />
                                            {fieldError('upstream-edit-provider', linkEditForm.errors.provider_name)}
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Capacity (Mbps)')}</span>
                                            <input
                                                id="upstream-edit-capacity"
                                                type="number"
                                                min="0"
                                                className="field"
                                                {...fieldA11y('upstream-edit-capacity', linkEditForm.errors.capacity_mbps)}
                                                value={linkEditForm.data.capacity_mbps}
                                                onChange={(event) =>
                                                    linkEditForm.setData('capacity_mbps', event.target.value)
                                                }
                                            />
                                            {fieldError('upstream-edit-capacity', linkEditForm.errors.capacity_mbps)}
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Monthly cost (minor units)')}</span>
                                            <input
                                                id="upstream-edit-monthly-cost"
                                                type="number"
                                                min="0"
                                                className="field"
                                                {...fieldA11y('upstream-edit-monthly-cost', linkEditForm.errors.monthly_cost_amount)}
                                                value={linkEditForm.data.monthly_cost_amount}
                                                onChange={(event) =>
                                                    linkEditForm.setData('monthly_cost_amount', event.target.value)
                                                }
                                                required
                                            />
                                            {fieldError('upstream-edit-monthly-cost', linkEditForm.errors.monthly_cost_amount)}
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Currency')}</span>
                                            <CurrencyCombobox
                                                id="upstream-edit-currency"
                                                className="field uppercase"
                                                {...fieldA11y('upstream-edit-currency', linkEditForm.errors.currency)}
                                                value={linkEditForm.data.currency}
                                                currencies={currencies}
                                                onChange={(value) => linkEditForm.setData('currency', value)}
                                            />
                                            {fieldError('upstream-edit-currency', linkEditForm.errors.currency)}
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Contract starts')}</span>
                                            <input
                                                id="upstream-edit-contract-start"
                                                type="date"
                                                className="field"
                                                {...fieldA11y('upstream-edit-contract-start', linkEditForm.errors.contract_start)}
                                                value={linkEditForm.data.contract_start}
                                                onChange={(event) =>
                                                    linkEditForm.setData('contract_start', event.target.value)
                                                }
                                                required
                                            />
                                            {fieldError('upstream-edit-contract-start', linkEditForm.errors.contract_start)}
                                        </label>
                                        <label>
                                            <span className="field-label">{t('Contract ends')}</span>
                                            <input
                                                id="upstream-edit-contract-end"
                                                type="date"
                                                className="field"
                                                {...fieldA11y('upstream-edit-contract-end', linkEditForm.errors.contract_end)}
                                                value={linkEditForm.data.contract_end}
                                                onChange={(event) =>
                                                    linkEditForm.setData('contract_end', event.target.value)
                                                }
                                            />
                                            {fieldError('upstream-edit-contract-end', linkEditForm.errors.contract_end)}
                                        </label>
                                        <label className="md:col-span-2">
                                            <span className="field-label">{t('Notes')}</span>
                                            <textarea
                                                id="upstream-edit-notes"
                                                className="field min-h-20"
                                                {...fieldA11y('upstream-edit-notes', linkEditForm.errors.notes)}
                                                value={linkEditForm.data.notes}
                                                onChange={(event) => linkEditForm.setData('notes', event.target.value)}
                                            />
                                            {fieldError('upstream-edit-notes', linkEditForm.errors.notes)}
                                        </label>
                                        <div className="flex gap-2 md:col-span-2">
                                            <button
                                                type="button"
                                                className="button-primary"
                                                disabled={linkEditForm.processing}
                                                onClick={() => saveLink(link)}
                                            >
                                                <Save size={15} /> {t('Save changes')}
                                            </button>
                                            <button
                                                type="button"
                                                className="button-quiet"
                                                disabled={linkEditForm.processing}
                                                onClick={cancelLinkEdit}
                                            >
                                                <X size={15} /> {t('Cancel')}
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <>
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-sm font-semibold">{link.provider_name}</p>
                                                <p className="mt-1 text-xs text-muted">
                                                    {link.capacity_mbps
                                                        ? link.capacity_mbps.toLocaleString() + ' Mbps'
                                                        : t('Capacity not recorded')}{' '}
                                                    · {formatDate(link.contract_start)}
                                                </p>
                                            </div>
                                            <div className="text-end">
                                                <p className="text-sm font-semibold">
                                                    {formatMoney(link.monthly_cost_amount, link.currency)}
                                                </p>
                                                <span className="block text-xs font-normal text-muted">{t('monthly')}</span>
                                                {canManage && (
                                                    <button
                                                        type="button"
                                                        className="mt-1 text-xs font-semibold text-brand hover:underline"
                                                        onClick={() => startLinkEdit(link)}
                                                    >
                                                        <Edit3 size={12} className="me-1 inline" /> {t('Edit')}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        {link.contract_end && (
                                            <p className="mt-2 text-xs text-muted">
                                                {t('Contract ends')} {formatDate(link.contract_end)}
                                            </p>
                                        )}
                                        {link.notes && <p className="mt-2 text-sm text-muted">{link.notes}</p>}
                                    </>
                                )}
                            </div>
                        ))}
                        {pop.upstream_links.length === 0 && (
                            <p className="px-5 py-10 text-center text-sm text-muted">{t('No upstream links recorded.')}</p>
                        )}
                    </div>
                </section>
            </div>

            <div className="mt-6 flex items-center gap-2 text-xs text-muted">
                <ExternalLink size={14} /> {t('Provider contracts are inventory records; billing settlement remains in the finance workflow.')}
            </div>
        </AppLayout>
    );
}
