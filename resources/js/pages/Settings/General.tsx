import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, CheckCircle2, ImagePlus, Save, Settings2 } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Tenant = { public_id: string; name: string; slug: string; logo_url: string | null };
type Settings = {
    locale: 'en' | 'ar' | 'fr';
    timezone: string;
    base_currency: string;
    collection_currency: string;
    date_format: string;
    time_format: string;
    rtl: boolean;
    grace_extends_period: boolean;
    notification_quiet_start: string;
    notification_quiet_end: string;
    resolved_ticket_auto_close_hours: number;
    radius_interim_interval_seconds: number;
};

type FormSettings = Settings & { name: string; logo: File | null };

type PaymentStatus = { ready: boolean; status: string; detail: string };
type Payments = { cash: PaymentStatus; whish: PaymentStatus; stripe: PaymentStatus };
type SetupSignals = {
    logo_ready: boolean;
    currency: { base: string; collection: string; rate_ready: boolean };
    whatsapp: { mode: 'cloud' | 'web'; configured: boolean; status: string };
};

type Props = {
    tenant: Tenant;
    settings: Settings;
    currencies: CurrencyOption[];
    payments: Payments;
    setup: SetupSignals;
};

function SetupSignal({
    label,
    detail,
    ready,
    href,
    t,
}: {
    label: string;
    detail: string;
    ready: boolean;
    href: string;
    t: (key: string) => string;
}) {
    return (
        <div className="rounded-xl border border-line bg-sand/50 p-4">
            <div className="flex items-start gap-3">
                {ready ? (
                    <CheckCircle2 size={18} className="mt-0.5 shrink-0 text-emerald-700" />
                ) : (
                    <AlertCircle size={18} className="mt-0.5 shrink-0 text-amber-700" />
                )}
                <div className="min-w-0">
                    <p className="text-sm font-semibold">{label}</p>
                    <p className="mt-1 text-xs text-muted">{detail}</p>
                </div>
            </div>
            <Link href={href} className="mt-3 inline-flex text-xs font-semibold text-brand">
                {ready ? t('Review setup') : t('Complete setup')} →
            </Link>
        </div>
    );
}

