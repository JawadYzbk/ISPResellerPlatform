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

    return (
        <AppLayout>
            <Head title={`Edit ${customer.first_name} ${customer.last_name ?? ''}`.trim()} />
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
                                value={form.data.first_name}
                                onChange={(event) => form.setData('first_name', event.target.value)}
                            />
                            {form.errors.first_name && <p className="field-error">{form.errors.first_name}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="last_name">
                                {t('Last name')}
                            </label>
                            <input
                                id="last_name"
                                className="field"
                                value={form.data.last_name}
                                onChange={(event) => form.setData('last_name', event.target.value)}
                            />
                            {form.errors.last_name && <p className="field-error">{form.errors.last_name}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="phone">
                                {t('Phone')}
                            </label>
                            <input
                                id="phone"
                                className="field"
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                            />
                            {form.errors.phone && <p className="field-error">{form.errors.phone}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="email">
                                {t('Email')}
                            </label>
                            <input
                                id="email"
                                type="email"
                                className="field"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                            />
                            {form.errors.email && <p className="field-error">{form.errors.email}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="zone_id">
                                {t('Zone')}
                            </label>
                            <ResponsiveSelect
                                id="zone_id"
                                className="field"
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
                            {form.errors.zone_id && <p className="field-error">{form.errors.zone_id}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="address">
                                {t('Address')}
                            </label>
                            <input
                                id="address"
                                className="field"
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                            />
                            {form.errors.address && <p className="field-error">{form.errors.address}</p>}
                        </div>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                    />
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('Save changes')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
