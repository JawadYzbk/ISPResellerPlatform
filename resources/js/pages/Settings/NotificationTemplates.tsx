import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, Copy, MessageSquare, Save } from 'lucide-react';
import { useState } from 'react';

import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Template = {
    id: number;
    key: string;
    channel: string;
    locale: string;
    subject: string | null;
    body: string;
    variables: string[];
    is_active: boolean;
};

type CatalogItem = {
    key: string;
    label: string;
    variables: string[];
};

type Props = {
    templates: Template[];
    catalog: CatalogItem[];
    locales: string[];
    storageWarning?: string | null;
};

function TemplateCard({
    definition,
    templates,
    locales,
    t,
}: {
    definition: CatalogItem;
    templates: Template[];
    locales: string[];
    t: (key: string) => string;
}) {
    const initial = templates.find((template) => template.locale === locales[0]) ?? templates[0];
    const [locale, setLocale] = useState(initial?.locale ?? locales[0] ?? 'en');
    const [copiedVariable, setCopiedVariable] = useState<string | null>(null);
    const selected = templates.find((template) => template.locale === locale) ?? initial;
    const form = useForm({ subject: selected?.subject ?? '', body: selected?.body ?? '' });

    const selectLocale = (nextLocale: string) => {
        const next = templates.find((template) => template.locale === nextLocale);
        if (!next) return;
        setLocale(nextLocale);
        form.setData({ subject: next.subject ?? '', body: next.body });
        form.clearErrors();
    };

    const copyVariable = async (variable: string) => {
        await navigator.clipboard?.writeText(`{{ ${variable} }}`);
        setCopiedVariable(variable);
        window.setTimeout(() => setCopiedVariable(null), 1200);
    };

    const submit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!selected) return;
        form.patch(`/settings/notification-templates/${selected.id}`, { preserveScroll: true });
    };

    if (!selected) return null;

    return (
        <form onSubmit={submit} className="card overflow-hidden">
            <div className="flex flex-col gap-3 border-b border-line px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 className="font-semibold text-ink">{t(definition.label)}</h2>
                    <p className="mt-1 font-mono text-xs text-muted">{definition.key}</p>
                </div>
                <span className="inline-flex items-center gap-1.5 self-start rounded-full bg-brand/10 px-2.5 py-1 text-xs font-semibold text-brand">
                    <MessageSquare size={13} /> {t('WhatsApp')}
                </span>
            </div>
            <div className="space-y-5 p-5">
                <div
                    className="flex flex-wrap gap-1 rounded-lg bg-sand p-1"
                    role="tablist"
                    aria-label={t(definition.label) + ' ' + t('languages')}
                >
                    {locales.map((option) => (
                        <button
                            key={option}
                            type="button"
                            role="tab"
                            aria-selected={locale === option}
                            onClick={() => selectLocale(option)}
                            className={`rounded-md px-3 py-1.5 text-xs font-semibold transition ${locale === option ? 'bg-white text-brand shadow-sm' : 'text-muted hover:text-ink'}`}
                        >
                            {t(option === 'en' ? 'English' : option === 'ar' ? 'Arabic' : 'French')}
                        </button>
                    ))}
                </div>
                <label>
                    <span className="field-label">{t('Message body')}</span>
                    <textarea
                        dir="auto"
                        className="field min-h-36 resize-y font-mono text-sm leading-6"
                        value={form.data.body}
                        onChange={(event) => form.setData('body', event.target.value)}
                    />
                    {form.errors.body && <p className="field-error">{t(form.errors.body)}</p>}
                </label>
                <div className="rounded-xl border border-line bg-sand/40 p-4">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-semibold">{t('Available variables')}</p>
                        <p className="text-xs text-muted">{t('Copied variables keep their braces.')}</p>
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {definition.variables.map((variable) => (
                            <button
                                key={variable}
                                type="button"
                                onClick={() => void copyVariable(variable)}
                                className="inline-flex items-center gap-1.5 rounded-md border border-line bg-white px-2.5 py-1.5 font-mono text-xs text-ink hover:border-brand/40 hover:text-brand"
                                title={`Copy {{ ${variable} }}`}
                            >
                                {copiedVariable === variable ? <Check size={13} /> : <Copy size={13} />}
                                {`{{ ${variable} }}`}
                            </button>
                        ))}
                    </div>
                    <p className="mt-3 text-xs text-muted text-pretty">
                        {t(
                            'Only these variables are accepted. At send time the customer, service, payment, or incident data replaces each token.',
                        )}
                    </p>
                </div>
                <div className="flex items-center justify-between gap-4 border-t border-line pt-4">
                    <p className="text-xs text-muted">
                        {t('Editing')} {t(locale === 'en' ? 'English' : locale === 'ar' ? 'Arabic' : 'French')} ·{' '}
                        {selected.is_active ? t('Active for delivery') : t('Disabled')}
                    </p>
                    <button type="submit" className="button-primary" disabled={form.processing}>
                        <Save size={15} /> {t('Save template')}
                    </button>
                </div>
            </div>
        </form>
    );
}

export default function NotificationTemplatesPage({ templates, catalog, locales, storageWarning }: Props) {
    const page = usePage<PageProps>();
    const t = createTranslator(page.props.app.locale);

    return (
        <AppLayout>
            <Head title={t('WhatsApp message templates')} />
            <div>
                <p className="eyebrow">{t('Automation')}</p>
                <h1 className="page-title">{t('WhatsApp message templates')}</h1>
                <p className="page-subtitle text-pretty">
                    {t(
                        'Customize every automated WhatsApp message by language. Variables are validated before saving and replaced from the sending record at delivery time.',
                    )}
                </p>
            </div>
            {storageWarning && (
                <div className="mt-6 flex items-start gap-3 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <AlertTriangle className="mt-0.5 shrink-0" size={17} />
                    <p className="text-pretty">{storageWarning}</p>
                </div>
            )}
            <div className="mt-8 grid gap-5 xl:grid-cols-2">
                {catalog.map((definition) => (
                    <TemplateCard
                        key={definition.key}
                        definition={definition}
                        templates={templates.filter((template) => template.key === definition.key)}
                        locales={locales}
                        t={t}
                    />
                ))}
            </div>
        </AppLayout>
    );
}
