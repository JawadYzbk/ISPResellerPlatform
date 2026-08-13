import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Save, Ticket as TicketIcon } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type CustomerSummary = {
    public_id: string;
    code: string;
    first_name: string;
    last_name: string | null;
};

type Props = PageProps & {
    customer: CustomerSummary;
    services: { public_id: string; username: string; plan: string | null }[];
};

export default function TicketCreate({ customer, services }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const form = useForm({
        subject: '',
        description: '',
        category: 'connection',
        priority: 'normal',
        service_public_id: '',
    });
    const fieldA11y = (id: string, error?: string) => ({
        'aria-invalid': Boolean(error),
        'aria-describedby': error ? `${id}-error` : undefined,
    });
    const fieldError = (id: string, error?: string) =>
        error ? (
            <p id={`${id}-error`} className="field-error" role="alert">
                {t(error)}
            </p>
        ) : null;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(`/customers/${customer.public_id}/tickets`);
    };

    return (
        <AppLayout>
            <Head title={t('ticket_create.title')} />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to customer')}
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">
                    {t('ticket_create.eyebrow')} · {customer.code}
                </p>
                <h1 className="page-title">{t('ticket_create.title')}</h1>
                <p className="page-subtitle">
                    {t('ticket_create.subtitle')} {customer.first_name} {customer.last_name ?? ''}
                </p>
                <form onSubmit={submit} className="card mt-6 space-y-6 p-6">
                    <label>
                        <span className="field-label">{t('Subject')}</span>
                        <input
                            id="subject"
                            className="field"
                            {...fieldA11y('subject', form.errors.subject)}
                            value={form.data.subject}
                            onChange={(event) => form.setData('subject', event.target.value)}
                            maxLength={160}
                            required
                        />
                        {fieldError('subject', form.errors.subject)}
                    </label>
                    <div className="grid gap-5 sm:grid-cols-3">
                        <label>
                            <span className="field-label">{t('Category')}</span>
                            <ResponsiveSelect
                                id="category"
                                className="field"
                                {...fieldA11y('category', form.errors.category)}
                                value={form.data.category}
                                onChange={(event) => form.setData('category', event.target.value)}
                            >
                                <option value="connection">{t('Connection')}</option>
                                <option value="billing">{t('Billing')}</option>
                                <option value="installation">{t('Installation')}</option>
                                <option value="technical">{t('Technical')}</option>
                                <option value="other">{t('Other')}</option>
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">{t('Priority')}</span>
                            <ResponsiveSelect
                                id="priority"
                                className="field"
                                {...fieldA11y('priority', form.errors.priority)}
                                value={form.data.priority}
                                onChange={(event) => form.setData('priority', event.target.value)}
                            >
                                <option value="critical">{t('Critical')}</option>
                                <option value="high">{t('High')}</option>
                                <option value="normal">{t('Normal')}</option>
                                <option value="low">{t('Low')}</option>
                            </ResponsiveSelect>
                        </label>
                        <label>
                            <span className="field-label">{t('Service')}</span>
                            <ResponsiveSelect
                                id="service_public_id"
                                className="field"
                                {...fieldA11y('service_public_id', form.errors.service_public_id)}
                                value={form.data.service_public_id}
                                onChange={(event) => form.setData('service_public_id', event.target.value)}
                            >
                                <option value="">{t('ticket_create.customer_ticket')}</option>
                                {services.map((service) => (
                                    <option key={service.public_id} value={service.public_id}>
                                        {service.username} · {service.plan ?? t('No plan')}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                    </div>
                    <label>
                        <span className="field-label">{t('Description')}</span>
                        <textarea
                            id="description"
                            className="field min-h-40"
                            {...fieldA11y('description', form.errors.description)}
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            maxLength={10000}
                            required
                        />
                        {fieldError('description', form.errors.description)}
                    </label>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            {t('Cancel')}
                        </Link>
                        <button type="submit" className="button-primary" disabled={form.processing}>
                            <Save size={16} /> <TicketIcon size={16} /> {t('Open ticket')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
