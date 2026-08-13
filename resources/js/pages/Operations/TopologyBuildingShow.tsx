import ResponsiveSelect from '@/components/ui/responsive-select';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import CustomerLocationFields from '@/components/CustomerLocationFields';
import type { Status } from '@/components/StatusBadge';
import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Box, Cable, MapPinned, Plus, Save, Unplug } from 'lucide-react';

type Service = {
    public_id: string;
    username: string | null;
    status: Status;
    customer: { public_id: string; name: string; code: string } | null;
    plan: { name: string } | null;
    distribution_box: { public_id: string; code: string } | null;
    network_port: number | null;
};

type Box = {
    public_id: string;
    name: string;
    code: string;
    box_type: string;
    capacity_ports: number;
    used_ports: number;
    latitude: string | null;
    longitude: string | null;
    status: Status;
    notes: string | null;
    pop: { id: number; name: string; code: string } | null;
    services: Service[];
};

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
    notes: string | null;
    boxes: Box[];
};

type FormValues = {
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

type BoxFormValues = FormValues & { pop_id: string; box_type: string; capacity_ports: string };

type Props = {
    building: Building;
    services: Service[];
    pops: { id: number; name: string; code: string }[];
    canManage: boolean;
    buildingStatuses: string[];
    boxTypes: string[];
    boxStatuses: string[];
};

function ErrorText({ message }: { message?: string }) {
    return message ? <p className="field-error">{message}</p> : null;
}

function BoxCard({ box, services, pops, canManage, boxTypes, boxStatuses }: { box: Box; services: Service[]; pops: Props['pops']; canManage: boolean; boxTypes: string[]; boxStatuses: string[] }) {
    const editForm = useForm<BoxFormValues>({
        name: box.name,
        code: box.code,
        address: '',
        latitude: box.latitude ?? '',
        longitude: box.longitude ?? '',
        floors: '',
        unit_count: '',
        status: box.status,
        notes: box.notes ?? '',
        pop_id: box.pop ? String(box.pop.id) : '',
        box_type: box.box_type,
        capacity_ports: String(box.capacity_ports),
    });
    const assignmentForm = useForm({ service_id: '', network_port: '' });
    const usage = Math.min(100, (box.used_ports / Math.max(1, box.capacity_ports)) * 100);

    const save = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editForm.put(`/operations/topology/boxes/${box.public_id}`, { preserveScroll: true });
    };

    const assign = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!assignmentForm.data.service_id) {
            assignmentForm.setError('service_id', 'Choose a service first.');
            return;
        }
        assignmentForm.transform((data) => ({ distribution_box_id: box.public_id, network_port: data.network_port }));
        assignmentForm.post(`/operations/topology/services/${assignmentForm.data.service_id}/assignment`, { preserveScroll: true, onSuccess: () => assignmentForm.reset() });
    };

    return (
        <section className="card overflow-hidden">
            <div className="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex items-start gap-3"><span className="grid size-9 shrink-0 place-items-center rounded-lg bg-brand/10 text-brand"><Box size={18} /></span><div><h2 className="font-semibold">{box.name}</h2><p className="mt-1 text-xs uppercase tracking-wide text-muted">{box.code} · {box.box_type}</p></div></div>
                <StatusBadge status={box.status} />
            </div>
            <div className="space-y-5 p-5">
                <div><div className="flex justify-between gap-4 text-sm"><span className="text-muted">Port capacity</span><span className="font-semibold tabular-nums">{box.used_ports} / {box.capacity_ports}</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-sand"><div className="h-full rounded-full bg-brand" style={{ width: `${usage}%` }} /></div></div>
                {canManage && <form onSubmit={save} className="space-y-4 rounded-xl bg-sand/40 p-4"><div className="grid gap-4 sm:grid-cols-2"><label><span className="field-label">Name</span><input className="field" value={editForm.data.name} onChange={(event) => editForm.setData('name', event.target.value)} /><ErrorText message={editForm.errors.name} /></label><label><span className="field-label">Code</span><input className="field uppercase" value={editForm.data.code} onChange={(event) => editForm.setData('code', event.target.value)} /><ErrorText message={editForm.errors.code} /></label><label><span className="field-label">Type</span><ResponsiveSelect className="field" value={editForm.data.box_type} onChange={(event) => editForm.setData('box_type', event.target.value)}>{boxTypes.map((type) => <option key={type} value={type}>{type.replace('_', ' ')}</option>)}</ResponsiveSelect><ErrorText message={editForm.errors.box_type} /></label><label><span className="field-label">Capacity ports</span><input type="number" min="1" className="field" value={editForm.data.capacity_ports} onChange={(event) => editForm.setData('capacity_ports', event.target.value)} /><ErrorText message={editForm.errors.capacity_ports} /></label><label><span className="field-label">POP</span><ResponsiveSelect className="field" value={editForm.data.pop_id} onChange={(event) => editForm.setData('pop_id', event.target.value)}><option value="">No POP linked</option>{pops.map((pop) => <option key={pop.id} value={pop.id}>{pop.name} · {pop.code}</option>)}</ResponsiveSelect><ErrorText message={editForm.errors.pop_id} /></label><label><span className="field-label">Status</span><ResponsiveSelect className="field" value={editForm.data.status} onChange={(event) => editForm.setData('status', event.target.value)}>{boxStatuses.map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}</ResponsiveSelect><ErrorText message={editForm.errors.status} /></label></div><CustomerLocationFields latitude={editForm.data.latitude} longitude={editForm.data.longitude} onLatitudeChange={(value) => editForm.setData('latitude', value)} onLongitudeChange={(value) => editForm.setData('longitude', value)} title="Box location" description="Pin the cabinet or splitter location for field technicians." /><label><span className="field-label">Notes</span><textarea className="field min-h-16" value={editForm.data.notes} onChange={(event) => editForm.setData('notes', event.target.value)} /><ErrorText message={editForm.errors.notes} /></label><div className="flex justify-end"><button type="submit" className="button-secondary" disabled={editForm.processing}><Save size={15} /> Save box</button></div></form>}
                {canManage && <form onSubmit={assign} className="grid gap-3 rounded-xl border border-brand/20 bg-brand/5 p-4 sm:grid-cols-[1fr_9rem_auto] sm:items-end"><label><span className="field-label">Assign service</span><ResponsiveSelect className="field" value={assignmentForm.data.service_id} onChange={(event) => assignmentForm.setData('service_id', event.target.value)}><option value="">Choose a service</option>{services.map((service) => <option key={service.public_id} value={service.public_id}>{service.customer?.name ?? 'Unknown customer'} · {service.username ?? service.public_id.slice(0, 8)}</option>)}</ResponsiveSelect><ErrorText message={assignmentForm.errors.service_id || (assignmentForm.errors as Record<string, string | undefined>).topology} /></label><label><span className="field-label">Port</span><input type="number" min="1" max={box.capacity_ports} className="field" value={assignmentForm.data.network_port} onChange={(event) => assignmentForm.setData('network_port', event.target.value)} placeholder="1" /><ErrorText message={assignmentForm.errors.network_port} /></label><button type="submit" className="button-primary" disabled={assignmentForm.processing}><Cable size={15} /> Assign</button></form>}
                <div className="divide-y divide-line rounded-xl border border-line"><div className="flex items-center justify-between gap-3 px-4 py-3"><p className="text-sm font-semibold">Assigned services</p><p className="text-xs text-muted">{box.used_ports} occupied port{box.used_ports === 1 ? '' : 's'}</p></div>{box.services.map((service) => <div key={service.public_id} className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><p className="text-sm font-semibold">{service.customer?.name ?? 'Unknown customer'}</p><p className="mt-1 text-xs text-muted">{service.customer?.code ?? '—'} · {service.plan?.name ?? 'No plan'} · port <span className="font-semibold tabular-nums">{service.network_port ?? '—'}</span></p></div><div className="flex items-center gap-3"><StatusBadge status={service.status} />{canManage && <ConfirmDialog title="Unassign service?" description="The physical box and port will be cleared from this service. The service itself will stay active." confirmLabel="Unassign" destructive onConfirm={() => router.delete(`/operations/topology/services/${service.public_id}/assignment`, { preserveScroll: true })}><button type="button" className="button-quiet text-rose-700"><Unplug size={14} /> Unassign</button></ConfirmDialog>}</div></div>)}{box.services.length === 0 && <p className="px-4 py-8 text-center text-sm text-muted">No active services assigned to this box.</p>}</div>
            </div>
        </section>
    );
}

