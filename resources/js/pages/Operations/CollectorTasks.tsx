import StatusBadge, { type Status } from '@/components/StatusBadge';
import CustomerCombobox, { type CustomerOption } from '@/components/ui/customer-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { formatDate } from '@/lib/format';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ClipboardCheck, MessageSquare, Paperclip, Plus, UserRound } from 'lucide-react';

import { createTranslator } from '@/lib/i18n';
import type { PageProps } from '@/types';

type Collector = { id: number; name: string; email: string };
type Customer = { id: number; code: string; name: string; phone: string | null };
type Message = {
    id: string;
    body: string;
    created_at: string;
    author: { id: number; name: string; role: string; is_viewer: boolean };
    attachments: { id: string; name: string; mime_type: string; size_bytes: number; download_url: string }[];
};
type Task = {
    id: string;
    title: string;
    description: string | null;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    status: Status;
    due_at: string | null;
    created_at: string;
    unread: boolean;
    collector: Collector;
    created_by: { name: string };
    customer: { id: string; code: string; name: string; phone: string | null; address: string | null } | null;
    messages: Message[];
};
type Props = {
    filters: { status: string; collector: number | null };
    collectors: Collector[];
    customers: Customer[];
    tasks: Task[];
    selectedTask: Task | null;
    timezone: string;
};

