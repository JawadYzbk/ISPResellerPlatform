import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, Ticket as TicketIcon } from 'lucide-react';

import AppLayout from '@/layouts/AppLayout';
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
    const form = useForm({
        subject: '',
        description: '',
        category: 'connection',
        priority: 'normal',
        service_public_id: '',
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.post(`/customers/${customer.public_id}/tickets`);
    };

    return (
        <AppLayout>
            <Head title="Open ticket" />
            <Link
                href={`/customers/${customer.public_id}`}
                className="mb-6 inline-flex items-center gap-2 text-sm font-semibold text-muted hover:text-brand"
            >
                <ArrowLeft size={16} /> Back to customer
            </Link>
            <div className="max-w-3xl">
                <p className="eyebrow">Support operations · {customer.code}</p>
                <h1 className="page-title">Open a ticket</h1>
                <p className="page-subtitle">
                    Start a customer conversation for {customer.first_name} {customer.last_name ?? ''} with a clear
                    service link and SLA priority.
                </p>
                <form onSubmit={submit} className="card mt-6 space-y-6 p-6">
                    <label>
                        <span className="field-label">Subject</span>
                        <input
                            className="field"
                            value={form.data.subject}
                            onChange={(event) => form.setData('subject', event.target.value)}
                            maxLength={160}
                            required
                        />
                        {form.errors.subject && <p className="field-error">{form.errors.subject}</p>}
                    </label>
                    <div className="grid gap-5 sm:grid-cols-3">
                        <label>
                            <span className="field-label">Category</span>
                            <select
                                className="field"
                                value={form.data.category}
                                onChange={(event) => form.setData('category', event.target.value)}
                            >
                                <option value="connection">Connection</option>
                                <option value="billing">Billing</option>
                                <option value="installation">Installation</option>
                                <option value="technical">Technical</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <label>
                            <span className="field-label">Priority</span>
                            <select
                                className="field"
                                value={form.data.priority}
                                onChange={(event) => form.setData('priority', event.target.value)}
                            >
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                            </select>
                        </label>
                        <label>
                            <span className="field-label">Service</span>
                            <select
                                className="field"
                                value={form.data.service_public_id}
                                onChange={(event) => form.setData('service_public_id', event.target.value)}
                            >
                                <option value="">Customer-level ticket</option>
                                {services.map((service) => (
                                    <option key={service.public_id} value={service.public_id}>
                                        {service.username} · {service.plan ?? 'No plan'}
                                    </option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <label>
                        <span className="field-label">Description</span>
                        <textarea
                            className="field min-h-40"
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            maxLength={10000}
                            required
                        />
                        {form.errors.description && <p className="field-error">{form.errors.description}</p>}
                    </label>
                    <div className="flex justify-end gap-3 border-t border-line pt-5">
                        <Link href={`/customers/${customer.public_id}`} className="button-secondary">
                            Cancel
                        </Link>
                        <button className="button-primary" disabled={form.processing}>
                            <Save size={16} /> <TicketIcon size={16} /> Open ticket
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
