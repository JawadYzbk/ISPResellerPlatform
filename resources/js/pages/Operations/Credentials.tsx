import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, KeyRound, Search, Upload } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatDate } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps, Paginator } from '@/types';
import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';

type Credential = {
    id: number;
    identifier: string;
    status: 'imported' | 'available' | 'reserved' | 'assigned' | 'active' | 'expired' | 'revoked' | 'invalid';
    expires_at: string | null;
    supplier: { name: string; code: string } | null;
    batch_reference: string | null;
    supplier_contract: { id: number; service_type: string; wholesale_currency: string; status: string } | null;
    assigned_service: {
        public_id: string;
        username: string;
        customer_public_id: string | null;
        customer: string | null;
    } | null;
};

type AssignableService = {
    public_id: string;
    username: string;
    customer: string | null;
};

type SupplierContract = { id: number; service_type: string; wholesale_currency: string; status: string };
type Supplier = { id: number; name: string; code: string; contracts: SupplierContract[] };

type Props = PageProps & {
    credentials: Paginator<Credential>;
    filters: { status?: string; search?: string };
    canAssign?: boolean;
    assignableServices?: AssignableService[];
    canImport?: boolean;
    canReveal?: boolean;
    suppliers?: Supplier[];
    currencies?: CurrencyOption[];
};

