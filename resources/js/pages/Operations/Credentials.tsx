import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, KeyRound, Search, Upload } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import { formatDate } from '@/lib/format';
import type { PageProps, Paginator } from '@/types';

type Credential = {
    id: number;
    identifier: string;
    status: 'available' | 'reserved' | 'assigned' | 'expired' | 'revoked';
    expires_at: string | null;
    supplier: { name: string; code: string } | null;
    batch_reference: string | null;
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

type Supplier = { id: number; name: string; code: string };

type Props = PageProps & {
    credentials: Paginator<Credential>;
    filters: { status?: string; search?: string };
    canAssign?: boolean;
    assignableServices?: AssignableService[];
    canImport?: boolean;
    canReveal?: boolean;
    suppliers?: Supplier[];
};

export default function CredentialsPage({
    credentials,
    filters,
    canAssign = false,
    assignableServices = [],
    canImport = false,
    canReveal = false,
    suppliers = [],
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [selectedServices, setSelectedServices] = useState<Record<number, string>>({});
    const [revealedSecrets, setRevealedSecrets] = useState<Record<number, string>>({});
    const [revealError, setRevealError] = useState<string | null>(null);
    const importForm = useForm({
        supplier_id: suppliers[0]?.id.toString() ?? '',
        reference: '',
        expires_at: '',
        file: null as File | null,
    });

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
            setRevealError('The credential could not be revealed. Re-authenticate and try again.');
            return;
        }
        const data = (await response.json()) as { secret: string };
        setRevealedSecrets((current) => ({ ...current, [credentialId]: data.secret }));
    };

    return (
        <AppLayout>
            <Head title="Credentials" />
            <div>
                <p className="eyebrow">Supplier operations</p>
                <h1 className="page-title">Upstream credentials</h1>
                <p className="page-subtitle">
                    Track imported credential inventory and assignments without exposing secrets.
                </p>
            </div>
            {canImport && (
                <form onSubmit={importCredentials} className="card mt-8 space-y-5 p-5">
                    <div>
                        <h2 className="text-lg font-semibold">Import credential batch</h2>
                        <p className="mt-1 text-sm text-muted">
                            CSV columns: <code>identifier,secret</code>. Plaintext secrets are encrypted immediately and
                            never included in the inventory response.
                        </p>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">Supplier</span>
                            <ResponsiveSelect
                                className="field"
                                value={importForm.data.supplier_id}
                                onChange={(event) => importForm.setData('supplier_id', event.target.value)}
                            >
                                <option value="">Select supplier</option>
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
                            <span className="field-label">Batch reference</span>
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
                            <span className="field-label">Expiry (optional)</span>
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
                            <span className="field-label">CSV file</span>
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
                            <Upload size={16} /> Import batch
                        </button>
                    </div>
                </form>
            )}
            {revealError && <p className="mt-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700">{revealError}</p>}
            <form onSubmit={applyFilters} className="card mt-8 flex flex-col gap-4 p-5 sm:flex-row sm:items-end">
                <label className="block sm:min-w-80">
                    <span className="field-label">Search credential inventory</span>
                    <div className="relative">
                        <Search size={17} className="pointer-events-none absolute start-3 top-3 text-muted" />
                        <input
                            className="field ps-10"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Identifier, batch, username"
                        />
                    </div>
                </label>
                <label className="block sm:min-w-48">
                    <span className="field-label">Credential status</span>
                    <ResponsiveSelect
                        className="field"
                        value={status}
                        onChange={(event) => setStatus(event.target.value)}
                    >
                        <option value="">All statuses</option>
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="assigned">Assigned</option>
                        <option value="expired">Expired</option>
                        <option value="revoked">Revoked</option>
                    </ResponsiveSelect>
                </label>
                <button type="submit" className="button-primary">
                    Apply filters
                </button>
            </form>
            <div className="card mt-6 overflow-hidden">
                <div className="flex items-center justify-between border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <KeyRound size={17} className="text-brand" />
                        <p className="text-sm font-semibold">{credentials.total.toLocaleString()} credential(s)</p>
                    </div>
                    <p className="text-xs text-muted">Secrets require a separate audited reveal flow.</p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[1160px] text-start">
                        <thead>
                            <tr className="border-b border-line bg-sand/50 text-xs font-semibold uppercase tracking-wider text-muted">
                                <th className="px-5 py-3.5 text-start">Identifier</th>
                                <th className="px-5 py-3.5 text-start">Supplier / batch</th>
                                <th className="px-5 py-3.5 text-start">Status</th>
                                <th className="px-5 py-3.5 text-start">Expiry</th>
                                <th className="px-5 py-3.5 text-start">Assigned service</th>
                                <th className="px-5 py-3.5 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {credentials.data.map((credential) => (
                                <tr key={credential.id} className="hover:bg-sand/30">
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">{credential.identifier}</p>
                                        <p className="mt-1 text-xs text-muted">Inventory #{credential.id}</p>
                                        {revealedSecrets[credential.id] && (
                                            <p className="mt-2 break-all rounded bg-amber-50 px-2 py-1 font-mono text-xs text-amber-900">
                                                {revealedSecrets[credential.id]}
                                            </p>
                                        )}
                                    </td>
                                    <td className="px-5 py-4">
                                        <p className="text-sm font-semibold">
                                            {credential.supplier?.name ?? 'No supplier'}
                                        </p>
                                        <p className="mt-1 text-xs text-muted">
                                            {credential.batch_reference ?? 'No batch reference'}
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
                                            <span className="text-sm text-muted">Unassigned</span>
                                        )}
                                    </td>
                                    <td className="px-5 py-4 text-end">
                                        {canReveal && !revealedSecrets[credential.id] && (
                                            <button
                                                type="button"
                                                className="mb-2 block ms-auto text-sm font-semibold text-coral hover:underline"
                                                onClick={() => revealCredential(credential.id)}
                                            >
                                                Reveal secret
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
                                                        <option value="">Select service</option>
                                                        {assignableServices.map((service) => (
                                                            <option key={service.public_id} value={service.public_id}>
                                                                {service.username} · {service.customer ?? 'No customer'}
                                                            </option>
                                                        ))}
                                                    </ResponsiveSelect>
                                                    <ConfirmDialog
                                                        title={`Assign ${credential.identifier}?`}
                                                        description="This credential will be assigned to the selected service."
                                                        confirmLabel="Assign credential"
                                                        onConfirm={() => assignCredential(credential)}
                                                    >
                                                        <button
                                                            type="button"
                                                            className="text-sm font-semibold text-brand"
                                                        >
                                                            Assign
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
                                        <p className="mt-3 font-semibold">No credentials match these filters</p>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="flex items-center justify-between border-t border-line px-5 py-4">
                    <p className="text-xs text-muted">
                        Page {credentials.current_page} of {credentials.last_page}
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
