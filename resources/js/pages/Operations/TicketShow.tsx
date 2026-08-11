import ResponsiveSelect from '@/components/ui/responsive-select';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, MessageSquare, Send } from 'lucide-react';
import { useState } from 'react';

import StatusBadge from '@/components/StatusBadge';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import type { PageProps } from '@/types';

type TicketMessage = {
    public_id: string;
    author_type: string;
    body: string;
    visibility: string;
    created_at: string | null;
};

type Ticket = {
    public_id: string;
    number: string;
    subject: string;
    description: string;
    priority: string;
    status: 'open' | 'in_progress' | 'pending' | 'resolved' | 'closed';
    due_at: string | null;
    message_count: number;
    customer: { public_id: string; code: string; name: string } | null;
    service: { public_id: string; username: string } | null;
    assignee: { id: number; name: string } | null;
    messages: TicketMessage[];
};

type Props = PageProps & {
    ticket: Ticket;
    assignees: { id: number; name: string; role: string }[];
    canAssign?: boolean;
    canMutate?: boolean;
    canClose?: boolean;
};

const transitions: Record<Ticket['status'], Ticket['status'][]> = {
    open: ['open', 'in_progress', 'pending', 'resolved', 'closed'],
    in_progress: ['in_progress', 'pending', 'resolved', 'closed'],
    pending: ['pending', 'in_progress', 'resolved', 'closed'],
    resolved: ['resolved', 'in_progress', 'closed'],
    closed: ['closed'],
};