const statusOptions = [
    { value: 'open', label: 'Open work' },
    { value: 'assigned', label: 'Assigned' },
    { value: 'acknowledged', label: 'Acknowledged' },
    { value: 'in_progress', label: 'In progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function CollectorTasks({ filters, collectors, customers, tasks, selectedTask, timezone }: Props) {
    const { props } = usePage<PageProps>();
    const t = createTranslator(props.app.locale);
    const createForm = useForm({
        collector_id: collectors[0]?.id ?? 0,
        customer_id: '',
        title: '',
        description: '',
        priority: 'normal',
        due_at: '',
    });
    const messageForm = useForm<{ body: string; attachment: File | null }>({ body: '', attachment: null });
    const customerOptions: CustomerOption[] = customers.map((customer) => ({
        id: String(customer.id),
        code: customer.code,
        name: customer.name,
        phone: customer.phone,
        status: 'active',
        balance_amount: 0,
        balance_currency: '',
    }));
    const applyFilter = (next: { status?: string; collector?: number | null }) =>
        router.get(
            '/operations/collector-tasks',
            {
                status: next.status ?? filters.status,
                collector: 'collector' in next ? (next.collector ?? undefined) : (filters.collector ?? undefined),
            },
            { preserveState: true, replace: true },
        );

    return (
        <AppLayout>
            <Head title={t('collector_tasks.title')} />
            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p className="eyebrow">{t('collector_tasks.eyebrow')}</p>
                    <h1 className="page-title text-balance">{t('collector_tasks.title')}</h1>
                    <p className="page-subtitle text-pretty">{t('collector_tasks.subtitle')}</p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <ResponsiveSelect
                        aria-label={t('collector_tasks.status_filter')}
                        value={filters.status}
                        onChange={(event) => applyFilter({ status: event.target.value })}
                    >
                        {statusOptions.map((item) => (
                            <option key={item.value} value={item.value}>
                                {t(item.label)}
                            </option>
                        ))}
                    </ResponsiveSelect>
                    <ResponsiveSelect
                        aria-label={t('collector_tasks.collector_filter')}
                        value={filters.collector === null ? '' : String(filters.collector)}
                        onChange={(event) =>
                            applyFilter({ collector: event.target.value === '' ? null : Number(event.target.value) })
                        }
                    >
                        <option value="">{t('collector_tasks.all_collectors')}</option>
                        {collectors.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.name}
                            </option>
                        ))}
                    </ResponsiveSelect>
                </div>
            </div>

            <section className="card mt-6 p-6">
                <div className="flex items-start gap-3">
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                        <Plus size={18} />
                    </span>
                    <div>
                        <h2 className="section-title">{t('collector_tasks.assign_work')}</h2>
                        <p className="mt-1 text-pretty text-sm text-muted">
                            {t('collector_tasks.assign_work_description')}
                        </p>
                    </div>
                </div>
                <form
                    className="mt-5 grid gap-4 lg:grid-cols-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        createForm.post('/operations/collector-tasks', {
                            onSuccess: () => createForm.reset('title', 'description', 'customer_id', 'due_at'),
                        });
                    }}
                >
                    <label className="field-label">
                        {t('Collector')}
                        <ResponsiveSelect
                            className="mt-1"
                            value={String(createForm.data.collector_id)}
                            onChange={(event) => createForm.setData('collector_id', Number(event.target.value))}
                        >
                            {collectors.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </ResponsiveSelect>
                    </label>
                    <label className="field-label">
                        {t('Customer')} ({t('Optional').toLocaleLowerCase()})
                        <CustomerCombobox
                            className="field mt-1"
                            value={createForm.data.customer_id}
                            customers={customerOptions}
                            placeholder={t('collector_tasks.no_customer')}
                            onChange={(value) => createForm.setData('customer_id', value)}
                        />
                    </label>
                    <label className="field-label">
                        {t('Priority')}
                        <ResponsiveSelect
                            className="mt-1"
                            value={createForm.data.priority}
                            onChange={(event) => createForm.setData('priority', event.target.value)}
                        >
                            {['low', 'normal', 'high', 'urgent'].map((value) => (
                                <option key={value} value={value}>
                                    {t('collector_tasks.priority_' + value)}
                                </option>
                            ))}
                        </ResponsiveSelect>
                    </label>
                    <label className="field-label lg:col-span-2">
                        {t('Title')}
                        <input
                            className="field mt-1"
                            value={createForm.data.title}
                            maxLength={160}
                            onChange={(event) => createForm.setData('title', event.target.value)}
                        />
                    </label>
                    <label className="field-label">
                        {t('collector_tasks.due_at')} ({timezone})
                        <input
                            className="field mt-1"
                            type="datetime-local"
                            value={createForm.data.due_at}
                            onChange={(event) => createForm.setData('due_at', event.target.value)}
                        />
                    </label>
                    <label className="field-label lg:col-span-3">
                        {t('Instructions')}
                        <textarea
                            className="field mt-1 min-h-24"
                            value={createForm.data.description}
                            maxLength={5000}
                            onChange={(event) => createForm.setData('description', event.target.value)}
                        />
                    </label>
                    {(createForm.errors.title || createForm.errors.collector_id) && (
                        <p className="field-error lg:col-span-3">
                            {createForm.errors.title ?? createForm.errors.collector_id}
                        </p>
                    )}
                    <div className="flex justify-end lg:col-span-3">
                        <button
                            className="button-primary"
                            disabled={createForm.processing || createForm.data.title.trim() === ''}
                        >
                            {t('collector_tasks.create_task')}
                        </button>
                    </div>
                </form>
            </section>

            <div className="mt-6 grid gap-6 xl:grid-cols-[minmax(20rem,0.85fr)_minmax(30rem,1.35fr)]">
                <section className="card overflow-hidden">
                    <div className="border-b border-line px-5 py-4">
                        <h2 className="section-title">{t('collector_tasks.work_queue')}</h2>
                        <p className="mt-1 text-xs text-muted tabular-nums">
                            {tasks.length} {t('collector_tasks.tasks')}
                        </p>
                    </div>
                    <div className="divide-y divide-line">
                        {tasks.map((task) => (
                            <Link
                                key={task.id}
                                href={`/operations/collector-tasks?status=${filters.status}&task=${task.id}${filters.collector ? `&collector=${filters.collector}` : ''}`}
                                preserveScroll
                                className={`block p-5 ${selectedTask?.id === task.id ? 'bg-brand-soft/60' : 'hover:bg-sand/40'}`}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h3 className="line-clamp-2 font-semibold">{task.title}</h3>
                                    {task.unread && (
                                        <span
                                            className="mt-1 size-2 shrink-0 rounded-full bg-brand"
                                            aria-label={t('collector_tasks.unread_messages')}
                                        />
                                    )}
                                </div>
                                <div className="mt-3 flex flex-wrap items-center gap-2">
                                    <StatusBadge status={task.status} />
                                    <span className="text-xs font-semibold capitalize text-muted">{task.priority}</span>
                                </div>
                                <p className="mt-3 truncate text-xs text-muted">
                                    {task.collector.name}
                                    {task.customer ? ` · ${task.customer.name}` : ''}
                                </p>
                            </Link>
                        ))}
                        {tasks.length === 0 && (
                            <div className="p-10 text-center">
                                <ClipboardCheck className="mx-auto text-muted" size={28} />
                                <p className="mt-3 font-semibold">{t('collector_tasks.no_matching_tasks')}</p>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {t('collector_tasks.no_matching_tasks_description')}
                                </p>
                            </div>
                        )}
                    </div>
                </section>

                <section className="card min-w-0 p-6">
                    {selectedTask ? (
                        <>
                            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <StatusBadge status={selectedTask.status} />
                                        <span className="text-xs font-semibold capitalize text-muted">
                                            {t('collector_tasks.priority_' + selectedTask.priority)}{' '}
                                            {t('Priority').toLocaleLowerCase()}
                                        </span>
                                    </div>
                                    <h2 className="mt-3 text-balance font-display text-2xl font-semibold">
                                        {selectedTask.title}
                                    </h2>
                                    <p className="mt-2 text-pretty text-sm text-muted">
                                        {t('collector_tasks.assigned_to')} {selectedTask.collector.name}{' '}
                                        {t('collector_tasks.by')} {selectedTask.created_by.name}
                                    </p>
                                </div>
                                <ResponsiveSelect
                                    aria-label={t('collector_tasks.update_status')}
                                    value={selectedTask.status}
                                    onChange={(event) =>
                                        router.patch(
                                            `/operations/collector-tasks/${selectedTask.id}/status`,
                                            { status: event.target.value },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {statusOptions
                                        .filter((item) => item.value !== 'open')
                                        .map((item) => (
                                            <option key={item.value} value={item.value}>
                                                {item.label}
                                            </option>
                                        ))}
                                </ResponsiveSelect>
                            </div>
                            {selectedTask.description && (
                                <p className="mt-5 whitespace-pre-wrap text-pretty text-sm">
                                    {selectedTask.description}
                                </p>
                            )}
                            <div className="mt-5 grid gap-3 sm:grid-cols-2">
                                <div className="rounded-xl border border-line p-4">
                                    <p className="eyebrow">{t('Due')}</p>
                                    <p className="mt-2 text-sm font-semibold">
                                        {selectedTask.due_at
                                            ? formatDate(selectedTask.due_at)
                                            : t('collector_tasks.no_deadline')}
                                    </p>
                                </div>
                                <div className="rounded-xl border border-line p-4">
                                    <p className="eyebrow">{t('Customer')}</p>
                                    {selectedTask.customer ? (
                                        <Link
                                            href={`/customers/${selectedTask.customer.id}`}
                                            className="mt-2 block text-sm font-semibold text-brand"
                                        >
                                            {selectedTask.customer.name} · {selectedTask.customer.code}
                                        </Link>
                                    ) : (
                                        <p className="mt-2 text-sm text-muted">{t('collector_tasks.no_customer')}</p>
                                    )}
                                </div>
                            </div>
                            <div className="mt-6 border-t border-line pt-6">
                                <div className="flex items-center gap-2">
                                    <MessageSquare className="text-brand" size={18} />
                                    <h3 className="section-title">{t('collector_tasks.conversation')}</h3>
                                </div>
                                <div className="mt-4 space-y-3">
                                    {selectedTask.messages.map((message) => (
                                        <div
                                            key={message.id}
                                            className={`max-w-[88%] rounded-xl border border-line p-4 ${message.author.is_viewer ? 'ms-auto bg-brand-soft/60' : 'bg-sand/40'}`}
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-xs font-semibold">{message.author.name}</span>
                                                <span className="text-xs text-muted tabular-nums">
                                                    {formatDate(message.created_at)}
                                                </span>
                                            </div>
                                            <p className="mt-2 whitespace-pre-wrap text-pretty text-sm">
                                                {message.body}
                                            </p>
                                            {message.attachments.map((attachment) => (
                                                <a
                                                    key={attachment.id}
                                                    href={attachment.download_url}
                                                    className="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-brand"
                                                    download
                                                >
                                                    <Paperclip size={13} /> {attachment.name}
                                                </a>
                                            ))}
                                        </div>
                                    ))}
                                    {selectedTask.messages.length === 0 && (
                                        <p className="rounded-xl border border-dashed border-line p-6 text-center text-sm text-muted">
                                            {t('collector_tasks.no_messages')}
                                        </p>
                                    )}
                                </div>
                                <form
                                    className="mt-4"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        messageForm.post(`/operations/collector-tasks/${selectedTask.id}/messages`, {
                                            preserveScroll: true,
                                            forceFormData: true,
                                            onSuccess: () => messageForm.reset(),
                                        });
                                    }}
                                >
                                    <label className="field-label">
                                        {t('collector_tasks.new_message')}
                                        <textarea
                                            className="field mt-1 min-h-24"
                                            value={messageForm.data.body}
                                            maxLength={5000}
                                            onChange={(event) => messageForm.setData('body', event.target.value)}
                                        />
                                    </label>
                                    <label className="field-label mt-3 block">
                                        {t('Attachment')} ({t('Optional').toLocaleLowerCase()})
                                        <input
                                            key={messageForm.data.attachment?.name ?? 'empty'}
                                            className="field mt-1"
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp,.txt"
                                            onChange={(event) =>
                                                messageForm.setData('attachment', event.target.files?.[0] ?? null)
                                            }
                                        />
                                    </label>
                                    {messageForm.errors.body && (
                                        <p className="field-error mt-1">{messageForm.errors.body}</p>
                                    )}
                                    {messageForm.errors.attachment && (
                                        <p className="field-error mt-1">{messageForm.errors.attachment}</p>
                                    )}
                                    <div className="mt-3 flex justify-end">
                                        <button
                                            className="button-primary"
                                            disabled={messageForm.processing || messageForm.data.body.trim() === ''}
                                        >
                                            {t('Send message')}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </>
                    ) : (
                        <div className="grid min-h-72 place-items-center text-center">
                            <div>
                                <UserRound className="mx-auto text-muted" size={30} />
                                <p className="mt-3 font-semibold">{t('collector_tasks.select_task')}</p>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    {t('collector_tasks.select_task_description')}
                                </p>
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
