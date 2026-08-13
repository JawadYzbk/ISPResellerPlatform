import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Activity, Gauge, Network, Pencil, Plus, Save, Signal, Thermometer, X } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Pop = { id: number; name: string; code: string };
type Service = {
    public_id: string;
    username: string;
    customer: { name: string; code: string } | null;
};
type Reading = {
    recorded_at: string | null;
    onu_serial: string | null;
    rx_dbm: string | null;
    tx_dbm: string | null;
    temperature_c: string | null;
    service: { username: string; customer: string | null } | null;
};
type Device = {
    public_id: string;
    name: string;
    code: string;
    device_type: string;
    vendor: string | null;
    model: string | null;
    host: string | null;
    management_port: number | null;
    status: string;
    notes: string | null;
    pop: { name: string; code: string } | null;
    readings_count: number;
    latest_reading: Reading | null;
};
type FormData = {
    pop_id: string;
    name: string;
    code: string;
    device_type: string;
    vendor: string;
    model: string;
    host: string;
    management_port: string;
    status: string;
    notes: string;
};
type ReadingFormData = {
    optical_device_id: string;
    service_id: string;
    onu_serial: string;
    rx_dbm: string;
    tx_dbm: string;
    temperature_c: string;
    recorded_at: string;
};
type Props = PageProps & {
    devices: Device[];
    pops: Pop[];
    services: Service[];
    canManage: boolean;
    deviceTypes: string[];
    deviceStatuses: string[];
};

const emptyDevice: FormData = {
    pop_id: '',
    name: '',
    code: '',
    device_type: 'olt',
    vendor: '',
    model: '',
    host: '',
    management_port: '',
    status: 'active',
    notes: '',
};

const deviceTypeLabels: Record<string, string> = { olt: 'OLT', onu: 'ONU', splitter: 'Splitter' };
const statusLabels: Record<string, string> = {
    active: 'Active',
    maintenance: 'Maintenance',
    retired: 'Retired',
};

function localDateTime(): string {
    const date = new Date();
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60 * 1000);

    return local.toISOString().slice(0, 16);
}

function deviceData(device: Device): FormData {
    return {
        pop_id: '',
        name: device.name,
        code: device.code,
        device_type: device.device_type,
        vendor: device.vendor ?? '',
        model: device.model ?? '',
        host: device.host ?? '',
        management_port: device.management_port?.toString() ?? '',
        status: device.status,
        notes: device.notes ?? '',
    };
}

function readingValue(value: string | null | undefined, suffix: string): string {
    return value === null || value === undefined ? '—' : `${value}${suffix}`;
}