export default function TopologyBuildingShowPage({ building, services, pops, canManage, buildingStatuses, boxTypes, boxStatuses }: Props) {
    const buildingForm = useForm<FormValues>({ name: building.name, code: building.code, address: building.address ?? '', latitude: building.latitude ?? '', longitude: building.longitude ?? '', floors: building.floors === null ? '' : String(building.floors), unit_count: building.unit_count === null ? '' : String(building.unit_count), status: building.status, notes: building.notes ?? '' });
    const boxForm = useForm<BoxFormValues>({ name: '', code: '', address: '', latitude: '', longitude: '', floors: '', unit_count: '', status: 'active', notes: '', pop_id: '', box_type: boxTypes[0] ?? 'distribution', capacity_ports: '8' });

    const updateBuilding = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        buildingForm.put(`/operations/topology/buildings/${building.public_id}`, { preserveScroll: true });
    };
    const createBox = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        boxForm.post(`/operations/topology/buildings/${building.public_id}/boxes`, { preserveScroll: true, onSuccess: () => boxForm.reset('name', 'code', 'address', 'latitude', 'longitude', 'floors', 'unit_count', 'notes', 'pop_id') });
    };

    return (
        <AppLayout>
            <Head title={building.name} />
            <Link href="/operations/topology/buildings" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"><ArrowLeft size={16} /> Back to buildings</Link>
            <div className="flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p className="eyebrow">Network topology</p><div className="mt-2 flex flex-wrap items-center gap-3"><h1 className="page-title">{building.name}</h1><StatusBadge status={building.status} /></div><p className="page-subtitle">{building.code} · {building.address ?? 'No address recorded'}</p></div><div className="flex items-center gap-4 text-sm text-muted"><span className="tabular-nums">{building.boxes.length} boxes</span><span className="tabular-nums">{services.filter((service) => service.distribution_box?.public_id && building.boxes.some((box) => box.public_id === service.distribution_box?.public_id)).length} assigned services</span></div></div>

            {canManage && <form onSubmit={updateBuilding} className="card mt-8 space-y-5 p-6"><div className="flex items-center gap-2"><MapPinned size={17} className="text-brand" /><div><h2 className="section-title">Building details</h2><p className="mt-1 text-sm text-muted">Keep the location and access information current for installation work.</p></div></div><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"><label><span className="field-label">Name</span><input className="field" value={buildingForm.data.name} onChange={(event) => buildingForm.setData('name', event.target.value)} /><ErrorText message={buildingForm.errors.name} /></label><label><span className="field-label">Code</span><input className="field uppercase" value={buildingForm.data.code} onChange={(event) => buildingForm.setData('code', event.target.value)} /><ErrorText message={buildingForm.errors.code} /></label><label className="md:col-span-2"><span className="field-label">Address</span><input className="field" value={buildingForm.data.address} onChange={(event) => buildingForm.setData('address', event.target.value)} /><ErrorText message={buildingForm.errors.address} /></label><label><span className="field-label">Floors</span><input type="number" min="0" className="field" value={buildingForm.data.floors} onChange={(event) => buildingForm.setData('floors', event.target.value)} /><ErrorText message={buildingForm.errors.floors} /></label><label><span className="field-label">Units</span><input type="number" min="0" className="field" value={buildingForm.data.unit_count} onChange={(event) => buildingForm.setData('unit_count', event.target.value)} /><ErrorText message={buildingForm.errors.unit_count} /></label><label><span className="field-label">Status</span><ResponsiveSelect className="field" value={buildingForm.data.status} onChange={(event) => buildingForm.setData('status', event.target.value)}>{buildingStatuses.map((status) => <option key={status} value={status}>{status.replace('_', ' ')}</option>)}</ResponsiveSelect><ErrorText message={buildingForm.errors.status} /></label></div><CustomerLocationFields latitude={buildingForm.data.latitude} longitude={buildingForm.data.longitude} onLatitudeChange={(value) => buildingForm.setData('latitude', value)} onLongitudeChange={(value) => buildingForm.setData('longitude', value)} title="Building location" description="Click to place the site pin, or drag it to refine the coordinates." /><label><span className="field-label">Notes</span><textarea className="field min-h-20" value={buildingForm.data.notes} onChange={(event) => buildingForm.setData('notes', event.target.value)} /><ErrorText message={buildingForm.errors.notes} /></label><div className="flex justify-end"><button type="submit" className="button-primary" disabled={buildingForm.processing}><Save size={15} /> Save building</button></div></form>}

            {canManage && <form onSubmit={createBox} className="card mt-8 space-y-5 p-6"><div className="flex items-center gap-2"><Plus size={17} className="text-brand" /><div><h2 className="section-title">Add distribution box</h2><p className="mt-1 text-sm text-muted">Record a cabinet, splitter, or distribution point inside this site.</p></div></div><div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"><label><span className="field-label">Name</span><input className="field" value={boxForm.data.name} onChange={(event) => boxForm.setData('name', event.target.value)} placeholder="Cedar cabinet" /><ErrorText message={boxForm.errors.name} /></label><label><span className="field-label">Code</span><input className="field uppercase" value={boxForm.data.code} onChange={(event) => boxForm.setData('code', event.target.value)} placeholder="CEDAR-CAB-01" /><ErrorText message={boxForm.errors.code} /></label><label><span className="field-label">Type</span><ResponsiveSelect className="field" value={boxForm.data.box_type} onChange={(event) => boxForm.setData('box_type', event.target.value)}>{boxTypes.map((type) => <option key={type} value={type}>{type.replace('_', ' ')}</option>)}</ResponsiveSelect><ErrorText message={boxForm.errors.box_type} /></label><label><span className="field-label">Capacity ports</span><input type="number" min="1" className="field" value={boxForm.data.capacity_ports} onChange={(event) => boxForm.setData('capacity_ports', event.target.value)} /><ErrorText message={boxForm.errors.capacity_ports} /></label><label><span className="field-label">POP</span><ResponsiveSelect className="field" value={boxForm.data.pop_id} onChange={(event) => boxForm.setData('pop_id', event.target.value)}><option value="">No POP linked</option>{pops.map((pop) => <option key={pop.id} value={pop.id}>{pop.name} · {pop.code}</option>)}</ResponsiveSelect><ErrorText message={boxForm.errors.pop_id} /></label></div><CustomerLocationFields latitude={boxForm.data.latitude} longitude={boxForm.data.longitude} onLatitudeChange={(value) => boxForm.setData('latitude', value)} onLongitudeChange={(value) => boxForm.setData('longitude', value)} title="Box location" description="Optional GPS coordinates for the physical cabinet or splitter." /><label><span className="field-label">Notes</span><textarea className="field min-h-16" value={boxForm.data.notes} onChange={(event) => boxForm.setData('notes', event.target.value)} placeholder="Locked room, riser, or access notes" /><ErrorText message={boxForm.errors.notes} /></label><div className="flex justify-end"><button type="submit" className="button-primary" disabled={boxForm.processing}><Plus size={15} /> Add box</button></div></form>}

            <section className="mt-8 space-y-5"><div className="flex items-center gap-2"><Box size={17} className="text-brand" /><h2 className="section-title">Distribution boxes</h2></div>{building.boxes.map((box) => <BoxCard key={box.public_id} box={box} services={services} pops={pops} canManage={canManage} boxTypes={boxTypes} boxStatuses={boxStatuses} />)}{building.boxes.length === 0 && <div className="card px-5 py-14 text-center"><Box className="mx-auto text-muted" size={30} /><p className="mt-3 font-semibold">No boxes recorded</p><p className="mt-1 text-sm text-muted">Add the first distribution point above to start assigning subscriber ports.</p></div>}</section>
        </AppLayout>
    );
}
