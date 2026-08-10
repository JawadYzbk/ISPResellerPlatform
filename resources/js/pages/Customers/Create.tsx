import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

import CustomerLocationFields from '@/components/CustomerLocationFields';
import AppLayout from '@/layouts/AppLayout';

type Zone = { id: number; name: string; code: string };

type Props = { zones: Zone[] };

export default function CustomersCreate({ zones }: Props) {
    const form = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        zone_id: '',
        address: '',
        latitude: '',
        longitude: '',
    });

    return (
        <AppLayout>
            <Head title="Add customer" />
            <Link
                href="/customers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to customers
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">Subscriber CRM</p>
                <h1 className="page-title">Add customer</h1>
                <p className="page-subtitle">
                    Create the account record first; services and payments can follow from the customer page.
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/customers');
                    }}
                    className="card mt-8 space-y-6 p-6"
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label className="field-label" htmlFor="first_name">
                                First name
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
                                Last name
                            </label>
                            <input
                                id="last_name"
                                className="field"
                                value={form.data.last_name}
                                onChange={(event) => form.setData('last_name', event.target.value)}
                            />
                        </div>
                        <div>
                            <label className="field-label" htmlFor="phone">
                                Phone
                            </label>
                            <input
                                id="phone"
                                className="field"
                                placeholder="+961 70 123 456"
                                value={form.data.phone}
                                onChange={(event) => form.setData('phone', event.target.value)}
                            />
                            {form.errors.phone && <p className="field-error">{form.errors.phone}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="email">
                                Email
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
                                Zone
                            </label>
                            <select
                                id="zone_id"
                                className="field"
                                value={form.data.zone_id}
                                onChange={(event) => form.setData('zone_id', event.target.value)}
                            >
                                <option value="">Select a zone</option>
                                {zones.map((zone) => (
                                    <option key={zone.id} value={zone.id}>
                                        {zone.name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.zone_id && <p className="field-error">{form.errors.zone_id}</p>}
                        </div>
                        <div>
                            <label className="field-label" htmlFor="address">
                                Address
                            </label>
                            <input
                                id="address"
                                className="field"
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                            />
                        </div>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                    />
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href="/customers" className="button-secondary">
                            Cancel
                        </Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> Save customer
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
