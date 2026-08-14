import ResponsiveSelect from '@/components/ui/responsive-select';
import CustomerLocationFields from '@/components/CustomerLocationFields';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Building2, MapPinned, Plus } from 'lucide-react';

import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Building = {
    public_id: string;
    name: string;
    code: string;
    address: string | null;
    latitude: string | null;
    longitude: string | null;
    floors: number | null;
    unit_count: number | null;
    status: Status;
    distribution_boxes_count: number;
    active_services_count: number;
};

type BuildingForm = {
    name: string;
    code: string;
    address: string;
    latitude: string;
    longitude: string;
    floors: string;
    unit_count: string;
    status: string;
    notes: string;
};

type Props = {
    buildings: Building[];
    canManage: boolean;
    statuses: string[];
};

const emptyForm: BuildingForm = {
    name: '',
    code: '',
    address: '',
    latitude: '',
    longitude: '',
    floors: '',
    unit_count: '',
    status: 'active',
    notes: '',
};

export default function TopologyBuildingsPage({ buildings, canManage, statuses }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
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
    const form = useForm<BuildingForm>(emptyForm);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/operations/topology/buildings', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout>
            <Head title={t('topology_buildings.title')} />

            <div>
                <p className="eyebrow">{t('topology_buildings.eyebrow')}</p>
                <h1 className="page-title">{t('topology_buildings.title')}</h1>
                <p className="page-subtitle text-pretty">{t('topology_buildings.subtitle')}</p>
            </div>

            {canManage && (
                <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Plus size={17} className="text-brand" />
                        <div>
                            <h2 className="section-title">{t('topology_buildings.add')}</h2>
                            <p className="mt-1 text-sm text-muted">{t('topology_buildings.add_description')}</p>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">{t('Name')}</span>
                            <input
                                id="building-name"
                                className="field"
                                {...fieldA11y('building-name', form.errors.name)}
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                placeholder={t('Cedar Residence')}
                            />
                            {fieldError('building-name', form.errors.name)}
                        </label>
                        <label>
                            <span className="field-label">{t('Code')}</span>
                            <input
                                id="building-code"
                                className="field uppercase"
                                {...fieldA11y('building-code', form.errors.code)}
                                value={form.data.code}
                                onChange={(event) => form.setData('code', event.target.value)}
                                placeholder="CEDAR-01"
                            />
                            {fieldError('building-code', form.errors.code)}
                        </label>
                        <label className="md:col-span-2">
                            <span className="field-label">{t('Address')}</span>
                            <input
                                id="building-address"
                                className="field"
                                {...fieldA11y('building-address', form.errors.address)}
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                                placeholder={t('Street, district, city')}
                            />
                            {fieldError('building-address', form.errors.address)}
                        </label>
                        <label>
                            <span className="field-label">{t('Floors')}</span>
                            <input
                                id="building-floors"
                                type="number"
                                min="0"
                                className="field"
                                {...fieldA11y('building-floors', form.errors.floors)}
                                value={form.data.floors}
                                onChange={(event) => form.setData('floors', event.target.value)}
                                placeholder="8"
                            />
                            {fieldError('building-floors', form.errors.floors)}
                        </label>
                        <label>
                            <span className="field-label">{t('Units')}</span>
                            <input
                                id="building-unit-count"
                                type="number"
                                min="0"
                                className="field"
                                {...fieldA11y('building-unit-count', form.errors.unit_count)}
                                value={form.data.unit_count}
                                onChange={(event) => form.setData('unit_count', event.target.value)}
                                placeholder="64"
                            />
                            {fieldError('building-unit-count', form.errors.unit_count)}
                        </label>
                        <label>
                            <span className="field-label">{t('Status')}</span>
                            <ResponsiveSelect
                                id="building-status"
                                className="field"
                                {...fieldA11y('building-status', form.errors.status)}
                                value={form.data.status}
                                onChange={(event) => form.setData('status', event.target.value)}
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {t(status.replace('_', ' '))}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {fieldError('building-status', form.errors.status)}
                        </label>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        errors={form.errors}
                        fieldPrefix="building-location"
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                        title={t('topology_buildings.location')}
                        description={t('topology_buildings.location_description')}
                    />
                    <label>
                        <span className="field-label">{t('Notes')}</span>
                        <textarea
                            id="building-notes"
                            className="field min-h-20"
                            {...fieldA11y('building-notes', form.errors.notes)}
                            value={form.data.notes}
                            onChange={(event) => form.setData('notes', event.target.value)}
                                placeholder={t('Access notes, caretaker, or riser details')}
                        />
                        {fieldError('building-notes', form.errors.notes)}
                    </label>
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={form.processing}>
                            <Plus size={16} /> {t('topology_buildings.add')}
                        </button>
                    </div>
                </form>
            )}

            <section className="card mt-8 overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2">
                        <Building2 size={17} className="text-brand" />
                        <h2 className="section-title">{t('Sites')}</h2>
                    </div>
                    <p className="text-sm tabular-nums text-muted">
                        {buildings.length.toLocaleString()} {t('total')}
                    </p>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    {buildings.map((building) => (
                        <Link
                            key={building.public_id}
                            href={`/operations/topology/buildings/${building.public_id}`}
                            className="rounded-xl border border-line p-5 transition hover:border-brand/40 hover:bg-sand/30"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="font-semibold text-ink">{building.name}</p>
                                    <p className="mt-1 text-xs uppercase tracking-wide text-muted">{building.code}</p>
                                </div>
                                <StatusBadge status={building.status} />
                            </div>
                            <p className="mt-4 flex items-start gap-2 text-sm text-muted">
                                <MapPinned size={16} className="mt-0.5 shrink-0 text-brand" />
                                {building.address ?? t('topology_buildings.no_address')}
                            </p>
                            <div className="mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm">
                                <div>
                                    <p className="text-xs text-muted">{t('Boxes')}</p>
                                    <p className="mt-1 font-semibold tabular-nums">
                                        {building.distribution_boxes_count}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted">{t('topology_buildings.active_services')}</p>
                                    <p className="mt-1 font-semibold tabular-nums">{building.active_services_count}</p>
                                </div>
                            </div>
                        </Link>
                    ))}
                    {buildings.length === 0 && (
                        <div className="col-span-full px-5 py-14 text-center">
                            <Building2 className="mx-auto text-muted" size={30} />
                            <p className="mt-3 font-semibold">{t('topology_buildings.no_buildings')}</p>
                            <p className="mt-1 text-sm text-muted">
                                {t('topology_buildings.no_buildings_description')}
                            </p>
                        </div>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}
