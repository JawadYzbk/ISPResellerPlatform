import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2, Edit3, Map, Save, X } from 'lucide-react';
import { useState } from 'react';

import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';

type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
    phone: string | null;
    is_default: boolean;
};

type Zone = { id: number; parent_id: number | null; parent_name: string | null; name: string; code: string };
type Props = { branches: Branch[]; zones: Zone[]; tenant: { name: string; slug: string } | null };
type BranchForm = { name: string; code: string; address: string; phone: string; is_default: boolean };
type ZoneForm = { name: string; code: string; parent_id: string };

const emptyBranch: BranchForm = { name: '', code: '', address: '', phone: '', is_default: false };
const emptyZone: ZoneForm = { name: '', code: '', parent_id: '' };

export default function Locations({ branches, zones, tenant }: Props) {
    const [editingBranchId, setEditingBranchId] = useState<number | null>(null);
    const [editingZoneId, setEditingZoneId] = useState<number | null>(null);
    const branchForm = useForm<BranchForm>(emptyBranch);
    const zoneForm = useForm<ZoneForm>(emptyZone);

    const resetBranch = () => {
        setEditingBranchId(null);
        branchForm.setData(emptyBranch);
        branchForm.clearErrors();
    };

    const resetZone = () => {
        setEditingZoneId(null);
        zoneForm.setData(emptyZone);
        zoneForm.clearErrors();
    };

    const editBranch = (branch: Branch) => {
        setEditingBranchId(branch.id);
        branchForm.setData({
            name: branch.name,
            code: branch.code,
            address: branch.address ?? '',
            phone: branch.phone ?? '',
            is_default: branch.is_default,
        });
        branchForm.clearErrors();
    };

    const editZone = (zone: Zone) => {
        setEditingZoneId(zone.id);
        zoneForm.setData({ name: zone.name, code: zone.code, parent_id: zone.parent_id?.toString() ?? '' });
        zoneForm.clearErrors();
    };

    const submitBranch = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetBranch };
        if (editingBranchId) {
            branchForm.patch(`/settings/locations/branches/${editingBranchId}`, options);
        } else {
            branchForm.post('/settings/locations/branches', options);
        }
    };

    const submitZone = (event: React.FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetZone };
        if (editingZoneId) {
            zoneForm.patch(`/settings/locations/zones/${editingZoneId}`, options);
        } else {
            zoneForm.post('/settings/locations/zones', options);
        }
    };

    return (
        <AppLayout>
            <Head title="Branches and service zones" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to workspace settings
            </Link>
            <div className="max-w-5xl">
                <p className="eyebrow">Workspace setup · {tenant?.slug}</p>
                <h1 className="page-title">Branches and service zones</h1>
                <p className="page-subtitle">
                    Configure the operating locations used for document sequences, customer coverage, and dispatch.
                </p>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Link href="/settings/general" className="button-secondary">
                        General
                    </Link>
                    <Link href="/settings/readiness" className="button-secondary">
                        Pilot readiness
                    </Link>
                    <Link href="/settings/users" className="button-secondary">
                        Users and invitations
                    </Link>
                </div>

                <div className="mt-8 grid gap-6 xl:grid-cols-2">
                    <section className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex items-center gap-2">
                                    <Building2 size={18} className="text-brand" />
                                    <div>
                                        <h2 className="section-title">Branches</h2>
                                        <p className="mt-1 text-sm text-muted">
                                            Keep one default branch for numbering and operations.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form onSubmit={submitBranch} className="space-y-4 border-b border-line bg-sand/40 p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold">{editingBranchId ? 'Edit branch' : 'Add branch'}</h3>
                                {editingBranchId && (
                                    <button type="button" className="button-quiet" onClick={resetBranch}>
                                        <X size={15} /> Cancel
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">Name</span>
                                    <input
                                        className="field"
                                        value={branchForm.data.name}
                                        onChange={(event) => branchForm.setData('name', event.target.value)}
                                        placeholder="Main office"
                                    />
                                    {branchForm.errors.name && <p className="field-error">{branchForm.errors.name}</p>}
                                </label>
                                <label>
                                    <span className="field-label">Code</span>
                                    <input
                                        className="field uppercase"
                                        value={branchForm.data.code}
                                        onChange={(event) =>
                                            branchForm.setData('code', event.target.value.toUpperCase())
                                        }
                                        placeholder="HQ"
                                    />
                                    {branchForm.errors.code && <p className="field-error">{branchForm.errors.code}</p>}
                                </label>
                                <label>
                                    <span className="field-label">Address</span>
                                    <input
                                        className="field"
                                        value={branchForm.data.address}
                                        onChange={(event) => branchForm.setData('address', event.target.value)}
                                        placeholder="12 Cedar Street"
                                    />
                                    {branchForm.errors.address && (
                                        <p className="field-error">{branchForm.errors.address}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">Phone</span>
                                    <input
                                        className="field"
                                        value={branchForm.data.phone}
                                        onChange={(event) => branchForm.setData('phone', event.target.value)}
                                        placeholder="+961 1 555 010"
                                    />
                                    {branchForm.errors.phone && (
                                        <p className="field-error">{branchForm.errors.phone}</p>
                                    )}
                                </label>
                            </div>
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    checked={branchForm.data.is_default}
                                    onChange={(event) => branchForm.setData('is_default', event.target.checked)}
                                />
                                Use as the default branch
                            </label>
                            {branchForm.errors.is_default && (
                                <p className="field-error">{branchForm.errors.is_default}</p>
                            )}
                            <button className="button-primary" disabled={branchForm.processing}>
                                <Save size={16} /> {editingBranchId ? 'Save branch' : 'Create branch'}
                            </button>
                        </form>
                        <div className="divide-y divide-line">
                            {branches.map((branch) => (
                                <article key={branch.id} className="flex items-start justify-between gap-4 p-5">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold">{branch.name}</h3>
                                            <span className="badge bg-sand text-muted">{branch.code}</span>
                                            {branch.is_default && (
                                                <span className="badge bg-brand-soft text-brand">Default</span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-sm text-muted">
                                            {branch.address || 'No address'}
                                            {branch.phone ? ` · ${branch.phone}` : ''}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="button-secondary shrink-0"
                                        onClick={() => editBranch(branch)}
                                    >
                                        <Edit3 size={15} /> Edit
                                    </button>
                                </article>
                            ))}
                            {branches.length === 0 && (
                                <p className="p-6 text-sm text-muted">
                                    No branches yet. Create the first one to establish the default operating location.
                                </p>
                            )}
                        </div>
                    </section>

                    <section className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <Map size={18} className="text-brand" />
                                <div>
                                    <h2 className="section-title">Service zones</h2>
                                    <p className="mt-1 text-sm text-muted">
                                        Organize customer coverage and zone-based operations.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <form onSubmit={submitZone} className="space-y-4 border-b border-line bg-sand/40 p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold">
                                    {editingZoneId ? 'Edit service zone' : 'Add service zone'}
                                </h3>
                                {editingZoneId && (
                                    <button type="button" className="button-quiet" onClick={resetZone}>
                                        <X size={15} /> Cancel
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">Name</span>
                                    <input
                                        className="field"
                                        value={zoneForm.data.name}
                                        onChange={(event) => zoneForm.setData('name', event.target.value)}
                                        placeholder="North district"
                                    />
                                    {zoneForm.errors.name && <p className="field-error">{zoneForm.errors.name}</p>}
                                </label>
                                <label>
                                    <span className="field-label">Code</span>
                                    <input
                                        className="field uppercase"
                                        value={zoneForm.data.code}
                                        onChange={(event) => zoneForm.setData('code', event.target.value.toUpperCase())}
                                        placeholder="NORTH"
                                    />
                                    {zoneForm.errors.code && <p className="field-error">{zoneForm.errors.code}</p>}
                                </label>
                            </div>
                            <label>
                                <span className="field-label">Parent zone (optional)</span>
                                <ResponsiveSelect
                                    value={zoneForm.data.parent_id}
                                    onChange={(event) => zoneForm.setData('parent_id', event.target.value)}
                                >
                                    <option value="">Top-level zone</option>
                                    {zones
                                        .filter((zone) => zone.id !== editingZoneId)
                                        .map((zone) => (
                                            <option key={zone.id} value={zone.id}>
                                                {zone.name} · {zone.code}
                                            </option>
                                        ))}
                                </ResponsiveSelect>
                                {zoneForm.errors.parent_id && (
                                    <p className="field-error">{zoneForm.errors.parent_id}</p>
                                )}
                            </label>
                            <button className="button-primary" disabled={zoneForm.processing}>
                                <Save size={16} /> {editingZoneId ? 'Save zone' : 'Create zone'}
                            </button>
                        </form>
                        <div className="divide-y divide-line">
                            {zones.map((zone) => (
                                <article key={zone.id} className="flex items-start justify-between gap-4 p-5">
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold">{zone.name}</h3>
                                            <span className="badge bg-sand text-muted">{zone.code}</span>
                                        </div>
                                        <p className="mt-2 text-sm text-muted">
                                            {zone.parent_name ? `Under ${zone.parent_name}` : 'Top-level zone'}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="button-secondary shrink-0"
                                        onClick={() => editZone(zone)}
                                    >
                                        <Edit3 size={15} /> Edit
                                    </button>
                                </article>
                            ))}
                            {zones.length === 0 && (
                                <p className="p-6 text-sm text-muted">
                                    No service zones yet. Add at least one before importing or onboarding customers.
                                </p>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
