import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, KeyRound, Save, ShieldCheck } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Settings = {
    payment_driver: string;
    frankfurter_enabled: boolean;
    frankfurter_currency_catalog_enabled: boolean;
    frankfurter_endpoint: string;
    frankfurter_connect_timeout: number;
    frankfurter_timeout: number;
    frankfurter_quotes: string;
    whatsapp_mode: string;
    whatsapp_web_enabled: boolean;
    whatsapp_web_endpoint: string;
    whatsapp_web_client_id: string;
    whatsapp_web_webhook_url: string;
    stripe_endpoint: string;
    stripe_webhook_tolerance: number;
    stripe_timeout: number;
    whish_enabled: boolean;
    whish_environment: string;
    whish_website_url: string;
    whish_endpoint: string;
    whish_timeout: number;
    whish_success_callback_url: string;
    whish_failure_callback_url: string;
    whish_success_redirect_url: string;
    whish_failure_redirect_url: string;
};

type SecretField =
    | 'whatsapp_cloud_token'
    | 'whatsapp_phone_number_id'
    | 'whatsapp_web_token'
    | 'whatsapp_webhook_secret'
    | 'stripe_secret'
    | 'stripe_publishable_key'
    | 'stripe_webhook_secret'
    | 'whish_channel'
    | 'whish_secret';

type FormData = Settings & Record<SecretField, string> & Record<`clear_${SecretField}`, boolean>;
type Source = 'workspace' | 'environment' | 'missing';
type Props = { settings: Settings; configured: Record<SecretField, boolean>; sources: Record<SecretField, Source> };

const sourceCopy: Record<Source, string> = {
    workspace: 'Saved securely for this workspace.',
    environment: 'Using the deployment environment value.',
    missing: 'Not configured.',
};

function FieldError({ message }: { message?: string }) {
    return message ? <p className="field-error">{message}</p> : null;
}

function SecretInput({
    label,
    field,
    form,
    configured,
    source,
}: {
    label: string;
    field: SecretField;
    form: ReturnType<typeof useForm<FormData>>;
    configured: boolean;
    source: Source;
}) {
    const clearField = `clear_${field}` as `clear_${SecretField}`;

    return (
        <label className="block">
            <span className="field-label">{label}</span>
            <input
                className="field"
                type="password"
                autoComplete="new-password"
                value={form.data[field]}
                placeholder={configured ? 'Leave blank to keep the current value' : 'Paste value'}
                onChange={(event) => form.setData(field, event.target.value)}
            />
            <span className={`mt-1 block text-xs ${configured ? 'text-emerald-700' : 'text-muted'}`}>
                {configured ? <CheckCircle2 size={13} className="me-1 inline" /> : null}
                {sourceCopy[source]}
            </span>
            <label className="mt-2 inline-flex items-center gap-2 text-xs text-muted">
                <input
                    type="checkbox"
                    checked={form.data[clearField]}
                    onChange={(event) => form.setData(clearField, event.target.checked)}
                />
                Clear saved value
            </label>
            <FieldError message={form.errors[field]} />
        </label>
    );
}

