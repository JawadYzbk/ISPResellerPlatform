import { Head, Link, router, useForm } from '@inertiajs/react';
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
    const [editingId, setEditingId] = useState<string | null>(null);
    const [responseToArchive, setResponseToArchive] = useState<TicketResponse | null>(null);
    const form = useForm<FormData>({ title: '', body: '', category: 'support', is_active: true });

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
            is_active: true,
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
            <Head title="Ticket responses" />
            <Link
                href="/settings/general"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to workspace settings
            </Link>
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <p className="eyebrow">Support operations</p>
                    <h1 className="page-title">Ticket responses</h1>
                    <p className="page-subtitle">
                        Give operators consistent, editable replies for common customer situations.
                    </p>
                </div>
                <Link href="/operations/tickets" className="button-secondary">
                    Open ticket queue
                </Link>
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[0.72fr_1.28fr]">
                <form onSubmit={submit} className="card space-y-5 p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <MessageSquareText size={18} className="text-brand" />
                            <h2 className="section-title">{editingId ? 'Edit response' : 'New response'}</h2>
                        </div>
                        {editingId && (
                            <button type="button" className="button-quiet" onClick={resetForm}>
                                <X size={15} /> Cancel
                            </button>
                        )}
                    </div>
                    <p className="text-sm text-muted">
                        Responses are available to staff with ticket reply access. Keep them short enough to
                        personalize.
                    </p>
                    <label>
                        <span className="field-label">Title</span>
                        <input
                            className="field"
                            value={form.data.title}
                            onChange={(event) => form.setData('title', event.target.value)}
                            placeholder="For example, Payment received"
                        />
                        {form.errors.title && <p className="field-error">{form.errors.title}</p>}
                    </label>
                    <label>
                        <span className="field-label">Category</span>
                        <ResponsiveSelect
                            className="field"
                            value={form.data.category}
                            onChange={(event) => form.setData('category', event.target.value)}
                        >
                            {categories.map((category) => (
                                <option key={category.value} value={category.value}>
                                    {category.label}
                                </option>
                            ))}
                        </ResponsiveSelect>
                        {form.errors.category && <p className="field-error">{form.errors.category}</p>}
                    </label>
                    <label>
                        <span className="field-label">Reply text</span>
                        <textarea
                            className="field min-h-36 resize-y"
                            value={form.data.body}
                            onChange={(event) => form.setData('body', event.target.value)}
                            placeholder="Write the reusable customer-facing reply"
                        />
                        {form.errors.body && <p className="field-error">{form.errors.body}</p>}
                    </label>
                    {editingId && (
                        <label className="flex items-center gap-3 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) => form.setData('is_active', event.target.checked)}
                            />
                            Keep this response available to operators
                        </label>
                    )}
                    <button className="button-primary w-full justify-center" disabled={form.processing}>
                        <Save size={16} /> {editingId ? 'Save response' : 'Create response'}
                    </button>
                </form>

                <section className="card overflow-hidden">
                    <div className="border-b border-line px-6 py-5">
                        <h2 className="section-title">Saved responses</h2>
                        <p className="mt-1 text-sm text-muted">
                            Archived responses stay in the workspace so they can be restored or reviewed later.
                        </p>
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
                                                <span className="badge bg-amber-50 text-amber-700">Archived</span>
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
                                            {response.is_active ? 'Edit' : 'Restore'}
                                        </button>
                                        {response.is_active && (
                                            <button
                                                type="button"
                                                className="button-secondary text-coral"
                                                onClick={() => setResponseToArchive(response)}
                                            >
                                                <Archive size={15} /> Archive
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </article>
                        ))}
                        {responses.length === 0 && (
                            <p className="p-6 text-sm text-muted">No saved ticket responses yet.</p>
                        )}
                    </div>
                </section>
            </div>

            <AlertDialog open={responseToArchive !== null} onOpenChange={(open) => !open && setResponseToArchive(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Archive this ticket response?</AlertDialogTitle>
                        <AlertDialogDescription>
                            “{responseToArchive?.title}” will disappear from the ticket composer. You can restore it
                            later.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Keep response</AlertDialogCancel>
                        <AlertDialogAction onClick={archive}>Archive response</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