export default function GeneralSettings({ tenant, settings, currencies, payments, setup }: Props) {
    const { app } = usePage<PageProps>().props;
    const t = createTranslator(app.locale);
    const form = useForm<FormSettings>({ ...settings, name: tenant.name, logo: null });
    const whatsappReady = setup.whatsapp.mode === 'web' ? setup.whatsapp.status === 'ready' : setup.whatsapp.configured;
    const whatsappDetail = whatsappReady
        ? setup.whatsapp.mode === 'web'
            ? t('The Web.js bridge is paired and ready for controlled delivery.')
            : t('Cloud API credentials are present.')
        : setup.whatsapp.status === 'disabled'
          ? t('WhatsApp Web.js is disabled.')
          : t('Provider credentials or pairing are still required.');
    const currencyDetail =
        setup.currency.base === setup.currency.collection
            ? `${setup.currency.base} ${t('is used for billing and collection.')}`
            : setup.currency.rate_ready
              ? `${t('Effective')} ${setup.currency.base}/${setup.currency.collection} ${t('conversion is available.')}`
              : `${t('Add an effective')} ${setup.currency.base}/${setup.currency.collection} ${t('rate before collecting.')}`;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'put' }));
        form.post('/settings/general', {
            forceFormData: form.data.logo !== null,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout>
            <Head title={t('Workspace settings')} />
            <Link
                href="/dashboard"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to dashboard')}
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">
                    {t('Administration')} · {tenant.slug}
                </p>
                <h1 className="page-title">{t('Workspace settings')}</h1>
                <p className="page-subtitle">
                    {t('Control tenant identity, business time, currency, and automation defaults.')}
                </p>
                <div className="mt-5 flex gap-2">
                    <Link href="/settings/setup" className="button-primary">
                        {t('First-time setup')}
                    </Link>
                    <Link href="/settings/general" className="button-secondary">
                        {t('General')}
                    </Link>
                    <Link href="/settings/readiness" className="button-secondary">
                        {t('Pilot readiness')}
                    </Link>
                    <Link href="/settings/users" className="button-secondary">
                        {t('Users and invitations')}
                    </Link>
                    <Link href="/settings/locations" className="button-secondary">
                        {t('Branches and zones')}
                    </Link>
                    <Link href="/settings/whatsapp" className="button-secondary">
                        WhatsApp setup
                    </Link>
                    <Link href="/settings/ticket-responses" className="button-secondary">
                        Ticket responses
                    </Link>
                </div>
                <section id="payment-channels" className="card mt-6 p-5">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h2 className="section-title">{t('Payment channels')}</h2>
                            <p className="mt-1 text-sm text-muted">{t('What the workspace can offer today.')}</p>
                        </div>
                        <Link href="/billing/payments" className="button-quiet">
                            {t('Payment ledger')}
                        </Link>
                    </div>
                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        <div className="rounded-xl border border-line bg-sand/50 p-4">
                            <p className="text-sm font-semibold">{t('Cash collection')}</p>
                            <p className="mt-1 text-xs text-muted">{payments.cash.detail}</p>
                            <Link href="/billing/shifts" className="mt-3 inline-flex text-xs font-semibold text-brand">
                                {t('Open cash shifts')} →
                            </Link>
                        </div>
                        <div className="rounded-xl border border-line bg-sand/50 p-4">
                            <p className="text-sm font-semibold">{t('Whish Pay QR')}</p>
                            <p
                                className={`mt-1 text-xs ${payments.whish.ready ? 'text-emerald-700' : 'text-amber-700'}`}
                            >
                                {payments.whish.detail}
                            </p>
                            <Link
                                href={payments.whish.ready ? '/customers' : '/settings/integrations#whish'}
                                className="mt-3 inline-flex text-xs font-semibold text-brand"
                            >
                                {payments.whish.ready ? t('Open customer collection') : t('Open readiness checklist')} →
                            </Link>
                        </div>
                        <div className="rounded-xl border border-line bg-sand/50 p-4">
                            <p className="text-sm font-semibold">{t('Stripe portal')}</p>
                            <p
                                className={`mt-1 text-xs ${payments.stripe.ready ? 'text-emerald-700' : 'text-amber-700'}`}
                            >
                                {payments.stripe.detail}
                            </p>
                            <Link
                                href={payments.stripe.ready ? '#payment-channels' : '/settings/integrations#stripe'}
                                className="mt-3 inline-flex text-xs font-semibold text-brand"
                            >
                                {payments.stripe.ready ? t('Review customer portal') : t('Open readiness checklist')} →
                            </Link>
                        </div>
                    </div>
                </section>
                <section className="card mt-6 p-5">
                    <div className="flex items-center justify-between gap-4">
                        <div>
                            <h2 className="section-title">{t('Setup signals')}</h2>
                            <p className="mt-1 text-sm text-muted">
                                {t('The remaining workspace checks are visible here.')}
                            </p>
                        </div>
                        <Link href="/settings/integrations" className="button-quiet">
                            {t('Open integration settings')}
                        </Link>
                    </div>
                    <div className="mt-5 grid gap-4 md:grid-cols-3">
                        <SetupSignal
                            t={t}
                            label={t('Tenant branding')}
                            detail={
                                setup.logo_ready
                                    ? t('Logo uploaded for staff and customer surfaces.')
                                    : t('Upload a tenant logo for staff and customer surfaces.')
                            }
                            ready={setup.logo_ready}
                            href="#workspace-identity"
                        />
                        <SetupSignal
                            t={t}
                            label={t('Currencies and FX')}
                            detail={currencyDetail}
                            ready={setup.currency.rate_ready}
                            href={setup.currency.rate_ready ? '#money-display' : '/billing/exchange-rates'}
                        />
                        <SetupSignal
                            t={t}
                            label={t('WhatsApp delivery')}
                            detail={whatsappDetail}
                            ready={whatsappReady}
                            href="/settings/whatsapp"
                        />
                    </div>
                </section>
                <form onSubmit={submit} className="card mt-8 space-y-8 p-6">
                    <section id="workspace-identity">
                        <div className="flex items-center gap-2">
                            <Settings2 size={18} className="text-brand" />
                            <h2 className="section-title">{t('Workspace identity')}</h2>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('Workspace name')}</span>
                                <input
                                    className="field"
                                    value={form.data.name}
                                    onChange={(event) => form.setData('name', event.target.value)}
                                />
                                {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                            </label>
                            <label className="sm:col-span-2">
                                <span className="field-label">{t('Tenant logo')}</span>
                                <div className="mt-2 flex flex-wrap items-center gap-4">
                                    <div className="grid size-16 place-items-center overflow-hidden rounded-2xl bg-brand-soft text-brand">
                                        {tenant.logo_url ? (
                                            <img
                                                src={tenant.logo_url}
                                                alt={`${tenant.name} logo`}
                                                className="size-full object-cover"
                                            />
                                        ) : (
                                            <ImagePlus size={22} />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <input
                                            className="field file:me-3 file:rounded-md file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(event) => form.setData('logo', event.target.files?.[0] ?? null)}
                                        />
                                        <p className="mt-1 text-xs text-muted">
                                            JPG, PNG, or WebP up to 2 MB. It appears in the staff shell and customer
                                            portal.
                                        </p>
                                        {form.errors.logo && <p className="field-error">{form.errors.logo}</p>}
                                    </div>
                                </div>
                            </label>
                            <label>
                                <span className="field-label">{t('Tenant slug')}</span>
                                <input className="field bg-sand" value={tenant.slug} disabled />
                                <p className="mt-1 text-xs text-muted">
                                    {t('The public portal URL is not changed here.')}
                                </p>
                            </label>
                            <label>
                                <span className="field-label">{t('Locale')}</span>
                                <ResponsiveSelect
                                    id="workspace-locale"
                                    className="field"
                                    value={form.data.locale}
                                    onChange={(event) =>
                                        form.setData('locale', event.target.value as Settings['locale'])
                                    }
                                >
                                    <option value="en">{t('English')}</option>
                                    <option value="ar">{t('Arabic')}</option>
                                    <option value="fr">{t('French')}</option>
                                </ResponsiveSelect>
                            </label>
                            <label>
                                <span className="field-label">{t('Timezone')}</span>
                                <input
                                    className="field"
                                    value={form.data.timezone}
                                    onChange={(event) => form.setData('timezone', event.target.value)}
                                    placeholder="Asia/Beirut"
                                />
                                {form.errors.timezone && <p className="field-error">{form.errors.timezone}</p>}
                            </label>
                        </div>
                    </section>
                    <section id="money-display" className="border-t border-line pt-7">
                        <h2 className="section-title">{t('Money and display')}</h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('Base currency')}</span>
                                <CurrencyCombobox
                                    id="base_currency"
                                    aria-label={t('Base currency')}
                                    currencies={currencies}
                                    value={form.data.base_currency}
                                    onChange={(value) => form.setData('base_currency', value)}
                                />
                                {form.errors.base_currency && (
                                    <p className="field-error">{form.errors.base_currency}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Collection currency')}</span>
                                <CurrencyCombobox
                                    id="collection_currency"
                                    aria-label={t('Collection currency')}
                                    currencies={currencies}
                                    value={form.data.collection_currency}
                                    onChange={(value) => form.setData('collection_currency', value)}
                                />
                                {form.errors.collection_currency && (
                                    <p className="field-error">{form.errors.collection_currency}</p>
                                )}
                            </label>
                            <label>
                                <span className="field-label">{t('Date format')}</span>
                                <input
                                    className="field"
                                    value={form.data.date_format}
                                    onChange={(event) => form.setData('date_format', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">{t('Time format')}</span>
                                <input
                                    className="field"
                                    value={form.data.time_format}
                                    onChange={(event) => form.setData('time_format', event.target.value)}
                                />
                            </label>
                        </div>
                        <label className="mt-5 flex items-center gap-3 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={form.data.rtl}
                                onChange={(event) => form.setData('rtl', event.target.checked)}
                            />
                            {t('Prefer right-to-left layout for this workspace')}
                        </label>
                    </section>
                    <section className="border-t border-line pt-7">
                        <h2 className="section-title">{t('Automation windows')}</h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">{t('Notification quiet start')}</span>
                                <input
                                    className="field"
                                    type="time"
                                    value={form.data.notification_quiet_start}
                                    onChange={(event) => form.setData('notification_quiet_start', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">{t('Notification quiet end')}</span>
                                <input
                                    className="field"
                                    type="time"
                                    value={form.data.notification_quiet_end}
                                    onChange={(event) => form.setData('notification_quiet_end', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Resolved ticket auto-close (hours)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={720}
                                    value={form.data.resolved_ticket_auto_close_hours}
                                    onChange={(event) =>
                                        form.setData('resolved_ticket_auto_close_hours', Number(event.target.value))
                                    }
                                />
                            </label>
                            <label>
                                <span className="field-label">RADIUS interim interval (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={30}
                                    max={3600}
                                    value={form.data.radius_interim_interval_seconds}
                                    onChange={(event) =>
                                        form.setData('radius_interim_interval_seconds', Number(event.target.value))
                                    }
                                />
                            </label>
                        </div>
                        <label className="mt-5 flex items-center gap-3 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={form.data.grace_extends_period}
                                onChange={(event) => form.setData('grace_extends_period', event.target.checked)}
                            />
                            Extend a billing period from its expiry when grace renewal is enabled
                        </label>
                    </section>
                    <div className="flex justify-end border-t border-line pt-5">
                        <button id="save-workspace-settings" className="button-primary" disabled={form.processing}>
                            <Save size={16} /> {t('Save settings')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