function formatDate(value: string | null, t: (key: string) => string): string {
    if (!value) return t('No reading recorded');

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

function ErrorText({ message }: { message?: string }) {
    return message ? <p className="field-error">{message}</p> : null;
}

function DeviceFields({
    form,
    pops,
    deviceTypes,
    deviceStatuses,
    t,
}: {
    form: ReturnType<typeof useForm<FormData>>;
    pops: Pop[];
    deviceTypes: string[];
    deviceStatuses: string[];
    t: (key: string) => string;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label>
                <span className="field-label">{t('Name')}</span>
                <input
                    className="field"
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    placeholder="Core OLT"
                />
                <ErrorText message={form.errors.name} />
            </label>
            <label>
                <span className="field-label">{t('Code')}</span>
                <input
                    className="field uppercase"
                    value={form.data.code}
                    onChange={(event) => form.setData('code', event.target.value)}
                    placeholder="OLT-CENTRAL-01"
                />
                <ErrorText message={form.errors.code} />
            </label>
            <label>
                <span className="field-label">{t('Type')}</span>
                <ResponsiveSelect
                    className="field"
                    value={form.data.device_type}
                    onChange={(event) => form.setData('device_type', event.target.value)}
                >
                    {deviceTypes.map((type) => (
                        <option key={type} value={type}>
                            {t(deviceTypeLabels[type] ?? type)}
                        </option>
                    ))}
                </ResponsiveSelect>
                <ErrorText message={form.errors.device_type} />
            </label>
            <label>
                <span className="field-label">{t('Status')}</span>
                <ResponsiveSelect
                    className="field"
                    value={form.data.status}
                    onChange={(event) => form.setData('status', event.target.value)}
                >
                    {deviceStatuses.map((status) => (
                        <option key={status} value={status}>
                            {t(statusLabels[status] ?? status)}
                        </option>
                    ))}
                </ResponsiveSelect>
                <ErrorText message={form.errors.status} />
            </label>
            <label>
                <span className="field-label">{t('POP')}</span>
                <ResponsiveSelect
                    className="field"
                    value={form.data.pop_id}
                    onChange={(event) => form.setData('pop_id', event.target.value)}
                >
                    <option value="">{t('No POP assigned')}</option>
                    {pops.map((pop) => (
                        <option key={pop.id} value={pop.id}>
                            {pop.name} · {pop.code}
                        </option>
                    ))}
                </ResponsiveSelect>
                <ErrorText message={form.errors.pop_id} />
            </label>
            <label>
                <span className="field-label">{t('Vendor')}</span>
                <input
                    className="field"
                    value={form.data.vendor}
                    onChange={(event) => form.setData('vendor', event.target.value)}
                    placeholder="Huawei, ZTE, Nokia"
                />
                <ErrorText message={form.errors.vendor} />
            </label>
            <label>
                <span className="field-label">{t('Model')}</span>
                <input
                    className="field"
                    value={form.data.model}
                    onChange={(event) => form.setData('model', event.target.value)}
                    placeholder="MA5800"
                />
                <ErrorText message={form.errors.model} />
            </label>
            <label>
                <span className="field-label">{t('Management host')}</span>
                <input
                    className="field"
                    value={form.data.host}
                    onChange={(event) => form.setData('host', event.target.value)}
                    placeholder="10.0.0.10"
                />
                <ErrorText message={form.errors.host} />
            </label>
            <label>
                <span className="field-label">{t('Management port')}</span>
                <input
                    className="field"
                    type="number"
                    min="1"
                    max="65535"
                    value={form.data.management_port}
                    onChange={(event) => form.setData('management_port', event.target.value)}
                    placeholder="161"
                />
                <ErrorText message={form.errors.management_port} />
            </label>
            <label className="md:col-span-2 xl:col-span-3">
                <span className="field-label">{t('Notes')}</span>
                <textarea
                    className="field min-h-24 resize-y"
                    value={form.data.notes}
                    onChange={(event) => form.setData('notes', event.target.value)}
                    placeholder="Rack, cabinet, vendor access notes"
                />
                <ErrorText message={form.errors.notes} />
            </label>
        </div>
    );
}

export default function OpticalOperationsPage({
    devices,
    pops,
    services,
    canManage,
    deviceTypes,
    deviceStatuses,
}: Props) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);
    const [editingDevice, setEditingDevice] = useState<string | null>(null);
    const createForm = useForm<FormData>(emptyDevice);
    const editForm = useForm<FormData>(emptyDevice);
    const readingForm = useForm<ReadingFormData>({
        optical_device_id: devices[0]?.public_id ?? '',
        service_id: '',
        onu_serial: '',
        rx_dbm: '',
        tx_dbm: '',
        temperature_c: '',
        recorded_at: localDateTime(),
    });

    const submitCreate = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        createForm.post('/operations/optical/devices', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    const startEditing = (device: Device) => {
        setEditingDevice(device.public_id);
        editForm.setData(deviceData(device));
        editForm.clearErrors();
    };

    const cancelEditing = () => {
        setEditingDevice(null);
        editForm.reset();
        editForm.clearErrors();
    };

    const submitEdit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!editingDevice) return;

        editForm.patch(`/operations/optical/devices/${editingDevice}`, {
            preserveScroll: true,
            onSuccess: cancelEditing,
        });
    };

    const submitReading = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        readingForm.post('/operations/optical/readings', { preserveScroll: true });
    };

    return (
        <AppLayout>
            <Head title={t('Optical access')} />

            <div>
                <p className="eyebrow">{t('Network inventory')}</p>
                <h1 className="page-title">{t('Optical access')}</h1>
                <p className="page-subtitle">
                    {t(
                        'Register OLTs, ONUs, and splitters, then keep signal readings connected to the customer service.',
                    )}
                </p>
            </div>

            <section className="card mt-8 p-6">
                <div className="flex items-start gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                        <Network size={18} />
                    </div>
                    <div>
                        <h2 className="section-title">{t('Optical inventory')}</h2>
                        <p className="mt-1 text-sm text-muted">
                            {t(
                                'Manual readings are available now. Vendor drivers can later feed the same reading history without exposing device credentials to the browser.',
                            )}
                        </p>
                    </div>
                </div>
            </section>

            {canManage && (
                <form onSubmit={submitCreate} className="card mt-6 space-y-5 p-6">
                    <div className="flex items-center gap-2">
                        <Plus size={17} className="text-brand" />
                        <h2 className="section-title">{t('Add optical device')}</h2>
                    </div>
                    <DeviceFields
                        form={createForm}
                        pops={pops}
                        deviceTypes={deviceTypes}
                        deviceStatuses={deviceStatuses}
                        t={t}
                    />
                    <div className="flex justify-end">
                        <button type="submit" className="button-primary" disabled={createForm.processing}>
                            <Plus size={16} /> {t('Add device')}
                        </button>
                    </div>
                </form>
            )}

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(360px,0.7fr)]">
                <section className="card overflow-hidden">
                    <div className="flex items-center justify-between border-b border-line px-5 py-4">
                        <div className="flex items-center gap-2">
                            <Signal size={17} className="text-brand" />
                            <h2 className="text-sm font-semibold">{t('Registered devices')}</h2>
                        </div>
                        <span className="text-xs text-muted">
                            {devices.length} {t('device(s)')}
                        </span>
                    </div>

                    {devices.length === 0 ? (
                        <div className="px-5 py-12 text-center">
                            <Network size={24} className="mx-auto text-muted" />
                            <p className="mt-3 text-sm font-semibold">{t('No optical devices registered')}</p>
                            <p className="mt-1 text-sm text-muted">
                                {t('Add an OLT or ONU to begin recording signal history.')}
                            </p>
                        </div>
                    ) : (
                        <div className="divide-y divide-line">
                            {devices.map((device) => (
                                <article key={device.public_id} className="p-5">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="flex items-start gap-3">
                                            <div className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                                <Network size={17} />
                                            </div>
                                            <div>
                                                <h3 className="text-sm font-semibold">{device.name}</h3>
                                                <p className="mt-1 text-xs text-muted">
                                                    {device.code} ·{' '}
                                                    {t(deviceTypeLabels[device.device_type] ?? device.device_type)}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="rounded-full bg-sand px-2.5 py-1 text-xs font-semibold text-muted">
                                                {t(statusLabels[device.status] ?? device.status)}
                                            </span>
                                            {canManage && editingDevice !== device.public_id && (
                                                <button
                                                    type="button"
                                                    className="button-secondary"
                                                    onClick={() => startEditing(device)}
                                                >
                                                    <Pencil size={14} /> {t('Edit')}
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    <div className="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                                        <div>
                                            <p className="field-label">{t('Location')}</p>
                                            <p>
                                                {device.pop
                                                    ? `${device.pop.name} · ${device.pop.code}`
                                                    : t('No POP assigned')}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="field-label">{t('Device')}</p>
                                            <p>
                                                {[device.vendor, device.model].filter(Boolean).join(' · ') ||
                                                    t('No model set')}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="field-label">{t('Management')}</p>
                                            <p>
                                                {device.host
                                                    ? `${device.host}${device.management_port ? `:${device.management_port}` : ''}`
                                                    : t('Not configured')}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mt-4 rounded-xl border border-line bg-sand/40 p-4">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <Activity size={16} className="text-brand" />
                                                <p className="text-xs font-semibold uppercase tracking-wider text-muted">
                                                    {t('Latest reading')}
                                                </p>
                                            </div>
                                            <span className="text-xs text-muted">
                                                {device.readings_count} {t('total')}
                                            </span>
                                        </div>
                                        {device.latest_reading ? (
                                            <div className="mt-3 grid gap-3 text-sm sm:grid-cols-4">
                                                <div>
                                                    <p className="field-label">{t('ONU serial')}</p>
                                                    <p>{device.latest_reading.onu_serial || '—'}</p>
                                                </div>
                                                <div>
                                                    <p className="field-label">{t('RX power')}</p>
                                                    <p>{readingValue(device.latest_reading.rx_dbm, ' dBm')}</p>
                                                </div>
                                                <div>
                                                    <p className="field-label">{t('TX power')}</p>
                                                    <p>{readingValue(device.latest_reading.tx_dbm, ' dBm')}</p>
                                                </div>
                                                <div>
                                                    <p className="field-label">{t('Temperature')}</p>
                                                    <p>{readingValue(device.latest_reading.temperature_c, ' °C')}</p>
                                                </div>
                                                <div className="sm:col-span-4 text-xs text-muted">
                                                    {device.latest_reading.service
                                                        ? `Service ${device.latest_reading.service.username}${device.latest_reading.service.customer ? ` · ${device.latest_reading.service.customer}` : ''}`
                                                        : t('Not linked to a service')}{' '}
                                                    · {formatDate(device.latest_reading.recorded_at, t)}
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="mt-3 text-sm text-muted">
                                                {t('No optical reading has been recorded yet.')}
                                            </p>
                                        )}
                                    </div>

                                    {editingDevice === device.public_id && (
                                        <form
                                            onSubmit={submitEdit}
                                            className="mt-5 space-y-5 border-t border-line pt-5"
                                        >
                                            <DeviceFields
                                                form={editForm}
                                                pops={pops}
                                                deviceTypes={deviceTypes}
                                                deviceStatuses={deviceStatuses}
                                                t={t}
                                            />
                                            <div className="flex justify-end gap-2">
                                                <button
                                                    type="button"
                                                    className="button-secondary"
                                                    onClick={cancelEditing}
                                                >
                                                    <X size={15} /> {t('Cancel')}
                                                </button>
                                                <button
                                                    type="submit"
                                                    className="button-primary"
                                                    disabled={editForm.processing}
                                                >
                                                    <Save size={15} /> {t('Save changes')}
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                {canManage && (
                    <form onSubmit={submitReading} className="card h-fit space-y-5 p-6">
                        <div className="flex items-start gap-3">
                            <div className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-soft text-brand">
                                <Gauge size={18} />
                            </div>
                            <div>
                                <h2 className="section-title">{t('Record optical reading')}</h2>
                                <p className="mt-1 text-sm text-muted">
                                    {t('Capture manual measurements against a live service.')}
                                </p>
                            </div>
                        </div>

                        <label>
                            <span className="field-label">{t('Device')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={readingForm.data.optical_device_id}
                                onChange={(event) => readingForm.setData('optical_device_id', event.target.value)}
                            >
                                <option value="">{t('Choose a device')}</option>
                                {devices.map((device) => (
                                    <option key={device.public_id} value={device.public_id}>
                                        {device.name} · {device.code}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            <ErrorText message={readingForm.errors.optical_device_id} />
                        </label>
                        <label>
                            <span className="field-label">{t('Service')}</span>
                            <ResponsiveSelect
                                className="field"
                                value={readingForm.data.service_id}
                                onChange={(event) => readingForm.setData('service_id', event.target.value)}
                            >
                                <option value="">{t('No service selected')}</option>
                                {services.map((service) => (
                                    <option key={service.public_id} value={service.public_id}>
                                        {service.username} ·{' '}
                                        {service.customer?.name ?? service.customer?.code ?? t('Customer')}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            <ErrorText message={readingForm.errors.service_id} />
                        </label>
                        <label>
                            <span className="field-label">{t('ONU serial')}</span>
                            <input
                                className="field"
                                value={readingForm.data.onu_serial}
                                onChange={(event) => readingForm.setData('onu_serial', event.target.value)}
                                placeholder="HWTC12345678"
                            />
                            <ErrorText message={readingForm.errors.onu_serial} />
                        </label>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('RX power (dBm)')}</span>
                                <input
                                    className="field"
                                    type="number"
                                    step="0.01"
                                    min="-60"
                                    max="10"
                                    value={readingForm.data.rx_dbm}
                                    onChange={(event) => readingForm.setData('rx_dbm', event.target.value)}
                                    placeholder="-18.50"
                                />
                                <ErrorText message={readingForm.errors.rx_dbm} />
                            </label>
                            <label>
                                <span className="field-label">{t('TX power (dBm)')}</span>
                                <input
                                    className="field"
                                    type="number"
                                    step="0.01"
                                    min="-20"
                                    max="20"
                                    value={readingForm.data.tx_dbm}
                                    onChange={(event) => readingForm.setData('tx_dbm', event.target.value)}
                                    placeholder="2.20"
                                />
                                <ErrorText message={readingForm.errors.tx_dbm} />
                            </label>
                        </div>
                        <label>
                            <span className="field-label">
                                <Thermometer size={14} className="me-1 inline" /> {t('Temperature (°C)')}
                            </span>
                            <input
                                className="field"
                                type="number"
                                step="0.01"
                                min="-50"
                                max="150"
                                value={readingForm.data.temperature_c}
                                onChange={(event) => readingForm.setData('temperature_c', event.target.value)}
                                placeholder="42.00"
                            />
                            <ErrorText message={readingForm.errors.temperature_c} />
                        </label>
                        <label>
                            <span className="field-label">{t('Recorded at')}</span>
                            <input
                                className="field"
                                type="datetime-local"
                                value={readingForm.data.recorded_at}
                                onChange={(event) => readingForm.setData('recorded_at', event.target.value)}
                            />
                            <ErrorText message={readingForm.errors.recorded_at} />
                        </label>
                        <button
                            type="submit"
                            className="button-primary w-full"
                            disabled={readingForm.processing || devices.length === 0}
                        >
                            <Save size={16} /> {t('Record reading')}
                        </button>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}
