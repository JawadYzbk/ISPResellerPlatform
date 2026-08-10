import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CalendarDays, CreditCard, MapPin, MessageCircle, Phone, Plus, RefreshCw, ShieldOff, Wifi } from 'lucide-react';

import { StatusBadge } from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import type { Customer, PageProps } from '@/types';

type Props = PageProps & { customer: Customer; canAnonymize?: boolean };

export default function CustomerShow({ customer, canAnonymize = false }: Props) {
    const fullName = `${customer.first_name} ${customer.last_name ?? ''}`.trim();
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
                    <button className="button-primary">
                        <CreditCard size={16} />
                        Take payment
                    </button>
                    {canAnonymize && !customer.anonymized_at && (
                        <button
                            type="button"
                            className="button-secondary text-coral"
                            onClick={() => {
                                if (window.confirm('Anonymize this customer record? Personal data cannot be recovered.')) {
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
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="card p-5">
                            <p className="text-xs font-semibold uppercase tracking-wider text-muted">Balance</p>
                            <p className="mt-3 font-display text-2xl font-semibold">
                                {formatMoney(customer.balance_amount, customer.balance_currency)}
                            </p>
                            <p className="mt-1 text-xs text-muted">Account balance</p>
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
                    </div>
                    <div className="card overflow-hidden">
                        <div className="flex items-center justify-between border-b border-line px-6 py-5">
                            <div>
                                <h2 className="section-title">Services</h2>
                                <p className="mt-1 text-sm text-muted">Every connection belonging to this customer.</p>
                            </div>
                            <button className="button-secondary">
                                <Plus size={16} />
                                Add service
                            </button>
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
                                    <div className="mt-5 grid gap-4 border-t border-line pt-4 sm:grid-cols-3">
                                        <div>
                                            <p className="text-xs text-muted">Expires</p>
                                            <p className="mt-1 flex items-center gap-1.5 text-sm font-semibold">
                                                <CalendarDays size={14} className="text-muted" />
                                                {formatDate(service.expires_at)}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted">Provisioning</p>
                                            <p className="mt-1 text-sm font-semibold capitalize">Manual handoff</p>
                                        </div>
                                        <button className="flex items-center gap-1.5 text-sm font-semibold text-brand sm:justify-end">
                                            <RefreshCw size={14} />
                                            Re-sync service
                                        </button>
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
                            <button className="text-sm font-semibold text-brand">Edit</button>
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
                        </dl>
                    </div>
                    <div className="card p-6">
                        <div className="flex items-center gap-2">
                            <CalendarDays size={18} className="text-brand" />
                            <h2 className="section-title">Timeline</h2>
                        </div>
                        <div className="mt-5 border-s border-line ps-4">
                            <div className="relative pb-6">
                                <span className="absolute -start-[21px] top-1 size-2 rounded-full bg-brand ring-4 ring-brand-soft" />
                                <p className="text-sm font-semibold">Customer record created</p>
                                <p className="mt-1 text-xs text-muted">Account is ready for operations</p>
                            </div>
                            <div className="relative">
                                <span className="absolute -start-[21px] top-1 size-2 rounded-full bg-line" />
                                <p className="text-sm font-semibold">No payments recorded</p>
                                <p className="mt-1 text-xs text-muted">Payment activity will appear here</p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </AppLayout>
    );
}
