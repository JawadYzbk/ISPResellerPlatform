import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Save, Wifi } from 'lucide-react';
import { useMemo } from 'react';

import CustomerLocationFields from '@/components/CustomerLocationFields';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Zone = { id: number; name: string; code: string };
type Plan = {
    id: number;
    public_id: string;
    name: string;
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    amount_minor: number;
    currency: string;
};
type Router = { id: number; public_id: string; name: string };

type Props = { zones: Zone[]; canCreateService: boolean; plans: Plan[]; routers: Router[] };

const provisioningModes = [
    {
        value: 'manual',
        label: 'Manual handoff',
        description: 'Leave activation for an operator to complete outside the platform.',
    },
    { value: 'radius', label: 'FreeRADIUS', description: 'Use RADIUS authorization and live session enforcement.' },
    {
        value: 'mikrotik',
        label: 'MikroTik RouterOS',
        description: 'Queue RouterOS provisioning when the installation is completed.',
    },
    {
        value: 'external',
        label: 'External OSS / ACS',
        description: 'Send activation to the configured external network adapter.',
    },
    {
        value: 'upstream_credential',
        label: 'Upstream credential',
        description: 'Allocate an available upstream account automatically during activation.',
    },
] as const;

export default function CustomersCreate({ zones, canCreateService, plans, routers }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        zone_id: '',
        address: '',
        latitude: '',
        longitude: '',
        create_service: canCreateService && plans.length > 0,
        plan_id: plans[0]?.id.toString() ?? '',
        username: '',
        password: '',
        provisioning_mode: 'manual',
        router_id: '',
        billing_anchor_day: '',
    });
    const selectedPlan = useMemo(
        () => plans.find((plan) => plan.id.toString() === form.data.plan_id),
        [form.data.plan_id, plans],
    );
    const needsRouter = form.data.provisioning_mode === 'radius' || form.data.provisioning_mode === 'mikrotik';

    const generatePassword = () => {
        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@$%';
        const bytes = new Uint32Array(20);
        window.crypto.getRandomValues(bytes);
        form.setData('password', Array.from(bytes, (byte) => alphabet[byte % alphabet.length]).join(''));
    };

    return (
        <AppLayout>
            <Head title={t('Add customer')} />
            <Link
                href="/customers"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('customer.back_to_customers')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('customer.subscriber_crm')}</p>
                <h1 className="page-title">{t('customer.register_subscriber')}</h1>
                <p className="page-subtitle">{t('customer.register_description')}</p>
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
                        </div>
                        <div>
                            <label className="field-label" htmlFor="phone">
                                {t('Phone')}
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
                                <option value="">{t('customer.select_zone')}</option>
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
                        </div>
                    </div>
                    <CustomerLocationFields
                        latitude={form.data.latitude}
                        longitude={form.data.longitude}
                        onLatitudeChange={(value) => form.setData('latitude', value)}
                        onLongitudeChange={(value) => form.setData('longitude', value)}
                    />
                    {canCreateService && plans.length > 0 && (
                        <section className="space-y-6 border-t border-line pt-6">
                            <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-4">
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-brand"
                                    checked={form.data.create_service}
                                    onChange={(event) => form.setData('create_service', event.target.checked)}
                                />
                                <span>
                                    <span className="block font-semibold text-ink">
                                        {t('customer.create_initial_service')}
                                    </span>
                                    <span className="mt-1 block text-sm text-muted">
                                        {t('customer.create_initial_service_description')}
                                    </span>
                                </span>
                            </label>
                            {form.data.create_service && (
                                <div className="space-y-6 rounded-xl bg-sand/40 p-5">
                                    <div>
                                        <label className="field-label" htmlFor="plan_id">
                                            {t('customer.initial_plan')}
                                        </label>
                                        <ResponsiveSelect
                                            id="plan_id"
                                            className="field"
                                            value={form.data.plan_id}
                                            onChange={(event) => form.setData('plan_id', event.target.value)}
                                        >
                                            {plans.map((plan) => (
                                                <option key={plan.id} value={plan.id}>
                                                    {plan.name} / {plan.download_kbps / 1000}/{plan.upload_kbps / 1000}{' '}
                                                    Mbps / {plan.duration_days} {t('days')}
                                                </option>
                                            ))}
                                        </ResponsiveSelect>
                                        {form.errors.plan_id && <p className="field-error">{form.errors.plan_id}</p>}
                                        {selectedPlan && (
                                            <p className="mt-1 text-xs text-muted">
                                                {t('customer.plan_currency')}: {selectedPlan.currency}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <div>
                                            <label className="field-label" htmlFor="username">
                                                {t('customer.service_username')}
                                            </label>
                                            <input
                                                id="username"
                                                className="field"
                                                value={form.data.username}
                                                onChange={(event) => form.setData('username', event.target.value)}
                                            />
                                            {form.errors.username && (
                                                <p className="field-error">{form.errors.username}</p>
                                            )}
                                        </div>
                                        <div>
                                            <label className="field-label" htmlFor="password">
                                                {t('customer.service_password')}
                                            </label>
                                            <div className="flex gap-2">
                                                <input
                                                    id="password"
                                                    type="password"
                                                    className="field min-w-0 flex-1"
                                                    value={form.data.password}
                                                    onChange={(event) => form.setData('password', event.target.value)}
                                                />
                                                <button
                                                    type="button"
                                                    className="button-secondary shrink-0"
                                                    onClick={generatePassword}
                                                    title={t('customer.generate_password')}
                                                >
                                                    <KeyRound size={16} />
                                                </button>
                                            </div>
                                            {form.errors.password && (
                                                <p className="field-error">{form.errors.password}</p>
                                            )}
                                            <p className="mt-1 text-xs text-muted">{t('customer.minimum_password')}</p>
                                        </div>
                                    </div>
                                    <div>
                                        <label className="field-label" htmlFor="billing_anchor_day">
                                            {t('customer.monthly_billing_anchor')}
                                        </label>
                                        <ResponsiveSelect
                                            id="billing_anchor_day"
                                            className="field"
                                            value={form.data.billing_anchor_day}
                                            onChange={(event) => form.setData('billing_anchor_day', event.target.value)}
                                        >
                                            <option value="">{t('customer.follow_plan_duration')}</option>
                                            {Array.from({ length: 31 }, (_, index) => index + 1).map((day) => (
                                                <option key={day} value={day}>
                                                    {t('customer.day_of_month')} {day}
                                                </option>
                                            ))}
                                        </ResponsiveSelect>
                                        {form.errors.billing_anchor_day && (
                                            <p className="field-error">{form.errors.billing_anchor_day}</p>
                                        )}
                                        <p className="mt-1 text-xs text-pretty text-muted">
                                            {t('customer.billing_anchor_description')}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="field-label">{t('customer.provisioning_mode')}</p>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {provisioningModes.map((mode) => (
                                                <label
                                                    key={mode.value}
                                                    className={`cursor-pointer rounded-xl border p-4 transition ${form.data.provisioning_mode === mode.value ? 'border-brand bg-brand-soft' : 'border-line hover:border-brand/50'}`}
                                                >
                                                    <input
                                                        type="radio"
                                                        name="provisioning_mode"
                                                        value={mode.value}
                                                        checked={form.data.provisioning_mode === mode.value}
                                                        onChange={(event) => {
                                                            form.setData('provisioning_mode', event.target.value);
                                                            if (
                                                                event.target.value !== 'radius' &&
                                                                event.target.value !== 'mikrotik'
                                                            )
                                                                form.setData('router_id', '');
                                                        }}
                                                        className="sr-only"
                                                    />
                                                    <span className="font-semibold">
                                                        {t(`customer.provisioning.${mode.value}.label`)}
                                                    </span>
                                                    <span className="mt-1 block text-xs text-muted">
                                                        {t(`customer.provisioning.${mode.value}.description`)}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                        {form.errors.provisioning_mode && (
                                            <p className="field-error">{form.errors.provisioning_mode}</p>
                                        )}
                                    </div>
                                    {needsRouter && (
                                        <div>
                                            <label className="field-label" htmlFor="router_id">
                                                {t('Router')}
                                            </label>
                                            <ResponsiveSelect
                                                id="router_id"
                                                className="field"
                                                value={form.data.router_id}
                                                onChange={(event) => form.setData('router_id', event.target.value)}
                                            >
                                                <option value="">{t('customer.select_router')}</option>
                                                {routers.map((router) => (
                                                    <option key={router.id} value={router.id}>
                                                        {router.name}
                                                    </option>
                                                ))}
                                            </ResponsiveSelect>
                                            {form.errors.router_id && (
                                                <p className="field-error">{form.errors.router_id}</p>
                                            )}
                                        </div>
                                    )}
                                    <div className="flex items-start gap-3 rounded-xl bg-white/70 p-4 text-sm text-muted">
                                        <Wifi size={18} className="mt-0.5 shrink-0 text-brand" />
                                        <p>{t('customer.pending_installation_note')}</p>
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                    {canCreateService && plans.length === 0 && (
                        <p className="border-t border-line pt-5 text-sm text-muted">{t('customer.no_plans_note')}</p>
                    )}
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href="/customers" className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('customer.save_customer')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
