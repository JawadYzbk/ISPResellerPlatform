import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Archive, ArrowLeft, Edit3, MessageSquareText, RotateCcw, Save, X } from 'lucide-react';
import { useState } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type TicketResponse = {
    public_id: string;
    title: string;
    body: string;
    category: string;
    is_active: boolean;
};

type Props = PageProps & { responses: TicketResponse[] };
type FormData = { title: string; body: string; category: string; is_active: boolean };

const categories = [
    { value: 'billing', label: 'Billing' },
    { value: 'support', label: 'Support' },
    { value: 'operations', label: 'Operations' },
    { value: 'general', label: 'General' },
];

export default function TicketResponses({ responses }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const [editingId, setEditingId] = useState<string | null>(null);
    const [responseToArchive, setResponseToArchive] = useState<TicketResponse | null>(null);
    const form = useForm<FormData>({ title: '', body: '', category: 'support', is_active: true });
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

    const resetForm = () => {
        setEditingId(null);
        form.reset();
        form.setData('category', 'support');
        form.setData('is_active', true);
    };

    const edit = (response: TicketResponse) => {
        setEditingId(response.public_id);
        form.setData({
            title: response.title,
            body: response.body,
            category: response.category,
            is_active: response.is_active,
        });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (editingId) {
            form.patch(`/settings/ticket-responses/${editingId}`, { preserveScroll: true });
        } else {
            form.post('/settings/ticket-responses', { preserveScroll: true });
        }
    };

    const archive = () => {
        if (!responseToArchive) return;
        router.delete(`/settings/ticket-responses/${responseToArchive.public_id}`, {
            preserveScroll: true,
            onFinish: () => setResponseToArchive(null),
        });
    };

    return (
        <AppLayout>
            <Head title={t('ticket_responses.title')} />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> {t('Back to workspace settings')}
            </Link>
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">{t('tickets.support_operations')}</p>
                    <h1 className="page-title">{t('ticket_responses.title')}</h1>
                    <p className="page-subtitle">{t('ticket_responses.subtitle')}</p>
                </div>
                <Link href="/operations/tickets" className="button-secondary">
                    {t('ticket_responses.open_queue')}
                </Link>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[0.72fr_1.28fr]">
                <form onSubmit={submit} className="card space-y-5 p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <MessageSquareText size={18} className="text-brand" />
                            <h2 className="section-title">
                                {editingId ? t('ticket_responses.edit') : t('ticket_responses.new')}
                            </h2>
                        </div>
                        {editingId && (
                            <button type="button" className="button-quiet" onClick={resetForm}>
                                <X size={15} /> {t('Cancel')}
                            </button>
                        )}
                    </div>
                    <p className="text-sm text-muted">{t('ticket_responses.form_description')}</p>
                    <label>
                        <span className="field-label">{t('Title')}</span>
                        <input
                            id="response-title"
                            className="field"
                            {...fieldA11y('response-title', form.errors.title)}
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            placeholder={t('ticket_responses.title_placeholder')}
                        />
                        {fieldError('response-title', form.errors.title)}
                    </label>
                    <label>
                        <span className="field-label">{t('Category')}</span>
                        <ResponsiveSelect
                            id="response-category"
                            className="field"
                            {...fieldA11y('response-category', form.errors.category)}
                            value={form.data.category}
                            onChange={(event) => form.setData('category', event.target.value)}
                        >
                            {categories.map((category) => (
                                <option key={category.value} value={category.value}>
                                    {t(category.label)}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {fieldError('response-category', form.errors.category)}
                    </label>
                    <label>
                        <span className="field-label">{t('ticket_responses.reply_text')}</span>
                        <textarea
                            id="response-body"
                            className="field min-h-36 resize-y"
                            {...fieldA11y('response-body', form.errors.body)}
                            value={form.data.body}
                            onChange={(event) => form.setData('body', event.target.value)}
                            placeholder={t('ticket_responses.reply_placeholder')}
                        />
                        {fieldError('response-body', form.errors.body)}
                    </label>
                    {editingId && (
                        <label className="flex items-center gap-3 text-sm">
                            <input
                                id="response-is-active"
                                type="checkbox"
                                {...fieldA11y('response-is-active', form.errors.is_active)}
                                checked={form.data.is_active}
                                onChange={(event) => form.setData('is_active', event.target.checked)}
                            />
                            {t('ticket_responses.keep_available')}
                        </label>
                    )}
                    {fieldError('response-is-active', form.errors.is_active)}
                    <button type="submit" className="button-primary w-full justify-center" disabled={form.processing}>
                        <Save size={16} /> {editingId ? t('ticket_responses.save') : t('ticket_responses.create')}
                    </button>
                </form>

                <section className="card overflow-hidden">
                    <div className="border-b border-line px-6 py-5">
                        <h2 className="section-title">{t('ticket_responses.saved')}</h2>
                        <p className="mt-1 text-sm text-muted">{t('ticket_responses.saved_description')}</p>
                    </div>
                    <div className="divide-y divide-line">
                        {responses.map((response) => (
                            <article key={response.public_id} className="p-6">
                                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold">{response.title}</h3>
                                            <span className="badge bg-sand text-muted">{response.category}</span>
                                            {!response.is_active && (
                                                <span className="badge bg-amber-50 text-amber-700">
                                                    {t('Archived')}
                                                </span>
                                            )}
                                        </div>
                                        <p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-muted">
                                            {response.body}
                                        </p>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <button
                                            type="button"
                                            className="button-secondary"
                                            onClick={() => edit(response)}
                                        >
                                            {response.is_active ? <Edit3 size={15} /> : <RotateCcw size={15} />}
                                            {response.is_active ? t('Edit') : t('Restore')}
                                        </button>
                                        {response.is_active && (
                                            <button
                                                type="button"
                                                className="button-secondary text-coral"
                                                onClick={() => setResponseToArchive(response)}
                                            >
                                                <Archive size={15} /> {t('Archive')}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                        {responses.length === 0 && (
                            <p className="p-6 text-sm text-muted">{t('ticket_responses.none')}</p>
                        )}
                    </div>
                </section>
            </div>

            <AlertDialog open={responseToArchive !== null} onOpenChange={(open) => !open && setResponseToArchive(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('ticket_responses.archive_title')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            “{responseToArchive?.title}” {t('ticket_responses.archive_disappear')} {t('ticket_responses.archive_restore')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('ticket_responses.keep_response')}</AlertDialogCancel>
                        <AlertDialogAction onClick={archive}>
                            {t('ticket_responses.archive_response')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
