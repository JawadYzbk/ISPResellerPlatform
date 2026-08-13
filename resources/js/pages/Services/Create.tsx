import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, KeyRound, Save, Wifi } from 'lucide-react';
import { useMemo } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type CustomerSummary = { public_id: string; code: string; first_name: string; last_name: string | null };
type PlanOption = {
    id: number;
    public_id: string;
    name: string;
    download_kbps: number;
    upload_kbps: number;
    duration_days: number;
    amount_minor: number;
    currency: string;
};
type RouterOption = { id: number; public_id: string; name: string };
type Props = PageProps & { customer: CustomerSummary; plans: PlanOption[]; routers: RouterOption[] };

const modes = [
    {
        value: 'manual',
        label: 'Manual handoff',
        description: 'Record the intent for an operator to complete outside the platform.',
    },
    {
        value: 'radius',
        label: 'FreeRADIUS',
        description: 'Sync authorization data and enforce live sessions through RADIUS.',
    },
    {
        value: 'mikrotik',
        label: 'MikroTik RouterOS',
        description: 'Queue RouterOS REST provisioning and state changes.',
    },
    {
        value: 'external',
        label: 'External OSS / ACS',
        description: 'Send queued commands to the configured external network adapter.',
    },
    {
        value: 'upstream_credential',
        label: 'Upstream credential',
        description: 'Allocate an available upstream account automatically during activation.',
    },
] as const;

export default function ServicesCreate({ customer, plans, routers }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        plan_id: plans[0]?.id.toString() ?? '',
        username: `${customer.code.toLowerCase()}.service`,
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
            <Head title={t('Add service')} />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to customer')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">{t('Subscriber operations')}</p>
                <h1 className="page-title">{t('Add service')}</h1>
                <p className="page-subtitle">
                    {t('service.create_description')} {customer.first_name} {customer.last_name ?? ''}.{' '}
                    {t('service.activation_note')}
                </p>
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/customers/${customer.public_id}/services`);
                    }}
                    className="card mt-8 space-y-7 p-6"
                >
                    <div>
                        <label className="field-label" htmlFor="plan_id">
                            {t('Plan')}
                        </label>
                        <ResponsiveSelect
                            id="plan_id"
                            className="field"
                            value={form.data.plan_id}
                            onChange={(event) => form.setData('plan_id', event.target.value)}
                        >
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name} · {plan.download_kbps / 1000}/{plan.upload_kbps / 1000} Mbps ·{' '}
                                    {plan.duration_days} {t('days')}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {form.errors.plan_id && <p className="field-error">{t(form.errors.plan_id)}</p>}
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
                            {form.errors.username && <p className="field-error">{t(form.errors.username)}</p>}
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
                            {form.errors.password && <p className="field-error">{t(form.errors.password)}</p>}
                            <p className="mt-1 text-xs text-muted">{t('service.password_note')}</p>
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
                            <p className="field-error">{t(form.errors.billing_anchor_day)}</p>
                        )}
                        <p className="mt-1 text-xs text-pretty text-muted">
                            The first renewal invoice is prorated from its issue date to this day. Days 29–31 clamp to
                            shorter months.
                        </p>
                    </div>
                    <div>
                        <p className="field-label">{t('customer.provisioning_mode')}</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {modes.map((mode) => (
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
                                            if (event.target.value !== 'radius' && event.target.value !== 'mikrotik')
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
                            <p className="field-error">{t(form.errors.provisioning_mode)}</p>
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
                            {form.errors.router_id && <p className="field-error">{t(form.errors.router_id)}</p>}
                        </div>
                    )}
                    <div className="flex items-start gap-3 rounded-xl bg-sand/60 p-4 text-sm text-muted">
                        <Wifi size={18} className="mt-0.5 shrink-0 text-brand" />
                        <p>{t('service.pending_note')}</p>
                    </div>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button className="button-primary" disabled={form.processing || plans.length === 0}>
                            <Save size={16} /> {t('service.create_pending')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
