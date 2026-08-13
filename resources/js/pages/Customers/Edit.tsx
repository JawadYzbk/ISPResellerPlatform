import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import CustomerLocationFields from '@/components/CustomerLocationFields';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { Customer, PageProps, Zone } from '@/types';

type Props = {
    customer: Pick<Customer, 'public_id' | 'code' | 'first_name' | 'last_name' | 'phone' | 'email' | 'address'> & {
        zone_id: number | null;
        latitude: number | null;
        longitude: number | null;
    };
    zones: Zone[];
};

export default function CustomersEdit({ customer, zones }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        first_name: customer.first_name,
        last_name: customer.last_name ?? '',
        phone: customer.phone,
        email: customer.email ?? '',
        zone_id: customer.zone_id?.toString() ?? '',
        address: customer.address ?? '',
        latitude: customer.latitude?.toString() ?? '',
        longitude: customer.longitude?.toString() ?? '',
    });
    const fieldA11y = (name: keyof typeof form.data) => ({
        'aria-invalid': Boolean(form.errors[name]),
        'aria-describedby': form.errors[name] ? `${name}-error` : undefined,
    });
    const fieldError = (name: keyof typeof form.data) =>
        form.errors[name] ? (
            <p id={`${name}-error`} className="field-error" role="alert">
                {t(form.errors[name])}
            </p>
        ) : null;

    return (
        <AppLayout>
            <Head title={`${t('Edit customer')}: ${customer.first_name} ${customer.last_name ?? ''}`.trim()} />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to customer')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">
                    {t('Subscriber CRM')} · {customer.code}
                </p>
                <h1 className="page-title">{t('Edit customer')}</h1>
                <p className="page-subtitle">{t('customers.edit_subtitle')}</p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(`/customers/${customer.public_id}`);
                    }}
                    className="card mt-8 space-y-6 p-6"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label className="field-label" htmlFor="first_name">
                                {t('First name')}
                            </label>
                            <input
                                id="first_name"
                                className="field"
                                required
                                {...fieldA11y('first_name')}
                                value={form.data.first_name}
                                onChange={(event) => form.setData('first_name', event.target.value)}
                            />
                            {fieldError('first_name')}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="last_name">
                                {t('Last name')}
                            </label>
                            <input
                                id="last_name"
                                className="field"
                                {...fieldA11y('last_name')}
                                value={form.data.last_name}
                                onChange={(event) => form.setData('last_name', event.target.value)}
                            />
                            {fieldError('last_name')}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="phone">
                                {t('Phone')}
                            </label>
                            <input
                                id="phone"
                                className="field"
                                required
                                {...fieldA11y('phone')}
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                            />
                            {fieldError('phone')}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="email">
                                {t('Email')}
                            </label>
                            <input
                                id="email"
                                type="email"
                                className="field"
                                {...fieldA11y('email')}
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                            {fieldError('email')}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="zone_id">
                                {t('Zone')}
                            </label>
                            <ResponsiveSelect
                                id="zone_id"
                                className="field"
                                {...fieldA11y('zone_id')}
                                value={form.data.zone_id}
                                onChange={(event) => form.setData('zone_id', event.target.value)}
                            >
                                <option value="">{t('Select a zone')}</option>
                                {zones.map((zone) => (
                                    <option key={zone.id} value={zone.id}>
                                        {zone.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            {fieldError('zone_id')}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="address">
                                {t('Address')}
                            </label>
                            <input
                                id="address"
                                className="field"
                                {...fieldA11y('address')}
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                            />
                            {fieldError('address')}
                        </div>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        errors={form.errors}
                        fieldPrefix="customer-location"
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                    />
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button type="submit" className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('Save changes')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
