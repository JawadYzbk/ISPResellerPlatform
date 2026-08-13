import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Building2, Edit3, Map, Save, X } from 'lucide-react';
import { useState } from 'react';

import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

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
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [editingBranchId, setEditingBranchId] = useState<number | null>(null);
    const [editingZoneId, setEditingZoneId] = useState<number | null>(null);
    const branchForm = useForm<BranchForm>(emptyBranch);
    const zoneForm = useForm<ZoneForm>(emptyZone);
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
            <Head title={t('locations.title')} />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to workspace settings')}
            </Link>
            <div className="max-w-5xl">
                <p className="eyebrow">{t('Workspace setup')} · {tenant?.slug}</p>
                <h1 className="page-title">{t('locations.title')}</h1>
                <p className="page-subtitle">{t('locations.subtitle')}</p>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Link href="/settings/general" className="button-secondary">
                        {t('General')}
                    </Link>
                    <Link href="/settings/readiness" className="button-secondary">
                        {t('Pilot readiness')}
                    </Link>
                    <Link href="/settings/users" className="button-secondary">
                        {t('Users and invitations')}
                    </Link>
                </div>

                <div className="mt-8 grid gap-6 xl:grid-cols-2">
                    <section className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex items-center gap-2">
                                    <Building2 size={18} className="text-brand" />
                                    <div>
                                        <h2 className="section-title">{t('locations.branches')}</h2>
                                        <p className="mt-1 text-sm text-muted">{t('locations.branches_description')}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <form onSubmit={submitBranch} className="space-y-4 border-b border-line bg-sand/40 p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold">
                                    {editingBranchId ? t('locations.edit_branch') : t('locations.add_branch')}
                                </h3>
                                {editingBranchId && (
                                    <button type="button" className="button-quiet" onClick={resetBranch}>
                                        <X size={15} /> {t('Cancel')}
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">{t('Name')}</span>
                                    <input
                                        id="branch-name"
                                        className="field"
                                        {...fieldA11y('branch-name', branchForm.errors.name)}
                                        value={branchForm.data.name}
                                        onChange={(event) => branchForm.setData('name', event.target.value)}
                                        placeholder={t('Main office')}
                                    />
                                    {fieldError('branch-name', branchForm.errors.name)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Code')}</span>
                                    <input
                                        id="branch-code"
                                        className="field uppercase"
                                        {...fieldA11y('branch-code', branchForm.errors.code)}
                                        value={branchForm.data.code}
                                        onChange={(event) =>
                                            branchForm.setData('code', event.target.value.toUpperCase())
                                        }
                                        placeholder="HQ"
                                    />
                                    {fieldError('branch-code', branchForm.errors.code)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Address')}</span>
                                    <input
                                        id="branch-address"
                                        className="field"
                                        {...fieldA11y('branch-address', branchForm.errors.address)}
                                        value={branchForm.data.address}
                                        onChange={(event) => branchForm.setData('address', event.target.value)}
                                        placeholder="12 Cedar Street"
                                    />
                                    {fieldError('branch-address', branchForm.errors.address)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Phone')}</span>
                                    <input
                                        id="branch-phone"
                                        className="field"
                                        {...fieldA11y('branch-phone', branchForm.errors.phone)}
                                        value={branchForm.data.phone}
                                        onChange={(event) => branchForm.setData('phone', event.target.value)}
                                        placeholder="+961 1 555 010"
                                    />
                                    {fieldError('branch-phone', branchForm.errors.phone)}
                                </label>
                            </div>
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <input
                                    id="branch-is-default"
                                    type="checkbox"
                                    {...fieldA11y('branch-is-default', branchForm.errors.is_default)}
                                    checked={branchForm.data.is_default}
                                    onChange={(event) => branchForm.setData('is_default', event.target.checked)}
                                />
                                {t('locations.use_default_branch')}
                            </label>
                            {fieldError('branch-is-default', branchForm.errors.is_default)}
                            <button type="submit" className="button-primary" disabled={branchForm.processing}>
                                <Save size={16} />{' '}
                                {editingBranchId ? t('locations.save_branch') : t('locations.create_branch')}
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
                                                <span className="badge bg-brand-soft text-brand">{t('Default')}</span>
                                            )}
                                        </div>
                                        <p className="mt-2 text-sm text-muted">
                                            {branch.address || t('No address')}
                                            {branch.phone ? ` · ${branch.phone}` : ''}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="button-secondary shrink-0"
                                        onClick={() => editBranch(branch)}
                                    >
                                        <Edit3 size={15} /> {t('Edit')}
                                    </button>
                                </article>
                            ))}
                            {branches.length === 0 && (
                                <p className="p-6 text-sm text-muted">{t('locations.no_branches')}</p>
                            )}
                        </div>
                    </section>

                    <section className="card overflow-hidden">
                        <div className="border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <Map size={18} className="text-brand" />
                                <div>
                                    <h2 className="section-title">{t('locations.service_zones')}</h2>
                                    <p className="mt-1 text-sm text-muted">{t('locations.zones_description')}</p>
                                </div>
                            </div>
                        </div>
                        <form onSubmit={submitZone} className="space-y-4 border-b border-line bg-sand/40 p-6">
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="font-semibold">
                                    {editingZoneId ? t('locations.edit_zone') : t('locations.add_zone')}
                                </h3>
                                {editingZoneId && (
                                    <button type="button" className="button-quiet" onClick={resetZone}>
                                        <X size={15} /> {t('Cancel')}
                                    </button>
                                )}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label>
                                    <span className="field-label">{t('Name')}</span>
                                    <input
                                        id="zone-name"
                                        className="field"
                                        {...fieldA11y('zone-name', zoneForm.errors.name)}
                                        value={zoneForm.data.name}
                                        onChange={(event) => zoneForm.setData('name', event.target.value)}
                                        placeholder={t('North district')}
                                    />
                                    {fieldError('zone-name', zoneForm.errors.name)}
                                </label>
                                <label>
                                    <span className="field-label">{t('Code')}</span>
                                    <input
                                        id="zone-code"
                                        className="field uppercase"
                                        {...fieldA11y('zone-code', zoneForm.errors.code)}
                                        value={zoneForm.data.code}
                                        onChange={(event) => zoneForm.setData('code', event.target.value.toUpperCase())}
                                        placeholder="NORTH"
                                    />
                                    {fieldError('zone-code', zoneForm.errors.code)}
                                </label>
                            </div>
                            <label>
                                <span className="field-label">{t('locations.parent_zone_optional')}</span>
                                <ResponsiveSelect
                                    id="zone-parent"
                                    {...fieldA11y('zone-parent', zoneForm.errors.parent_id)}
                                    value={zoneForm.data.parent_id}
                                    onChange={(event) => zoneForm.setData('parent_id', event.target.value)}
                                >
                                    <option value="">{t('locations.top_level_zone')}</option>
                                    {zones
                                        .filter((zone) => zone.id !== editingZoneId)
                                        .map((zone) => (
                                            <option key={zone.id} value={zone.id}>
                                                {zone.name} · {zone.code}
                                            </option>
                                        ))}
                                </ResponsiveSelect>
                                {fieldError('zone-parent', zoneForm.errors.parent_id)}
                            </label>
                            <button type="submit" className="button-primary" disabled={zoneForm.processing}>
                                <Save size={16} />{' '}
                                {editingZoneId ? t('locations.save_zone') : t('locations.create_zone')}
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
                                            {zone.parent_name
                                                ? `${t('locations.under')} ${zone.parent_name}`
                                                : t('locations.top_level_zone')}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        className="button-secondary shrink-0"
                                        onClick={() => editZone(zone)}
                                    >
                                        <Edit3 size={15} /> {t('Edit')}
                                    </button>
                                </article>
                            ))}
                            {zones.length === 0 && <p className="p-6 text-sm text-muted">{t('locations.no_zones')}</p>}
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
