import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarDays,
    CreditCard,
    Download,
    Edit3,
    FileText,
    MapPin,
    MessageCircle,
    MessageSquare,
    Phone,
    Plus,
    Pause,
    Play,
    RefreshCw,
    ShieldOff,
    Upload,
    Wifi,
    WifiOff,
} from 'lucide-react';

import { StatusBadge } from '@/components/StatusBadge';
import MapView from '@/components/MapView';
import AppLayout from '@/layouts/AppLayout';
import { formatBytes, formatDate, formatDuration, formatExpiryCountdown, formatMoney } from '@/lib/format';
import type { Customer, PageProps } from '@/types';

type Props = PageProps & {
    customer: Customer;
    canAnonymize?: boolean;
    canCreateService?: boolean;
    canEdit?: boolean;
    canCollectPayment?: boolean;
    canCreateTicket?: boolean;
    canResyncServices?: boolean;
    canActivateServices?: boolean;
    canSuspendServices?: boolean;
    canPauseServices?: boolean;
    canTerminateServices?: boolean;
    canDisconnectSessions?: boolean;
    canForceResumeServices?: boolean;
    canManageEquipment?: boolean;
};

export default function CustomerShow({
    customer,
    canAnonymize = false,
    canCreateService = false,
    canEdit = false,
    canCollectPayment = false,
    canCreateTicket = false,
    canResyncServices = false,
    canActivateServices = false,
    canSuspendServices = false,
    canPauseServices = false,
    canTerminateServices = false,
    canDisconnectSessions = false,
    canForceResumeServices = false,
    canManageEquipment = false,
}: Props) {
    const fullName = `${customer.first_name} ${customer.last_name ?? ''}`.trim();
    const nextExpiry =
        customer.services
            .map((service) => service.expires_at)
            .filter((expiresAt): expiresAt is string => expiresAt !== null)
            .sort((left, right) => new Date(left).getTime() - new Date(right).getTime())[0] ?? null;
    const documentForm = useForm<{ file: File | null; document_type: string; retention_until: string }>({
        file: null,
        document_type: 'contract',
        retention_until: '',
    });
    const submitDocument = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        documentForm.post('/customers/' + customer.public_id + '/documents', {
            forceFormData: true,
            onSuccess: () => documentForm.reset(),
        });
    };

    return (
        <AppLayout>
            <Head title={fullName} />
            <Link
                href="/customers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} />
                Back to customers
            </Link>
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div className="flex items-center gap-4">
                    <div className="grid size-14 place-items-center rounded-2xl bg-brand text-lg font-bold text-white">
                        {customer.first_name.slice(0, 1)}
                        {customer.last_name?.slice(0, 1) ?? ''}
                    </div>
                    <div>
                        <div className="flex items-center gap-3">
                            <h1 className="page-title">{fullName}</h1>
                            <StatusBadge status={customer.status} />
                        </div>
                        <p className="mt-1 text-sm text-muted">
                            {customer.code} · {customer.zone?.name ?? 'Zone unassigned'}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <a href={`tel:${customer.phone}`} className="button-secondary">
                        <Phone size={16} />
                        Call
                    </a>
                    <a href={`https://wa.me/${customer.phone.replace(/\D/g, '')}`} className="button-secondary">
                        <MessageCircle size={16} />
                        WhatsApp
                    </a>
                    {canCollectPayment && (
                        <Link href={`/customers/${customer.public_id}/payments/create`} className="button-primary">
                            <CreditCard size={16} />
                            Take payment
                        </Link>
                    )}
                    {canCollectPayment && customer.services.some((service) => service.status !== 'terminated') && (
                        <Link href={`/customers/${customer.public_id}/renew`} className="button-secondary">
                            <RefreshCw size={16} />
                            Renew
                        </Link>
                    )}
                    {canCreateTicket && (
                        <Link href={`/customers/${customer.public_id}/tickets/create`} className="button-secondary">
                            <MessageSquare size={16} />
                            Open ticket
                        </Link>
                    )}
                    {canAnonymize && !customer.anonymized_at && (
                        <button
                            type="button"
                            className="button-secondary text-coral"
                            onClick={() => {
                                if (
                                    window.confirm('Anonymize this customer record? Personal data cannot be recovered.')
                                ) {
                                    router.post(`/customers/${customer.public_id}/anonymize`);
                                }
                            }}
                        >
                            <ShieldOff size={16} />
                            Anonymize
                        </button>
                    )}
                </div>
            </div>
            <div className="mt-8 grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <div className="space-y-6">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Balance</p>
                            <p
                                className={`mt-3 font-display text-2xl font-semibold ${customer.balance_amount > 0 ? 'text-coral' : ''}`}
                            >
                                {formatMoney(customer.balance_amount, customer.balance_currency)}
                            </p>
                            <p className="mt-1 text-xs text-muted">
                                {customer.balance_amount > 0 ? 'Amount owing' : 'Account balance'}
                            </p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Services</p>
                            <p className="mt-3 font-display text-2xl font-semibold">{customer.services.length}</p>
                            <p className="mt-1 text-xs text-muted">Across this customer</p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Contact</p>
                            <p className="mt-3 truncate text-sm font-semibold">{customer.phone}</p>
                            <p className="mt-1 truncate text-xs text-muted">{customer.email ?? 'No email on file'}</p>
                        </div>
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Expiry</p>
                            <p
                                className={`mt-3 text-sm font-semibold ${nextExpiry !== null && new Date(nextExpiry) < new Date() ? 'text-coral' : ''}`}
                            >
                                {formatExpiryCountdown(nextExpiry)}
                            </p>
                            <p className="mt-1 text-xs text-muted">Earliest service expiry</p>
                        </div>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div>
                                <h2 className="section-title">Services</h2>
                                <p className="mt-1 text-sm text-muted">Every connection belonging to this customer.</p>
                            </div>
                            {canCreateService && (
                                <Link
                                    href={`/customers/${customer.public_id}/services/create`}
                                    className="button-secondary"
                                >
                                    <Plus size={16} />
                                    Add service
                                </Link>
                            )}
                        </div>
                        <div className="divide-y divide-line">
                            {customer.services.map((service) => (
                                <div key={service.public_id} className="p-6">
                                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                        <div className="flex items-start gap-3">
                                            <div className="grid size-10 place-items-center rounded-xl bg-brand-soft text-brand">
                                                <Wifi size={18} />
                                            </div>
                                            <div>
                                                <p className="font-semibold">{service.plan.name}</p>
                                                <p className="mt-1 text-sm text-muted">
                                                    {service.username} · {service.plan.download_kbps / 1000} Mbps down /{' '}
                                                    {service.plan.upload_kbps / 1000} Mbps up
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <StatusBadge status={service.status} />
                                            <StatusBadge status={service.network_state} />
                                        </div>
                                    </div>
                                    <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <p className="text-xs text-muted">Expires</p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                                <CalendarDays size={14} className="text-muted" />
                                                {formatDate(service.expires_at)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">Provisioning</p>
                                            <p className="mt-1 text-sm font-semibold capitalize">
                                                {(service.provisioning_mode ?? 'manual').replace('_', ' ')}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {service.router?.name ?? 'No router assigned'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">Session</p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                                <span
                                                    className={`size-2 rounded-full ${service.session ? 'bg-emerald-500' : 'bg-slate-300'}`}
                                                />
                                                {service.session ? 'Online' : 'Offline'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted">
                                                {service.session?.framed_ip ??
                                                    service.session?.nasname ??
                                                    'No active session'}
                                            </p>
                                            {service.session && (
                                                <p className="mt-1 text-xs text-muted">
                                                    Uptime{' '}
                                                    {formatDuration(
                                                        service.session.started_at,
                                                        service.session.last_seen_at,
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">Quota</p>
                                            {service.usage.quota_bytes > 0 ? (
                                                <>
                                                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-sand">
                                                        <div
                                                            className="h-full rounded-full bg-brand"
                                                            style={{
                                                                width: `${Math.min(100, (service.usage.used_bytes / service.usage.quota_bytes) * 100)}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {formatBytes(service.usage.used_bytes)} of{' '}
                                                        {formatBytes(service.usage.quota_bytes)}
                                                    </p>
                                                </>
                                            ) : (
                                                <p className="mt-1 text-sm text-muted">No quota set</p>
                                            )}
                                        </div>
                                        <div className="lg:col-span-4">
                                            <p className="text-xs text-muted">Equipment</p>
                                            {service.equipment.length > 0 ? (
                                                <div className="mt-2 flex flex-wrap gap-2">
                                                    {service.equipment.map((unit) => (
                                                        <div
                                                            key={unit.serial_number}
                                                            className="inline-flex items-center gap-2 rounded-full bg-sand px-3 py-1.5 text-xs font-semibold"
                                                        >
                                                            {unit.item?.name ?? 'Serialized equipment'} ·{' '}
                                                            {unit.serial_number}
                                                            <span className="font-normal text-muted">
                                                                · Assigned {formatDate(unit.assigned_at)}
                                                            </span>
                                                            {canManageEquipment && (
                                                                <button
                                                                    type="button"
                                                                    className="font-semibold text-coral hover:underline"
                                                                    onClick={() =>
                                                                        window.confirm(
                                                                            `Mark ${unit.serial_number} as returned?`,
                                                                        ) &&
                                                                        router.post(
                                                                            `/services/${service.public_id}/equipment/${unit.id}/return`,
                                                                        )
                                                                    }
                                                                >
                                                                    Return
                                                                </button>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-1 text-sm text-muted">No equipment assigned.</p>
                                            )}
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3 sm:justify-end">
                                            {service.status === 'pending' && canActivateServices && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    onClick={() =>
                                                        window.confirm('Activate this service?') &&
                                                        router.post(`/services/${service.public_id}/activate`)
                                                    }
                                                >
                                                    <Play size={14} /> Activate
                                                </button>
                                            )}
                                            {service.status === 'active' && canSuspendServices && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                    onClick={() =>
                                                        window.confirm('Suspend this service?') &&
                                                        router.post(`/services/${service.public_id}/suspend`, {
                                                            reason: 'manual_operator',
                                                        })
                                                    }
                                                >
                                                    <Pause size={14} /> Suspend
                                                </button>
                                            )}
                                            {service.status === 'active' && canPauseServices && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700"
                                                    onClick={() =>
                                                        window.confirm('Pause this service?') &&
                                                        router.post(`/services/${service.public_id}/pause`, {
                                                            reason: 'customer_requested',
                                                        })
                                                    }
                                                >
                                                    <Pause size={14} /> Pause
                                                </button>
                                            )}
                                            {((service.status === 'suspended' &&
                                                canActivateServices &&
                                                (service.suspension_reason === 'auto_overdue' ||
                                                    canForceResumeServices)) ||
                                                (service.status === 'paused' && canActivateServices)) && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    onClick={() =>
                                                        window.confirm('Reactivate this service?') &&
                                                        router.post(`/services/${service.public_id}/resume`)
                                                    }
                                                >
                                                    <Play size={14} /> Resume
                                                </button>
                                            )}
                                            {canTerminateServices && service.status !== 'terminated' && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                    onClick={() =>
                                                        window.confirm(
                                                            'Terminate this service? Equipment will be marked for recovery.',
                                                        ) &&
                                                        router.post(`/services/${service.public_id}/terminate`, {
                                                            reason: 'manual_operator',
                                                        })
                                                    }
                                                >
                                                    <ShieldOff size={14} /> Terminate
                                                </button>
                                            )}
                                            {canResyncServices && service.status !== 'terminated' && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                                    onClick={() => router.post(`/services/${service.public_id}/resync`)}
                                                >
                                                    <RefreshCw size={14} /> Re-sync
                                                </button>
                                            )}
                                            {canDisconnectSessions && service.session && (
                                                <button
                                                    type="button"
                                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-coral"
                                                    onClick={() =>
                                                        window.confirm('Disconnect the current network session?') &&
                                                        router.post(`/services/${service.public_id}/disconnect-session`)
                                                    }
                                                >
                                                    <WifiOff size={14} /> Disconnect
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {customer.services.length === 0 && (
                                <div className="p-12 text-center">
                                    <Wifi className="mx-auto text-muted" size={28} />
                                    <p className="mt-3 font-semibold">No services yet</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
                <aside className="space-y-6">
                    <div className="card p-6">
                        <div className="flex items-center justify-between">
                            <h2 className="section-title">Customer details</h2>
                            {canEdit && (
                                <Link
                                    href={`/customers/${customer.public_id}/edit`}
                                    className="inline-flex items-center gap-1.5 text-sm font-semibold text-brand"
                                >
                                    <Edit3 size={14} /> Edit
                                </Link>
                            )}
                        </div>
                        <dl className="mt-5 space-y-4">
                            <div>
                                <dt className="text-xs text-muted">Phone</dt>
                                <dd className="mt-1 text-sm font-medium">{customer.phone}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">Email</dt>
                                <dd className="mt-1 text-sm font-medium">{customer.email ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">Address</dt>
                                <dd className="mt-1 flex items-start gap-1.5 text-sm font-medium">
                                    <MapPin size={15} className="mt-0.5 shrink-0 text-muted" />
                                    {customer.address ?? 'No address on file'}
                                </dd>
                            </div>
                            {customer.latitude !== null && customer.longitude !== null && (
                                <div>
                                    <dt className="text-xs text-muted">Coordinates</dt>
                                    <dd className="mt-1 flex items-center justify-between gap-3 text-sm font-medium">
                                        <span>
                                            {customer.latitude.toFixed(7)}, {customer.longitude.toFixed(7)}
                                        </span>
                                        <a
                                            href={
                                                'https://www.openstreetmap.org/?mlat=' +
                                                customer.latitude +
                                                '&mlon=' +
                                                customer.longitude
                                            }
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-brand hover:underline"
                                        >
                                            Open map
                                        </a>
                                    </dd>
                                    <div className="mt-3">
                                        <MapView latitude={customer.latitude} longitude={customer.longitude} />
                                    </div>
                                </div>
                            )}
                        </dl>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center gap-2 border-b border-line px-6 py-5">
                            <FileText size={18} className="text-brand" />
                            <div>
                                <h2 className="section-title">Documents</h2>
                                <p className="mt-1 text-sm text-muted">Private files attached to this customer.</p>
                            </div>
                        </div>
                        {canEdit && (
                            <form onSubmit={submitDocument} className="space-y-3 border-b border-line px-6 py-5">
                                <label>
                                    <span className="field-label">Add PDF or image</span>
                                    <input
                                        type="file"
                                        accept="application/pdf,image/jpeg,image/png,image/webp"
                                        className="field"
                                        onChange={(event) =>
                                            documentForm.setData('file', event.target.files?.[0] ?? null)
                                        }
                                    />
                                    {documentForm.errors.file && (
                                        <p className="field-error">{documentForm.errors.file}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">Document type</span>
                                    <select
                                        className="field"
                                        value={documentForm.data.document_type}
                                        onChange={(event) => documentForm.setData('document_type', event.target.value)}
                                    >
                                        <option value="contract">Contract</option>
                                        <option value="identity">Identity</option>
                                        <option value="proof_of_address">Proof of address</option>
                                        <option value="other">Other</option>
                                    </select>
                                    {documentForm.errors.document_type && (
                                        <p className="field-error">{documentForm.errors.document_type}</p>
                                    )}
                                </label>
                                <label>
                                    <span className="field-label">Retain until (optional)</span>
                                    <input
                                        type="date"
                                        className="field"
                                        value={documentForm.data.retention_until}
                                        onChange={(event) =>
                                            documentForm.setData('retention_until', event.target.value)
                                        }
                                    />
                                    {documentForm.errors.retention_until && (
                                        <p className="field-error">{documentForm.errors.retention_until}</p>
                                    )}
                                </label>
                                <button
                                    type="submit"
                                    className="button-secondary"
                                    disabled={documentForm.processing || !documentForm.data.file}
                                >
                                    <Upload size={15} /> Upload document
                                </button>
                            </form>
                        )}
                        <div className="divide-y divide-line">
                            {customer.documents.map((document) => (
                                <div key={document.id} className="flex items-center justify-between gap-4 px-6 py-4">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-semibold">{document.filename}</p>
                                        <p className="mt-1 text-xs capitalize text-muted">
                                            {document.document_type?.replace('_', ' ') ?? 'other'} ·{' '}
                                            {document.mime_type} · {document.size_bytes} bytes ·{' '}
                                            {formatDate(document.created_at)}
                                        </p>
                                        {document.retention_until && (
                                            <p className="mt-1 text-xs text-muted">
                                                Retained until {formatDate(document.retention_until)}
                                            </p>
                                        )}
                                    </div>
                                    <a href={document.download_url} className="button-secondary shrink-0" download>
                                        <Download size={15} /> Download
                                    </a>
                                </div>
                            ))}
                            {customer.documents.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">
                                    No customer documents have been uploaded.
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div className="flex items-center gap-2">
                                <MessageSquare size={18} className="text-brand" />
                                <div>
                                    <h2 className="section-title">Support tickets</h2>
                                    <p className="mt-1 text-sm text-muted">Recent customer conversations.</p>
                                </div>
                            </div>
                            <Link
                                href={`/operations/tickets?search=${encodeURIComponent(customer.code)}`}
                                className="text-sm font-semibold text-brand"
                            >
                                View queue
                            </Link>
                        </div>
                        <div className="divide-y divide-line">
                            {customer.tickets.map((ticket) => (
                                <Link
                                    key={ticket.public_id}
                                    href={`/operations/tickets/${ticket.public_id}`}
                                    className="block px-6 py-4 hover:bg-sand/30"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <p className="text-sm font-semibold">{ticket.subject}</p>
                                            <p className="mt-1 text-xs text-muted">
                                                {ticket.number} · {ticket.priority} priority
                                            </p>
                                        </div>
                                        <StatusBadge status={ticket.status} />
                                    </div>
                                    <p className="mt-2 text-xs text-muted">Updated {formatDate(ticket.updated_at)}</p>
                                </Link>
                            ))}
                            {customer.tickets.length === 0 && (
                                <p className="px-6 py-8 text-sm text-muted">No support tickets for this customer.</p>
                            )}
                        </div>
                    </div>
                    <div className="card p-6">
                        <div className="flex items-center gap-2">
                            <CalendarDays size={18} className="text-brand" />
                            <h2 className="section-title">Timeline</h2>
                        </div>
                        <div className="mt-5 border-s border-line ps-4">
                            {customer.timeline.map((item, index) => (
                                <div
                                    key={`${item.type}-${item.created_at}-${index}`}
                                    className="relative pb-6 last:pb-0"
                                >
                                    <span
                                        className={`absolute -start-[21px] top-1 size-2 rounded-full ring-4 ${index === 0 ? 'bg-brand ring-brand-soft' : 'bg-line ring-canvas'}`}
                                    />
                                    <p className="text-sm font-semibold">{item.title}</p>
                                    <p className="mt-1 text-xs text-muted">
                                        {item.detail}
                                        {item.amount !== undefined && item.currency
                                            ? ` · ${formatMoney(item.amount, item.currency)}`
                                            : ''}
                                    </p>
                                    <p className="mt-1 text-[11px] text-muted">{formatDate(item.created_at)}</p>
                                </div>
                            ))}
                            {customer.timeline.length === 0 && (
                                <p className="text-sm text-muted">No activity recorded yet.</p>
                            )}
                        </div>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
