import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CalendarClock, Receipt, Save } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney } from '@/lib/format';
import { createTranslator, enumLabel } from '@/lib/i18n';
import type { PageProps } from '@/types';

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
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({ service_id: services[0]?.public_id ?? '' });
    const selectedService = services.find((service) => service.public_id === form.data.service_id);

    return (
        <AppLayout>
            <Head title={t('customer.renew_service')} />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('customer.back_to_customer')}
            </Link>
            <div className="max-w-2xl">
                <p className="eyebrow">
                    {t('Billing')} / {customer.code}
                </p>
                <h1 className="page-title">{t('customer.renew_service')}</h1>
                <p className="page-subtitle">
                    {t('customer.renew_description')} {customer.first_name} {customer.last_name ?? ''}.{' '}
                    {t('customer.renew_paid_note')}
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
                            {t('Service')}
                        </label>
                        <ResponsiveSelect
                            id="service_id"
                            className="field"
                            value={form.data.service_id}
                            onChange={(event) => form.setData('service_id', event.target.value)}
                        >
                            {services.map((service) => (
                                <option key={service.public_id} value={service.public_id}>
                                    {service.username} / {service.plan?.name ?? t('customer.plan_unavailable')}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {form.errors.service_id && <p className="field-error" role="alert">{t(form.errors.service_id)}</p>}
                    </div>
                    {selectedService && (
                        <div className="grid gap-4 rounded-xl bg-sand/50 p-4 sm:grid-cols-3">
                            <div>
                                <p className="text-xs text-muted">{t('Status')}</p>
                                <p className="mt-1 font-semibold capitalize">
                                    {enumLabel(selectedService.status, t)}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">{t('Expires')}</p>
                                <p className="mt-1 font-semibold">{formatDate(selectedService.expires_at)}</p>
                            </div>
                            <div>
                                <p className="text-xs text-muted">{t('customer.renewal_price')}</p>
                                <p className="mt-1 font-semibold">
                                    {selectedService.price
                                        ? formatMoney(
                                              selectedService.price.amount_minor,
                                              selectedService.price.currency,
                                          )
                                        : t('customer.no_active_price')}
                                </p>
                            </div>
                        </div>
                    )}
                    {services.length === 0 && (
                        <p className="flex items-center gap-2 text-sm text-muted">
                            <Receipt size={16} /> {t('customer.no_renewable_services')}
                        </p>
                    )}
                    <div className="flex items-start gap-3 rounded-xl border border-line p-4 text-sm text-muted">
                        <CalendarClock size={18} className="mt-0.5 shrink-0 text-brand" />
                        <p>{t('customer.renew_reuse_note')}</p>
                    </div>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button
                            type="submit"
                            className="button-primary"
                            disabled={form.processing || services.length === 0 || selectedService?.price === null}
                        >
                            <Save size={16} /> {t('customer.issue_renewal_invoice')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