export default function Integrations({ settings, configured, sources }: Props) {
    const form = useForm<FormData>({
        ...settings,
        whatsapp_cloud_token: '',
        whatsapp_phone_number_id: '',
        whatsapp_web_token: '',
        whatsapp_webhook_secret: '',
        stripe_secret: '',
        stripe_publishable_key: '',
        stripe_webhook_secret: '',
        whish_channel: '',
        whish_secret: '',
        clear_whatsapp_cloud_token: false,
        clear_whatsapp_phone_number_id: false,
        clear_whatsapp_web_token: false,
        clear_whatsapp_webhook_secret: false,
        clear_stripe_secret: false,
        clear_stripe_publishable_key: false,
        clear_stripe_webhook_secret: false,
        clear_whish_channel: false,
        clear_whish_secret: false,
    });

    const update = (field: keyof FormData, value: string | number | boolean) => {
        form.setData(field, value as never);
    };

    return (
        <AppLayout>
            <Head title="Integrations and API keys" />
            <Link
                href="/settings/setup"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> First-time setup
            </Link>
            <div className="max-w-5xl">
                <p className="eyebrow">Workspace integrations</p>
                <h1 className="page-title">Integrations and API keys</h1>
                <p className="page-subtitle">
                    Configure provider credentials for this workspace. Secrets are encrypted at rest and never sent back
                    to the browser.
                </p>

                <div className="mt-6 flex items-start gap-3 rounded-xl border border-brand/20 bg-brand-soft/40 p-4 text-sm text-slate-700">
                    <ShieldCheck size={19} className="mt-0.5 shrink-0 text-brand" />
                    <p>
                        You do not need to edit <code>.env</code> for tenant credentials. Environment values remain a
                        fallback, while values saved here apply only to this workspace.
                    </p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/settings/integrations');
                    }}
                    className="mt-6 space-y-6"
                >
                    <section className="card space-y-5 p-6">
                        <div className="flex items-center gap-3">
                            <KeyRound size={19} className="text-brand" />
                            <div>
                                <h2 className="section-title">Payment gateway</h2>
                                <p className="mt-1 text-sm text-muted">
                                    Cash is always available. Select Stripe for online card payments.
                                </p>
                            </div>
                        </div>
                        <label className="block max-w-sm">
                            <span className="field-label">Online payment driver</span>
                            <ResponsiveSelect
                                className="field"
                                value={form.data.payment_driver}
                                onChange={(event) => update('payment_driver', event.target.value)}
                            >
                                <option value="null">Cash only</option>
                                <option value="stripe">Stripe</option>
                            </ResponsiveSelect>
                            <FieldError message={form.errors.payment_driver} />
                        </label>
                    </section>

                    <section className="card space-y-5 p-6">
                        <div>
                            <h2 className="section-title">Frankfurter exchange rates</h2>
                            <p className="mt-1 text-sm text-muted">
                                Keep currency catalog and scheduled FX imports server-side.
                            </p>
                        </div>
                        <div className="grid gap-5 md:grid-cols-2">
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    checked={form.data.frankfurter_enabled}
                                    onChange={(event) => update('frankfurter_enabled', event.target.checked)}
                                />
                                Enable Frankfurter rates
                            </label>
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    checked={form.data.frankfurter_currency_catalog_enabled}
                                    onChange={(event) =>
                                        update('frankfurter_currency_catalog_enabled', event.target.checked)
                                    }
                                />
                                Refresh currency catalog
                            </label>
                            <label>
                                <span className="field-label">API endpoint</span>
                                <input
                                    className="field"
                                    value={form.data.frankfurter_endpoint}
                                    onChange={(event) => update('frankfurter_endpoint', event.target.value)}
                                />
                                <FieldError message={form.errors.frankfurter_endpoint} />
                            </label>
                            <label>
                                <span className="field-label">Quote currencies</span>
                                <input
                                    className="field"
                                    value={form.data.frankfurter_quotes}
                                    onChange={(event) => update('frankfurter_quotes', event.target.value)}
                                    placeholder="LBP,USD,EUR"
                                />
                                <p className="mt-1 text-xs text-muted">Three-letter codes separated by commas.</p>
                                <FieldError message={form.errors.frankfurter_quotes} />
                            </label>
                            <label>
                                <span className="field-label">Connect timeout (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={30}
                                    value={form.data.frankfurter_connect_timeout}
                                    onChange={(event) =>
                                        update('frankfurter_connect_timeout', Number(event.target.value))
                                    }
                                />
                            </label>
                            <label>
                                <span className="field-label">Request timeout (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={120}
                                    value={form.data.frankfurter_timeout}
                                    onChange={(event) => update('frankfurter_timeout', Number(event.target.value))}
                                />
                            </label>
                        </div>
                    </section>

                    <section className="card space-y-5 p-6">
                        <div>
                            <h2 className="section-title">WhatsApp delivery</h2>
                            <p className="mt-1 text-sm text-muted">
                                Cloud API and the private Web.js bridge use separate credentials.
                            </p>
                        </div>
                        <label className="block max-w-sm">
                            <span className="field-label">Provider mode</span>
                            <ResponsiveSelect
                                className="field"
                                value={form.data.whatsapp_mode}
                                onChange={(event) => update('whatsapp_mode', event.target.value)}
                            >
                                <option value="cloud">WhatsApp Cloud API</option>
                                <option value="web">WhatsApp Web.js bridge</option>
                            </ResponsiveSelect>
                        </label>
                        <div className="grid gap-5 md:grid-cols-2">
                            <SecretInput
                                label="Cloud API token"
                                field="whatsapp_cloud_token"
                                form={form}
                                configured={configured.whatsapp_cloud_token}
                                source={sources.whatsapp_cloud_token}
                            />
                            <SecretInput
                                label="Cloud phone number ID"
                                field="whatsapp_phone_number_id"
                                form={form}
                                configured={configured.whatsapp_phone_number_id}
                                source={sources.whatsapp_phone_number_id}
                            />
                            <label className="flex items-center gap-3 text-sm font-medium">
                                <input
                                    type="checkbox"
                                    checked={form.data.whatsapp_web_enabled}
                                    onChange={(event) => update('whatsapp_web_enabled', event.target.checked)}
                                />
                                Enable private Web.js bridge
                            </label>
                            <label>
                                <span className="field-label">Bridge endpoint</span>
                                <input
                                    className="field"
                                    value={form.data.whatsapp_web_endpoint}
                                    onChange={(event) => update('whatsapp_web_endpoint', event.target.value)}
                                    placeholder="http://whatsapp-web:3001"
                                />
                            </label>
                            <SecretInput
                                label="Bridge token"
                                field="whatsapp_web_token"
                                form={form}
                                configured={configured.whatsapp_web_token}
                                source={sources.whatsapp_web_token}
                            />
                            <label>
                                <span className="field-label">Bridge client ID</span>
                                <input
                                    className="field"
                                    value={form.data.whatsapp_web_client_id}
                                    onChange={(event) => update('whatsapp_web_client_id', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Signed webhook URL</span>
                                <input
                                    className="field"
                                    value={form.data.whatsapp_web_webhook_url}
                                    onChange={(event) => update('whatsapp_web_webhook_url', event.target.value)}
                                />
                            </label>
                            <SecretInput
                                label="Signed webhook secret"
                                field="whatsapp_webhook_secret"
                                form={form}
                                configured={configured.whatsapp_webhook_secret}
                                source={sources.whatsapp_webhook_secret}
                            />
                        </div>
                        <Link href="/settings/whatsapp" className="button-secondary inline-flex">
                            Open QR pairing
                        </Link>
                    </section>

                    <section id="stripe" className="card space-y-5 p-6">
                        <div>
                            <h2 className="section-title">Stripe</h2>
                            <p className="mt-1 text-sm text-muted">
                                Payment intents are settled only after a verified webhook.
                            </p>
                        </div>
                        <div className="grid gap-5 md:grid-cols-2">
                            <SecretInput
                                label="Secret key"
                                field="stripe_secret"
                                form={form}
                                configured={configured.stripe_secret}
                                source={sources.stripe_secret}
                            />
                            <SecretInput
                                label="Publishable key"
                                field="stripe_publishable_key"
                                form={form}
                                configured={configured.stripe_publishable_key}
                                source={sources.stripe_publishable_key}
                            />
                            <SecretInput
                                label="Webhook signing secret"
                                field="stripe_webhook_secret"
                                form={form}
                                configured={configured.stripe_webhook_secret}
                                source={sources.stripe_webhook_secret}
                            />
                            <label>
                                <span className="field-label">API endpoint</span>
                                <input
                                    className="field"
                                    value={form.data.stripe_endpoint}
                                    onChange={(event) => update('stripe_endpoint', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Webhook tolerance (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={900}
                                    value={form.data.stripe_webhook_tolerance}
                                    onChange={(event) => update('stripe_webhook_tolerance', Number(event.target.value))}
                                />
                            </label>
                            <label>
                                <span className="field-label">Request timeout (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={120}
                                    value={form.data.stripe_timeout}
                                    onChange={(event) => update('stripe_timeout', Number(event.target.value))}
                                />
                            </label>
                        </div>
                    </section>

                    <section id="whish" className="card space-y-5 p-6">
                        <div>
                            <h2 className="section-title">Whish Pay</h2>
                            <p className="mt-1 text-sm text-muted">
                                Collectors can generate QR payment requests; signed callbacks mark successful payments.
                            </p>
                        </div>
                        <label className="flex items-center gap-3 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={form.data.whish_enabled}
                                onChange={(event) => update('whish_enabled', event.target.checked)}
                            />
                            Enable Whish Pay
                        </label>
                        <div className="grid gap-5 md:grid-cols-2">
                            <label>
                                <span className="field-label">Environment</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={form.data.whish_environment}
                                    onChange={(event) => update('whish_environment', event.target.value)}
                                >
                                    <option value="sandbox">Sandbox</option>
                                    <option value="production">Production</option>
                                </ResponsiveSelect>
                            </label>
                            <SecretInput
                                label="Channel / merchant ID"
                                field="whish_channel"
                                form={form}
                                configured={configured.whish_channel}
                                source={sources.whish_channel}
                            />
                            <SecretInput
                                label="Secret"
                                field="whish_secret"
                                form={form}
                                configured={configured.whish_secret}
                                source={sources.whish_secret}
                            />
                            <label>
                                <span className="field-label">Website URL</span>
                                <input
                                    className="field"
                                    value={form.data.whish_website_url}
                                    onChange={(event) => update('whish_website_url', event.target.value)}
                                    placeholder="https://isp.example"
                                />
                            </label>
                            <label>
                                <span className="field-label">API endpoint</span>
                                <input
                                    className="field"
                                    value={form.data.whish_endpoint}
                                    onChange={(event) => update('whish_endpoint', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Request timeout (seconds)</span>
                                <input
                                    className="field"
                                    type="number"
                                    min={1}
                                    max={120}
                                    value={form.data.whish_timeout}
                                    onChange={(event) => update('whish_timeout', Number(event.target.value))}
                                />
                            </label>
                            <label>
                                <span className="field-label">Success callback URL</span>
                                <input
                                    className="field"
                                    value={form.data.whish_success_callback_url}
                                    onChange={(event) => update('whish_success_callback_url', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Failure callback URL</span>
                                <input
                                    className="field"
                                    value={form.data.whish_failure_callback_url}
                                    onChange={(event) => update('whish_failure_callback_url', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Success redirect URL</span>
                                <input
                                    className="field"
                                    value={form.data.whish_success_redirect_url}
                                    onChange={(event) => update('whish_success_redirect_url', event.target.value)}
                                />
                            </label>
                            <label>
                                <span className="field-label">Failure redirect URL</span>
                                <input
                                    className="field"
                                    value={form.data.whish_failure_redirect_url}
                                    onChange={(event) => update('whish_failure_redirect_url', event.target.value)}
                                />
                            </label>
                        </div>
                    </section>

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            className="button-primary inline-flex items-center gap-2"
                            disabled={form.processing}
                        >
                            <Save size={16} /> {form.processing ? 'Saving…' : 'Save integration settings'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
