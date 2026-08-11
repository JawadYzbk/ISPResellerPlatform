import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Receipt, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';

type Customer = {
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
    balance_currency: string;
};
type Service = {
    public_id: string;
    username: string;
    status: string;
    expires_at: string | null;
    plan: { name: string; duration_days: number } | null;
    price: { amount_minor: number; currency: string } | null;
};
type Props = { customer: Customer; services: Service[] };

export default function CustomerRenew({ customer, services }: Props) {
    const form = useForm({ service_id: services[0]?.public_id ?? '' });
    const selectedService = services.find((service) => service.public_id === form.data.service_id);

    return (
        <AppLayout>
            <Head title="Renew service" />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to customer
            </Link>
            <div className="max-w-2xl">
                <p className="eyebrow">Billing / {customer.code}</p>
                <h1 className="page-title">Renew service</h1>
                <p className="page-subtitle">
                    Issue one renewal invoice for {customer.first_name} {customer.last_name ?? ''}. The service period
                    extends only after the invoice is paid.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/customers/${customer.public_id}/renew`);
                    }}
                    className="card mt-8 space-y-6 p-6"
                >
                    <div>
                        <label className="field-label" htmlFor="service_id">
                            Service
                        </label>
                        <ResponsiveSelect
                            id="service_id"
                            className="field"
                            value={form.data.service_id}
                            onChange={(event) => form.setData('service_id', event.target.value)}
                        >
                            {services.map((service) => (
                                <option key={service.public_id} value={service.public_id}>
                                    {service.username} / {service.plan?.name ?? 'Plan unavailable'}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {form.errors.service_id && <p className="field-error">{form.errors.service_id}</p>}
                    </div>
                    {selectedService && (
                        <div className="grid gap-4 rounded-xl bg-sand/50 p-4 sm:grid-cols-3">
                            <div>
                                <p className="text-xs text-muted">Status</p>
                                <p className="mt-1 font-semibold capitalize">
                                    {selectedService.status.replace('_', ' ')}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Expires</p>
                                <p className="mt-1 font-semibold">{formatDate(selectedService.expires_at)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">Renewal price</p>
                                <p className="mt-1 font-semibold">
                                    {selectedService.price
                                        ? formatMoney(
                                              selectedService.price.amount_minor,
                                              selectedService.price.currency,
                                          )
                                        : 'No active price'}
                                </p>
                            </div>
                        </div>
                    )}
                    {services.length === 0 && (
                        <p className="flex items-center gap-2 text-sm text-muted">
                            <Receipt size={16} /> No renewable services are available for this customer.
                        </p>
                    )}
                    <div className="flex items-start gap-3 rounded-xl border border-line p-4 text-sm text-muted">
                        <CalendarClock size={18} className="mt-0.5 shrink-0 text-brand" />
                        <p>
                            Open invoices are reused when this action is repeated. Collect the issued invoice from the
                            payment screen to extend the service period.
                        </p>
                    </div>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            Cancel
                        </Link>
                        <button
                            className="button-primary"
                            disabled={form.processing || services.length === 0 || selectedService?.price === null}
                        >
                            <Save size={16} /> Issue renewal invoice
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
