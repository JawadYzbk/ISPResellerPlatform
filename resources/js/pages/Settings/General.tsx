import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Settings2 } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';

type Tenant = { public_id: string; name: string; slug: string };
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

type FormSettings = Settings & { name: string };

type Props = { tenant: Tenant; settings: Settings };

export default function GeneralSettings({ tenant, settings }: Props) {
    const form = useForm<FormSettings>({ ...settings, name: tenant.name });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put('/settings/general');
    };

    return (
        <AppLayout>
            <Head title="Workspace settings" />
            <Link href="/dashboard" className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand">
                <ArrowLeft size={16} /> Back to dashboard
            </Link>
            <div className="max-w-4xl">
                <p className="eyebrow">Administration · {tenant.slug}</p>
                <h1 className="page-title">Workspace settings</h1>
                <p className="page-subtitle">Control tenant identity, business time, currency, and automation defaults.</p>
                <form onSubmit={submit} className="card mt-8 space-y-8 p-6">
                    <section>
                        <div className="flex items-center gap-2">
                            <Settings2 size={18} className="text-brand" />
                            <h2 className="section-title">Workspace identity</h2>
                        </div>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">Workspace name</span>
                                <input className="field" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} />
                                {form.errors.name && <p className="field-error">{form.errors.name}</p>}
                            </label>
                            <label>
                                <span className="field-label">Tenant slug</span>
                                <input className="field bg-sand" value={tenant.slug} disabled />
                                <p className="mt-1 text-xs text-muted">The public portal URL is not changed here.</p>
                            </label>
                            <label>
                                <span className="field-label">Locale</span>
                                <select className="field" value={form.data.locale} onChange={(event) => form.setData('locale', event.target.value as Settings['locale'])}>
                                    <option value="en">English</option>
                                    <option value="ar">Arabic</option>
                                    <option value="fr">French</option>
                                </select>
                            </label>
                            <label>
                                <span className="field-label">Timezone</span>
                                <input className="field" value={form.data.timezone} onChange={(event) => form.setData('timezone', event.target.value)} placeholder="Asia/Beirut" />
                                {form.errors.timezone && <p className="field-error">{form.errors.timezone}</p>}
                            </label>
                        </div>
                    </section>
                    <section className="border-t border-line pt-7">
                        <h2 className="section-title">Money and display</h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">Base currency</span>
                                <input className="field" maxLength={3} value={form.data.base_currency} onChange={(event) => form.setData('base_currency', event.target.value.toUpperCase())} />
                                {form.errors.base_currency && <p className="field-error">{form.errors.base_currency}</p>}
                            </label>
                            <label>
                                <span className="field-label">Collection currency</span>
                                <input className="field" maxLength={3} value={form.data.collection_currency} onChange={(event) => form.setData('collection_currency', event.target.value.toUpperCase())} />
                                {form.errors.collection_currency && <p className="field-error">{form.errors.collection_currency}</p>}
                            </label>
                            <label>
                                <span className="field-label">Date format</span>
                                <input className="field" value={form.data.date_format} onChange={(event) => form.setData('date_format', event.target.value)} />
                            </label>
                            <label>
                                <span className="field-label">Time format</span>
                                <input className="field" value={form.data.time_format} onChange={(event) => form.setData('time_format', event.target.value)} />
                            </label>
                        </div>
                        <label className="mt-5 flex items-center gap-3 text-sm font-medium">
                            <input type="checkbox" checked={form.data.rtl} onChange={(event) => form.setData('rtl', event.target.checked)} />
                            Prefer right-to-left layout for this workspace
                        </label>
                    </section>
                    <section className="border-t border-line pt-7">
                        <h2 className="section-title">Automation windows</h2>
                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <label>
                                <span className="field-label">Notification quiet start</span>
                                <input className="field" type="time" value={form.data.notification_quiet_start} onChange={(event) => form.setData('notification_quiet_start', event.target.value)} />
                            </label>
                            <label>
                                <span className="field-label">Notification quiet end</span>
                                <input className="field" type="time" value={form.data.notification_quiet_end} onChange={(event) => form.setData('notification_quiet_end', event.target.value)} />
                            </label>
                            <label>
                                <span className="field-label">Resolved ticket auto-close (hours)</span>
                                <input className="field" type="number" min={1} max={720} value={form.data.resolved_ticket_auto_close_hours} onChange={(event) => form.setData('resolved_ticket_auto_close_hours', Number(event.target.value))} />
                            </label>
                            <label>
                                <span className="field-label">RADIUS interim interval (seconds)</span>
                                <input className="field" type="number" min={30} max={3600} value={form.data.radius_interim_interval_seconds} onChange={(event) => form.setData('radius_interim_interval_seconds', Number(event.target.value))} />
                            </label>
                        </div>
                        <label className="mt-5 flex items-center gap-3 text-sm font-medium">
                            <input type="checkbox" checked={form.data.grace_extends_period} onChange={(event) => form.setData('grace_extends_period', event.target.checked)} />
                            Extend a billing period from its expiry when grace renewal is enabled
                        </label>
                    </section>
                    <div className="flex justify-end border-t border-line pt-5">
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> Save settings
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