export default function TicketShow({
    ticket,
    assignees,
    canAssign = false,
    canMutate = false,
    canClose = false,
}: Props) {
    const [status, setStatus] = useState(ticket.status);
    const [assigneeId, setAssigneeId] = useState(ticket.assignee?.id.toString() ?? '');
    const [visibility, setVisibility] = useState<'public' | 'internal'>('public');
    const form = useForm<{ body: string; visibility: 'public' | 'internal' }>({ body: '', visibility: 'public' });

    const updateStatus = (event: React.FormEvent) => {
        event.preventDefault();
        if (status !== ticket.status) {
            router.post(`/operations/tickets/${ticket.public_id}/status`, { status });
        }
    };

    const submitReply = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(`/operations/tickets/${ticket.public_id}/messages`, { preserveScroll: true });
    };

    const submitAssignment = (event: React.FormEvent) => {
        event.preventDefault();
        router.post(
            `/operations/tickets/${ticket.public_id}/assignee`,
            { assignee_id: assigneeId || null },
            { preserveScroll: true },
        );
    };

    return (
        <AppLayout>
            <Head title={ticket.number} />
            <Link
                href="/operations/tickets"
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to tickets
            </Link>
            <div className="flex flex-col justify-between gap-5 md:flex-row md:items-end">
                <div>
                    <p className="eyebrow">Support ticket</p>
                    <div className="mt-2 flex flex-wrap items-center gap-3">
                        <h1 className="page-title">{ticket.number}</h1>
                        <StatusBadge status={ticket.status} />
                    </div>
                    <p className="page-subtitle">{ticket.subject}</p>
                </div>
                {ticket.customer && (
                    <Link href={`/customers/${ticket.customer.public_id}`} className="button-secondary">
                        {ticket.customer.name}
                    </Link>
                )}
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-[0.7fr_1.3fr]">
                <aside className="space-y-6">
                    <div className="card p-6">
                        <h2 className="section-title">Ticket details</h2>
                        <dl className="mt-5 space-y-4 text-sm">
                            <div>
                                <dt className="text-xs text-muted">Priority</dt>
                                <dd className="mt-1 font-semibold capitalize">{ticket.priority}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">SLA due</dt>
                                <dd className="mt-1 font-semibold">{formatDate(ticket.due_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">Service</dt>
                                <dd className="mt-1 font-semibold">{ticket.service?.username ?? 'Not linked'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted">Assigned to</dt>
                                <dd className="mt-1 font-semibold">{ticket.assignee?.name ?? 'Unassigned'}</dd>
                            </div>
                        </dl>
                    </div>
                    {canAssign && (
                        <form onSubmit={submitAssignment} className="card space-y-4 p-6">
                            <h2 className="section-title">Assignment</h2>
                            <label>
                                <span className="field-label">Responsible operator</span>
                                <ResponsiveSelect
                                    className="field"
                                    value={assigneeId}
                                    onChange={(event) => setAssigneeId(event.target.value)}
                                >
                                    <option value="">Unassigned</option>
                                    {assignees.map((assignee) => (
                                        <option key={assignee.id} value={assignee.id}>
                                            {assignee.name} · {assignee.role.replace('_', ' ')}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                            <button className="button-secondary w-full justify-center">Save assignment</button>
                        </form>
                    )}
                    {canMutate && ticket.status !== 'closed' && (
                        <form onSubmit={updateStatus} className="card space-y-4 p-6">
                            <h2 className="section-title">Update status</h2>
                            <ResponsiveSelect
                                className="field"
                                value={status}
                                onChange={(event) => setStatus(event.target.value as Ticket['status'])}
                            >
                                {transitions[ticket.status].map((option) => (
                                    <option key={option} value={option} disabled={option === 'closed' && !canClose}>
                                        {option.replace('_', ' ')}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                            <button className="button-primary" disabled={status === ticket.status}>
                                Save status
                            </button>
                        </form>
                    )}
                </aside>

                <section className="card overflow-hidden">
                    <div className="border-b border-line px-6 py-5">
                        <div className="flex items-center gap-2">
                            <MessageSquare size={17} className="text-brand" />
                            <h2 className="section-title">Conversation</h2>
                        </div>
                        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted">{ticket.description}</p>
                    </div>
                    <div className="space-y-5 p-6">
                        {ticket.messages.map((message) => (
                            <article
                                key={message.public_id}
                                className={`rounded-xl border p-4 ${message.visibility === 'internal' ? 'border-amber-200 bg-amber-50/70' : 'border-line bg-sand/30'}`}
                            >
                                <div className="flex items-center justify-between gap-3 text-xs text-muted">
                                    <span className="font-semibold capitalize">
                                        {message.author_type} ·{' '}
                                        {message.visibility === 'internal' ? 'internal note' : 'public reply'}
                                    </span>
                                    <time>{formatDate(message.created_at)}</time>
                                </div>
                                <p className="mt-3 whitespace-pre-wrap text-sm leading-6">{message.body}</p>
                            </article>
                        ))}
                        {ticket.messages.length === 0 && <p className="text-sm text-muted">No replies yet.</p>}
                    </div>
                    {canMutate && ticket.status !== 'closed' && (
                        <form onSubmit={submitReply} className="border-t border-line bg-sand/20 p-6">
                            <div className="mb-3 flex gap-2">
                                <button
                                    type="button"
                                    className={`button-secondary ${visibility === 'public' ? 'bg-white text-brand' : ''}`}
                                    onClick={() => {
                                        setVisibility('public');
                                        form.setData('visibility', 'public');
                                    }}
                                >
                                    Public reply
                                </button>
                                <button
                                    type="button"
                                    className={`button-secondary ${visibility === 'internal' ? 'bg-white text-amber-700' : ''}`}
                                    onClick={() => {
                                        setVisibility('internal');
                                        form.setData('visibility', 'internal');
                                    }}
                                >
                                    Internal note
                                </button>
                            </div>
                            <label className="field-label" htmlFor="body">
                                {visibility === 'internal' ? 'Internal note' : 'Public reply'}
                            </label>
                            <textarea
                                id="body"
                                className="field min-h-32 resize-y"
                                value={form.data.body}
                                onChange={(event) => form.setData('body', event.target.value)}
                                placeholder={
                                    visibility === 'internal'
                                        ? 'Add context for the next operator'
                                        : 'Write the customer-facing update'
                                }
                            />
                            {form.errors.body && <p className="field-error">{form.errors.body}</p>}
                            <div className="mt-4 flex justify-end">
                                <button className="button-primary" disabled={form.processing}>
                                    <Send size={16} /> Send reply
                                </button>
                            </div>
                        </form>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
