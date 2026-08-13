import ConfirmDialog from '@/components/ui/confirm-dialog';
import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Building2, Edit3, Save, ShieldCheck, Users, Wifi } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

type TenantRow = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended' | 'archived' | string;
    locale: string;
    timezone: string;
    base_currency: string;
    collection_currency: string;
    created_at: string | null;
    users_count: number;
    customers_count: number;
    services_count: number;
};

type Props = PageProps & { tenants: TenantRow[]; currencies: CurrencyOption[] };
type NewTenant = {
    name: string;
    slug: string;
    locale: string;
    timezone: string;
    base_currency: string;
    collection_currency: string;
    owner_name: string;
    owner_email: string;
    owner_password: string;
    owner_password_confirmation: string;
};

const statusOptions = ['active', 'suspended', 'archived'];

export default function TenantIndex({ tenants, currencies }: Props) {
    const page = usePage<Props>();
    const t = createTranslator(page.props.app.locale);
    const createForm = useForm<NewTenant>({
        name: '',
        slug: '',
        locale: 'en',
        timezone: 'Asia/Beirut',
        base_currency: 'USD',
        collection_currency: 'LBP',
        owner_name: '',
        owner_email: '',
        owner_password: '',
        owner_password_confirmation: '',
    });
    const editForm = useForm({ name: '', status: 'active' });
    const [editingId, setEditingId] = useState<string | null>(null);

    const editTenant = (tenant: TenantRow) => {
        setEditingId(tenant.id);
        editForm.setData({ name: tenant.name, status: tenant.status });
        editForm.clearErrors();
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const saveTenant = (tenant: TenantRow) => {
        editForm.patch(`/admin/tenants/${tenant.id}`, { onSuccess: cancelEdit });
    };

    return (
        <AppLayout>
            <Head title={t('Tenant administration')} />
            <div>
                <p className="eyebrow">{t('Platform administration')}</p>
                <h1 className="page-title">{t('Tenant workspaces')}</h1>
                <p className="page-subtitle">
                    {t('Provision isolated ISP workspaces, create their first owner, and control lifecycle status from one audited control plane.')}
                </p>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)]">
                <section className="card p-6">
                    <div className="flex items-start gap-3">
                        <span className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                            <Building2 size={18} />
                        </span>
                        <div>
                            <h2 className="section-title">{t('Create workspace')}</h2>
                            <p className="mt-1 text-sm text-muted">
                                {t('Defaults, currencies, core sequences, and a tenant owner are created together.')}
                            </p>
                        </div>
                    </div>
                    <form
                        className="mt-6 space-y-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            createForm.post('/admin/tenants');
                        }}
                    >
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="sm:col-span-2">
                                <span className="field-label">{t('Workspace name')}</span>
                                <input
                                    className="field"
                                    value={createForm.data.name}
                                    onChange={(event) => createForm.setData('name', event.target.value)}
                                    placeholder="Northline Broadband"
                                />
                                {createForm.errors.name && <p className="field-error">{t(createForm.errors.name)}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('Tenant slug')}</span>
                                <input
                                    className="field"
                                    value={createForm.data.slug}
                                    onChange={(event) => createForm.setData('slug', event.target.value)}
                                    placeholder="northline"
                                />
                                {createForm.errors.slug && <p className="field-error">{t(createForm.errors.slug)}</p>}
                            </label>
                            <label>
                                <span className="field-label">{t('Locale')}</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={createForm.data.locale}
                                    onChange={(event) => createForm.setData('locale', event.target.value)}
                                >
                                    <option value="en">{t('English')}</option>
                                    <option value="ar">{t('Arabic')}</option>
                                    <option value="fr">{t('French')}</option>
                                </ResponsiveSelect>
                                {createForm.errors.locale && <p className="field-error">{t(createForm.errors.locale)}</p>}
                            </label>
                            <label className="sm:col-span-2">
                                <span className="field-label">{t('Timezone')}</span>
                                <input
                                    className="field"
                                    value={createForm.data.timezone}
                                    onChange={(event) => createForm.setData('timezone', event.target.value)}
                                    placeholder="Asia/Beirut"
                                />
                                {createForm.errors.timezone && (
                                    <p className="field-error">{t(createForm.errors.timezone)}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Base currency')}</span>
                                <CurrencyCombobox
                                    id="base_currency"
                                    aria-label={t('Base currency')}
                                    value={createForm.data.base_currency}
                                    currencies={currencies}
                                    onChange={(value) => createForm.setData('base_currency', value)}
                                />
                                {createForm.errors.base_currency && (
                                    <p className="field-error">{t(createForm.errors.base_currency)}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Collection currency')}</span>
                                <CurrencyCombobox
                                    id="collection_currency"
                                    aria-label={t('Collection currency')}
                                    value={createForm.data.collection_currency}
                                    currencies={currencies}
                                    onChange={(value) => createForm.setData('collection_currency', value)}
                                />
                                {createForm.errors.collection_currency && (
                                    <p className="field-error">{t(createForm.errors.collection_currency)}</p>
                                )}
                            </label>
                        </div>

                        <div className="border-t border-line pt-5">
                            <p className="text-sm font-semibold">{t('First tenant owner')}</p>
                            <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">{t('Owner name')}</span>
                                    <input
                                        className="field"
                                        value={createForm.data.owner_name}
                                        onChange={(event) => createForm.setData('owner_name', event.target.value)}
                                        placeholder="Maya Haddad"
                                    />
                                    {createForm.errors.owner_name && (
                                        <p className="field-error">{t(createForm.errors.owner_name)}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">{t('Owner email')}</span>
                                    <input
                                        type="email"
                                        className="field"
                                        value={createForm.data.owner_email}
                                        onChange={(event) => createForm.setData('owner_email', event.target.value)}
                                        placeholder="owner@example.com"
                                    />
                                    {createForm.errors.owner_email && (
                                        <p className="field-error">{t(createForm.errors.owner_email)}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">{t('Temporary password')}</span>
                                    <input
                                        type="password"
                                        className="field"
                                        value={createForm.data.owner_password}
                                        onChange={(event) => createForm.setData('owner_password', event.target.value)}
                                    />
                                    {createForm.errors.owner_password && (
                                        <p className="field-error">{t(createForm.errors.owner_password)}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">{t('Confirm password')}</span>
                                    <input
                                        type="password"
                                        className="field"
                                        value={createForm.data.owner_password_confirmation}
                                        onChange={(event) =>
                                            createForm.setData('owner_password_confirmation', event.target.value)
                                        }
                                    />
                                </label>
                            </div>
                            <p className="mt-3 text-xs leading-5 text-muted">
                                {t('Use a one-time password for local provisioning and rotate it before handoff.')}
                            </p>
                        </div>

                        <button
                            type="submit"
                            className="button-primary w-full justify-center"
                            disabled={createForm.processing}
                        >
                            <ShieldCheck size={16} />
                            {createForm.processing ? t('Creating workspace…') : t('Create workspace')}
                        </button>
                    </form>
                </section>

                <section className="space-y-4">
                    <div className="card flex items-center justify-between gap-4 p-6">
                        <div>
                            <p className="eyebrow">{t('Control plane')}</p>
                            <h2 className="section-title mt-1">
                                {tenants.length} {t('workspace(s)')}
                            </h2>
                        </div>
                        <Building2 className="text-brand" size={22} />
                    </div>
                    {tenants.map((tenant) => (
                        <article key={tenant.id} className="card p-6">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    {editingId === tenant.id ? (
                                        <input
                                            className="field"
                                            value={editForm.data.name}
                                            onChange={(event) => editForm.setData('name', event.target.value)}
                                        />
                                    ) : (
                                        <h2 className="section-title truncate">{tenant.name}</h2>
                                    )}
                                    <p className="mt-1 text-sm text-muted">{tenant.slug}</p>
                                </div>
                                <span
                                    className={`inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${tenant.status === 'active' ? 'bg-emerald-50 text-emerald-700' : tenant.status === 'suspended' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600'}`}
                                >
                                    {enumLabel(tenant.status, t)}
                                </span>
                            </div>

                            <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                <div className="rounded-xl bg-sand/60 p-3">
                                    <Users size={15} className="text-brand" />
                                    <p className="mt-2 text-lg font-semibold">{tenant.users_count}</p>
                                    <p className="text-xs text-muted">{t('Operators')}</p>
                                </div>
                                <div className="rounded-xl bg-sand/60 p-3">
                                    <Building2 size={15} className="text-brand" />
                                    <p className="mt-2 text-lg font-semibold">{tenant.customers_count}</p>
                                    <p className="text-xs text-muted">{t('Customers')}</p>
                                </div>
                                <div className="rounded-xl bg-sand/60 p-3">
                                    <Wifi size={15} className="text-brand" />
                                    <p className="mt-2 text-lg font-semibold">{tenant.services_count}</p>
                                    <p className="text-xs text-muted">{t('Services')}</p>
                                </div>
                            </div>

                            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted">
                                <span>
                                    {tenant.base_currency} {t('base')} · {tenant.collection_currency} {t('collection')}
                                </span>
                                <span>{tenant.timezone}</span>
                                <span>{t('Created')} {formatDate(tenant.created_at)}</span>
                            </div>

                            {editingId === tenant.id && (
                                <div className="mt-5 max-w-xs">
                                    <span className="field-label">{t('Lifecycle status')}</span>
                                    <ResponsiveSelect
                                        className="field"
                                        value={editForm.data.status}
                                        onChange={(event) => editForm.setData('status', event.target.value)}
                                    >
                                        {statusOptions.map((status) => (
                                            <option key={status} value={status}>
                                                {t(status)}
                                            </option>
                                        ))}
                                    </ResponsiveSelect>
                                    {editForm.errors.status && <p className="field-error">{t(editForm.errors.status)}</p>}
                                </div>
                            )}

                            <div className="mt-5 flex justify-end gap-2">
                                {editingId === tenant.id ? (
                                    <>
                                        <button type="button" className="button-quiet" onClick={cancelEdit}>
                                            {t('Cancel')}
                                        </button>
                                        <ConfirmDialog
                                            title={t('Save tenant changes?')}
                                            description={t(
                                                'The workspace name and lifecycle status will be updated and recorded in the platform audit log.',
                                            )}
                                            confirmLabel={t('Save changes')}
                                            onConfirm={() => saveTenant(tenant)}
                                        >
                                            <button
                                                type="button"
                                                className="button-primary"
                                                disabled={editForm.processing}
                                            >
                                                <Save size={15} /> {t('Save changes')}
                                            </button>
                                        </ConfirmDialog>
                                    </>
                                ) : (
                                    <button
                                        type="button"
                                        className="button-secondary"
                                        onClick={() => editTenant(tenant)}
                                    >
                                        <Edit3 size={15} /> {t('Edit workspace')}
                                    </button>
                                )}
                            </div>
                        </article>
                    ))}
                </section>
            </div>
        </AppLayout>
    );
}
