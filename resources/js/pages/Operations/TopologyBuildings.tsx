import ResponsiveSelect from '@/components/ui/responsive-select';
import CustomerLocationFields from '@/components/CustomerLocationFields';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, MapPinned, Plus } from 'lucide-react';

import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';

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
    const form = useForm<BuildingForm>(emptyForm);

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post('/operations/topology/buildings', { onSuccess: () => form.reset() });
    };

    return (
        <AppLayout>
            <Head title="Buildings and boxes" />

            <div>
                <p className="eyebrow">Network topology</p>
                <h1 className="page-title">Buildings and distribution boxes</h1>
                <p className="page-subtitle text-pretty">
                    Map the physical network from subscriber buildings to ports, so installation and support work has a
                    reliable place to start.
                </p>
            </div>

            {canManage && (
                <form onSubmit={submit} className="card mt-8 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Plus size={17} className="text-brand" />
                        <div>
                            <h2 className="section-title">Add a building or site</h2>
                            <p className="mt-1 text-sm text-muted">Use a stable code that field teams can recognize.</p>
                        </div>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <label>
                            <span className="field-label">Name</span>
                            <input className="field" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} placeholder="Cedar Residence" />
                            {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                        </label>
                        <label>
                            <span className="field-label">Code</span>
                            <input className="field uppercase" value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} placeholder="CEDAR-01" />
                            {form.errors.code && <p className="field-error">{form.errors.code}</p>}
                        </label>
                        <label className="md:col-span-2">
                            <span className="field-label">Address</span>
                            <input className="field" value={form.data.address} onChange={(event) => form.setData('address', event.target.value)} placeholder="Street, district, city" />
                            {form.errors.address && <p className="field-error">{form.errors.address}</p>}
                        </label>
                        <label>
                            <span className="field-label">Floors</span>
                            <input type="number" min="0" className="field" value={form.data.floors} onChange={(event) => form.setData('floors', event.target.value)} placeholder="8" />
                            {form.errors.floors && <p className="field-error">{form.errors.floors}</p>}
                        </label>
                        <label>
                            <span className="field-label">Units</span>
                            <input type="number" min="0" className="field" value={form.data.unit_count} onChange={(event) => form.setData('unit_count', event.target.value)} placeholder="64" />
                            {form.errors.unit_count && <p className="field-error">{form.errors.unit_count}</p>}
                        </label>
                        <label>
                            <span className="field-label">Status</span>
                            <ResponsiveSelect className="field" value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}>
                                {statuses.map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}
                            </ResponsiveSelect>
                            {form.errors.status && <p className="field-error">{form.errors.status}</p>}
                        </label>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                        title="Building location"
                        description="Optional GPS coordinates for dispatch, surveys, and map-based planning."
                    />
                    <label>
                        <span className="field-label">Notes</span>
                        <textarea className="field min-h-20" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder="Access notes, caretaker, or riser details" />
                        {form.errors.notes && <p className="field-error">{form.errors.notes}</p>}
                    </label>
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={form.processing}><Plus size={16} /> Add building</button>
                    </div>
                </form>
            )}

            <section className="card mt-8 overflow-hidden">
                <div className="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
                    <div className="flex items-center gap-2"><Building2 size={17} className="text-brand" /><h2 className="section-title">Sites</h2></div>
                    <p className="text-sm tabular-nums text-muted">{buildings.length.toLocaleString()} total</p>
                </div>
                <div className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                    {buildings.map((building) => (
                        <Link key={building.public_id} href={`/operations/topology/buildings/${building.public_id}`} className="rounded-xl border border-line p-5 transition hover:border-brand/40 hover:bg-sand/30">
                            <div className="flex items-start justify-between gap-4">
                                <div><p className="font-semibold text-ink">{building.name}</p><p className="mt-1 text-xs uppercase tracking-wide text-muted">{building.code}</p></div>
                                <StatusBadge status={building.status} />
                            </div>
                            <p className="mt-4 flex items-start gap-2 text-sm text-muted"><MapPinned size={16} className="mt-0.5 shrink-0 text-brand" />{building.address ?? 'No address recorded'}</p>
                            <div className="mt-5 grid grid-cols-2 gap-3 border-t border-line pt-4 text-sm">
                                <div><p className="text-xs text-muted">Boxes</p><p className="mt-1 font-semibold tabular-nums">{building.distribution_boxes_count}</p></div>
                                <div><p className="text-xs text-muted">Active services</p><p className="mt-1 font-semibold tabular-nums">{building.active_services_count}</p></div>
                            </div>
                        </Link>
                    ))}
                    {buildings.length === 0 && <div className="col-span-full px-5 py-14 text-center"><Building2 className="mx-auto text-muted" size={30} /><p className="mt-3 font-semibold">No buildings yet</p><p className="mt-1 text-sm text-muted">Add the first site to begin mapping subscriber access.</p></div>}
                </div>
            </section>
        </AppLayout>
    );
}