export default function CredentialsPage({
    credentials,
    filters,
    canAssign = false,
    assignableServices = [],
    canImport = false,
    canReveal = false,
    suppliers = [],
    currencies = [],
}: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});
    const [revealedSecrets, setRevealedSecrets] = useState<Record<number, string>>({});
    const [revealError, setRevealError] = useState<string | null>(null);
    const importForm = useForm({
        supplier_id: suppliers[0]?.id.toString() ?? '',
        supplier_contract_id: '',
        reference: '',
        contract_reference: '',
        unit_cost_amount: '',
        total_cost_amount: '',
        currency: currencies[0]?.code ?? 'USD',
        expires_at: '',
        file: null as File | null,
    });
    const selectedSupplier = suppliers.find((supplier) => supplier.id.toString() === importForm.data.supplier_id);

    const applyFilters = (event: React.FormEvent) => {
        event.preventDefault();
        router.get(
            '/operations/credentials',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    };

    const assignCredential = (credential: Credential) => {
        const servicePublicId = selectedServices[credential.id];
        if (!servicePublicId) {
            return;
        }

        router.post(`/operations/credentials/${credential.id}/assign`, {
            service_public_id: servicePublicId,
        });
    };

    const importCredentials = (event: React.FormEvent) => {
        event.preventDefault();
        importForm.post('/operations/credentials/import', {
            forceFormData: true,
            onSuccess: () => importForm.reset('reference', 'expires_at', 'file'),
        });
    };

    const revealCredential = async (credentialId: number) => {
        setRevealError(null);
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const response = await fetch(`/operations/credentials/${credentialId}/reveal`, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            setRevealError(t('credentials.reveal_error'));
            return;
        }
        const data = (await response.json()) as { secret: string };
        setRevealedSecrets((current) => ({ ...current, [credentialId]: data.secret }));
    };

    return (
        <AppLayout>
            <Head title={t('credentials.title')} />
            <div>
                <p className="eyebrow">{t('credentials.eyebrow')}</p>
                <h1 className="page-title">{t('credentials.title')}</h1>
                <p className="page-subtitle">{t('credentials.subtitle')}</p>
            </div>
            {canImport && (
                <form onSubmit={importCredentials} className="card mt-8 space-y-5 p-5">
                    <div>
                        <h2 className="text-lg font-semibold">{t('credentials.import_batch')}</h2>
                        <p className="mt-1 text-sm text-muted">
                            {t('credentials.csv_columns')} <code>identifier,secret</code>. {t('credentials.csv_note')}
                        </p>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">{t('Supplier')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={importForm.data.supplier_id}
                                onChange={(event) => {
                                    importForm.setData('supplier_id', event.target.value);
                                    importForm.setData('supplier_contract_id', '');
                                }}
                            >
                                <option value="">{t('credentials.select_supplier')}</option>
                                {suppliers.map((supplier) => (
                                    <option key={supplier.id} value={supplier.id}>
                                        {supplier.name} ({supplier.code})
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {importForm.errors.supplier_id && (
                                <p className="field-error">{importForm.errors.supplier_id}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.contract_optional')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={importForm.data.supplier_contract_id}
                                onChange={(event) => importForm.setData('supplier_contract_id', event.target.value)}
                                disabled={!selectedSupplier || selectedSupplier.contracts.length === 0}
                            >
                                <option value="">{t('credentials.no_contract')}</option>
                                {selectedSupplier?.contracts.map((contract) => (
                                    <option key={contract.id} value={contract.id}>
                                        {t(contract.service_type)} · {contract.wholesale_currency} · {enumLabel(contract.status, t)}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {importForm.errors.supplier_contract_id && (
                                <p className="field-error">{importForm.errors.supplier_contract_id}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.batch_reference')}</span>
                            <input
                                className="field"
                                value={importForm.data.reference}
                                onChange={(event) => importForm.setData('reference', event.target.value)}
                                placeholder="SUP-2026-08"
                            />
                            {importForm.errors.reference && (
                                <p className="field-error">{importForm.errors.reference}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.expiry_optional')}</span>
                            <input
                                className="field"
                                type="date"
                                value={importForm.data.expires_at}
                                onChange={(event) => importForm.setData('expires_at', event.target.value)}
                            />
                            {importForm.errors.expires_at && (
                                <p className="field-error">{importForm.errors.expires_at}</p>
                            )}
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.contract_reference')}</span>
                            <input
                                className="field"
                                value={importForm.data.contract_reference}
                                onChange={(event) => importForm.setData('contract_reference', event.target.value)}
                                placeholder="CONTRACT-01"
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.unit_cost')}</span>
                            <input
                                className="field"
                                type="number"
                                min="0"
                                value={importForm.data.unit_cost_amount}
                                onChange={(event) => importForm.setData('unit_cost_amount', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.total_cost')}</span>
                            <input
                                className="field"
                                type="number"
                                min="0"
                                value={importForm.data.total_cost_amount}
                                onChange={(event) => importForm.setData('total_cost_amount', event.target.value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.cost_currency')}</span>
                            <CurrencyCombobox
                                className="field"
                                value={importForm.data.currency}
                                currencies={currencies}
                                onChange={(value) => importForm.setData('currency', value)}
                            />
                        </label>
                        <label>
                            <span className="field-label">{t('credentials.csv_file')}</span>
                            <input
                                className="field file:me-3 file:rounded-md file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand"
                                type="file"
                                accept=".csv,.txt"
                                onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)}
                            />
                            {importForm.errors.file && <p className="field-error">{importForm.errors.file}</p>}
                        </label>
                    </div>
                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="button-primary"
                            disabled={
                                importForm.processing ||
                                importForm.data.file === null ||
                                importForm.data.supplier_id === ''
                            }
                        >
                            <Upload size={16} /> {t('credentials.import_batch')}
                        </button>
                    </div>
                </form>
            )}
            {revealError && <p className="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{revealError}</p>}
            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">{t('credentials.search')}</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('credentials.search_placeholder')}
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">{t('credentials.status')}</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">{t('credentials.all_statuses')}</option>
                        <option value="imported">{t('Imported')}</option>
                        <option value="available">{t('Available')}</option>
                        <option value="reserved">{t('Reserved')}</option>
                        <option value="assigned">{t('Assigned')}</option>
                        <option value="active">{t('Active')}</option>
                        <option value="expired">{t('Expired')}</option>
                        <option value="revoked">{t('Revoked')}</option>
                        <option value="invalid">{t('Invalid')}</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    {t('Apply filters')}
                </button>
            </form>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <KeyRound size={17} className="text-brand" />
                        <p className="text-sm font-semibold">
                            {credentials.total.toLocaleString()} {t('credentials.count')}
                        </p>
                    </div>
                    <p className="text-xs text-muted">{t('credentials.secrets_note')}</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1160px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">{t('Identifier')}</th>
                                <th className="px-5 py-3.5 text-start">{t('credentials.supplier_batch')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Status')}</th>
                                <th className="px-5 py-3.5 text-start">{t('Expiry')}</th>
                                <th className="px-5 py-3.5 text-start">{t('credentials.assigned_service')}</th>
                                <th className="px-5 py-3.5 text-end">{t('Action')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {credentials.data.map((credential) => (
                                <tr key={credential.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{credential.identifier}</p>
                                        <p className="mt-1 text-xs text-muted">
                                            {t('credentials.inventory')} #{credential.id}
                                        </p>
                                        {revealedSecrets[credential.id] && (
                                            <p className="mt-2 break-all rounded bg-amber-50 px-2 py-1 font-mono text-xs text-amber-900">
                                                {revealedSecrets[credential.id]}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">
                                            {credential.supplier?.name ?? t('credentials.no_supplier')}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {credential.batch_reference ?? t('credentials.no_batch')}
                                            {credential.supplier_contract && (
                                                <span className="ms-2">
                                                    · {credential.supplier_contract.service_type} ·{' '}
                                                    {credential.supplier_contract.wholesale_currency}
                                                </span>
                                            )}
                                        </p>
                                    </td>
                                    <td className="px-5 py-4">
                                        <StatusBadge status={credential.status} />
                                    </td>
                                    <td className="px-5 py-4 text-sm text-muted">
                                        {formatDate(credential.expires_at)}
                                    </td>
                                    <td className="px-5 py-4">
                                        {credential.assigned_service ? (
                                            <>
                                                {credential.assigned_service.customer_public_id ? (
                                                    <Link
                                                        href={`/customers/${credential.assigned_service.customer_public_id}`}
                                                        className="text-sm font-semibold hover:text-brand"
                                                    >
                                                        {credential.assigned_service.username}
                                                    </Link>
                                                ) : (
                                                    <span className="text-sm font-semibold">
                                                        {credential.assigned_service.username}
                                                    </span>
                                                )}
                                                {credential.assigned_service.customer && (
                                                    <p className="mt-1 text-xs text-muted">
                                                        {credential.assigned_service.customer}
                                                    </p>
                                                )}
                                            </>
                                        ) : (
                                            <span className="text-sm text-muted">{t('Unassigned')}</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canReveal && !revealedSecrets[credential.id] && (
                                            <button
                                                type="button"
                                                className="mb-2 block ms-auto text-sm font-semibold text-coral hover:underline"
                                                onClick={() => revealCredential(credential.id)}
                                            >
                                                {t('credentials.reveal')}
                                            </button>
                                        )}
                                        {canAssign &&
                                            credential.status === 'available' &&
                                            assignableServices.length > 0 && (
                                                <div className="flex items-center justify-end gap-2">
                                                    <ResponsiveSelect
                                                        className="field max-w-56 py-2 text-xs"
                                                        value={selectedServices[credential.id] ?? ''}
                                                        onChange={(event) =>
                                                            setSelectedServices((current) => ({
                                                                ...current,
                                                                [credential.id]: event.target.value,
                                                            }))
                                                        }
                                                    >
                                                        <option value="">{t('credentials.select_service')}</option>
                                                        {assignableServices.map((service) => (
                                                            <option key={service.public_id} value={service.public_id}>
                                                                {service.username} ·{' '}
                                                                {service.customer ?? t('credentials.no_customer')}
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                    <ConfirmDialog
                                                        title={t('credentials.assign_title')}
                                                        description={t('credentials.assign_description')}
                                                        confirmLabel={t('credentials.assign_credential')}
                                                        onConfirm={() => assignCredential(credential)}
                                                    >
                                                        <button
                                                            type="button"
                                                            className="text-sm font-semibold text-brand"
                                                        >
                                                            {t('Assign')}
                                                        </button>
                                                    </ConfirmDialog>
                                                </div>
                                            )}
                                    </td>
                                </tr>
                            ))}
                            {credentials.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16 text-center">
                                        <KeyRound className="mx-auto text-muted" size={28} />
                                        <p className="mt-3 font-semibold">{t('credentials.no_matches')}</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        {t('Page')} {credentials.current_page} {t('of')} {credentials.last_page}
                    </p>
                    <div className="flex items-center gap-1">
                        {credentials.links.map((link, index) => {
                            const isPrevious = index === 0;
                            const isNext = index === credentials.links.length - 1;
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
