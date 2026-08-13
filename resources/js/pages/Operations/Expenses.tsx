import StatusBadge, { type Status } from '@/components/StatusBadge';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, FileCheck2, Paperclip, Plus, ReceiptText, Tags } from 'lucide-react';
import { useState } from 'react';

type Category = { id: number; public_id: string; name: string; code: string; is_active: boolean };
type Vendor = {
    id: number;
    public_id: string;
    name: string;
    phone: string | null;
    email: string | null;
    tax_number: string | null;
    address: string | null;
    is_active: boolean;
};
type Attachment = { public_id: string; name: string; mime_type: string; size_bytes: number; download_url: string };
type Expense = {
    public_id: string;
    status: Status;
    payment_source: 'cash' | 'bank' | 'collector';
    amount: number;
    currency: string;
    description: string;
    reference: string | null;
    incurred_at: string;
    review_note: string | null;
    category: Pick<Category, 'public_id' | 'name' | 'code'>;
    vendor: Pick<Vendor, 'public_id' | 'name'> | null;
    requested_by: { name: string };
    reviewed_by: { name: string } | null;
    collector: { id: number; name: string } | null;
    attachments: Attachment[];
};
type Props = {
    filters: { status: string; payment_source: string; category: number | null };
    permissions: { create: boolean; approve: boolean; manage: boolean };
    categories: Category[];
    vendors: Vendor[];
    collectors: { id: number; name: string }[];
    currencies: CurrencyOption[];
    expenses: Expense[];
};

export default function Expenses({
    filters,
    permissions,
    categories,
    vendors,
    collectors,
    currencies,
    expenses,
}: Props) {
    const [amount, setAmount] = useState('');
    const [reviewNotes, setReviewNotes] = useState<Record<string, string>>({});
    const activeCategories = categories.filter((category) => category.is_active);
    const activeVendors = vendors.filter((vendor) => vendor.is_active);
    const form = useForm({
        expense_category_id: activeCategories[0]?.id ?? 0,
        expense_vendor_id: '',
        collector_id: '',
        payment_source: 'cash',
        amount: 0,
        currency: currencies[0]?.code ?? 'USD',
        description: '',
        reference: '',
        incurred_at: new Date().toISOString().slice(0, 10),
        attachment: null as File | null,
    });
    const categoryForm = useForm({ name: '', code: '' });
    const vendorForm = useForm({ name: '', phone: '', email: '', tax_number: '', address: '' });
    const applyFilters = (next: Partial<Props['filters']>) =>
        router.get('/operations/expenses', { ...filters, ...next }, { preserveState: false, replace: true });
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const minor = parseMoneyToMinor(amount, form.data.currency);
        if (minor === null || minor <= 0) {
            form.setError('amount', 'Enter a positive amount.');
            return;
        }
        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount: minor }));
        form.post('/operations/expenses', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setAmount('');
                form.reset('description', 'reference', 'attachment');
            },
        });
    };
    const review = (expense: Expense, decision: 'posted' | 'rejected') =>
        router.patch(
            `/operations/expenses/${expense.public_id}/review`,
            { decision, review_note: reviewNotes[expense.public_id] ?? '' },
            { preserveScroll: true },
        );

    return (
        <AppLayout>
            <Head title="Operational expenses" />
            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p className="eyebrow">Expense control</p>
                    <h1 className="page-title text-balance">Operational expenses</h1>
                    <p className="page-subtitle text-pretty">
                        Submit receipts, review spending, and post approved cash, bank, or collector payments to the
                        ledger.
                    </p>
                </div>
                <div className="grid gap-2 sm:grid-cols-3">
                    <ResponsiveSelect
                        value={filters.status}
                        aria-label="Expense status"
                        onChange={(event) => applyFilters({ status: event.target.value })}
                    >
                        <option value="all">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="posted">Posted</option>
                        <option value="rejected">Rejected</option>
                    </ResponsiveSelect>
                    <ResponsiveSelect
                        value={filters.payment_source}
                        aria-label="Payment source"
                        onChange={(event) => applyFilters({ payment_source: event.target.value })}
                    >
                        <option value="all">All payment sources</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="collector">Collector cash</option>
                    </ResponsiveSelect>
                    <ResponsiveSelect
                        value={filters.category ?? ''}
                        aria-label="Expense category"
                        onChange={(event) =>
                            applyFilters({ category: event.target.value ? Number(event.target.value) : null })
                        }
                    >
                        <option value="">All categories</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.name}
                            </option>
                        ))}
                    </ResponsiveSelect>
                </div>
            </div>

            {permissions.create && (
                <section className="card mt-6 p-6">
                    <div className="flex items-start gap-3">
                        <span className="grid size-9 place-items-center rounded-xl bg-brand-soft text-brand">
                            <Plus size={18} />
                        </span>
                        <div>
                            <h2 className="section-title text-balance">Submit an expense</h2>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                It remains pending and has no ledger or custody impact until approved.
                            </p>
                        </div>
                    </div>
                    <form className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4" onSubmit={submit}>
                        <label className="field-label">
                            Category
                            <ResponsiveSelect
                                className="mt-1"
                                value={form.data.expense_category_id}
                                onChange={(event) => form.setData('expense_category_id', Number(event.target.value))}
                            >
                                {activeCategories.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                        <label className="field-label">
                            Vendor (optional)
                            <ResponsiveSelect
                                className="mt-1"
                                value={form.data.expense_vendor_id}
                                onChange={(event) => form.setData('expense_vendor_id', event.target.value)}
                            >
                                <option value="">No vendor</option>
                                {activeVendors.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name}
                                    </option>
                                ))}
                            </ResponsiveSelect>
                        </label>
                        <label className="field-label">
                            Paid from
                            <ResponsiveSelect
                                className="mt-1"
                                value={form.data.payment_source}
                                onChange={(event) => form.setData('payment_source', event.target.value)}
                            >
                                <option value="cash">Workspace cash</option>
                                <option value="bank">Bank account</option>
                                <option value="collector">Collector custody</option>
                            </ResponsiveSelect>
                        </label>
                        {form.data.payment_source === 'collector' && (
                            <label className="field-label">
                                Collector
                                <ResponsiveSelect
                                    className="mt-1"
                                    value={form.data.collector_id}
                                    onChange={(event) => form.setData('collector_id', event.target.value)}
                                >
                                    <option value="">Choose collector</option>
                                    {collectors.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </ResponsiveSelect>
                            </label>
                        )}
                        <label className="field-label">
                            Currency
                            <CurrencyCombobox
                                className="field mt-1"
                                value={form.data.currency}
                                currencies={currencies}
                                onChange={(value) => form.setData('currency', value)}
                            />
                        </label>
                        <label className="field-label">
                            Amount
                            <input
                                className="field mt-1 tabular-nums"
                                inputMode="decimal"
                                value={amount}
                                onChange={(event) => setAmount(event.target.value)}
                            />
                            {form.errors.amount && <span className="field-error">{form.errors.amount}</span>}
                        </label>
                        <label className="field-label">
                            Date
                            <input
                                className="field mt-1"
                                type="date"
                                value={form.data.incurred_at}
                                onChange={(event) => form.setData('incurred_at', event.target.value)}
                            />
                        </label>
                        <label className="field-label">
                            Reference (optional)
                            <input
                                className="field mt-1"
                                maxLength={120}
                                value={form.data.reference}
                                onChange={(event) => form.setData('reference', event.target.value)}
                            />
                        </label>
                        <label className="field-label md:col-span-2">
                            Description
                            <textarea
                                className="field mt-1 min-h-20"
                                maxLength={2000}
                                value={form.data.description}
                                onChange={(event) => form.setData('description', event.target.value)}
                            />
                        </label>
                        <label className="field-label md:col-span-2">
                            Receipt (PDF or image)
                            <input
                                className="field mt-1 file:me-3 file:rounded-lg file:border-0 file:bg-brand-soft file:px-3 file:py-1.5 file:font-semibold file:text-brand"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png,image/webp"
                                onChange={(event) => form.setData('attachment', event.target.files?.[0] ?? null)}
                            />
                        </label>
                        {(form.errors.description || form.errors.attachment) && (
                            <p className="field-error md:col-span-2 xl:col-span-4">
                                {form.errors.description ?? form.errors.attachment}
                            </p>
                        )}
                        <div className="flex justify-end md:col-span-2 xl:col-span-4">
                            <button
                                className="button-primary"
                                disabled={
                                    form.processing || !form.data.description.trim() || !form.data.expense_category_id
                                }
                            >
                                Submit for approval
                            </button>
                        </div>
                    </form>
                </section>
            )}

            <section className="card mt-6 overflow-hidden">
                <div className="border-b border-line px-5 py-4">
                    <h2 className="section-title">Expense register</h2>
                    <p className="mt-1 text-xs text-muted tabular-nums">{expenses.length} record(s)</p>
                </div>
                <div className="divide-y divide-line">
                    {expenses.map((expense) => (
                        <article key={expense.public_id} className="p-5">
                            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                                <div className="flex min-w-0 gap-3">
                                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand">
                                        <ReceiptText size={17} />
                                    </span>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold">{expense.category.name}</h3>
                                            <StatusBadge status={expense.status} />
                                            <span className="rounded-full bg-sand px-2.5 py-1 text-xs font-semibold capitalize">
                                                {expense.payment_source}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-pretty text-sm text-muted">{expense.description}</p>
                                        <p className="mt-2 text-xs text-muted">
                                            {expense.vendor?.name ?? 'No vendor'} · {expense.requested_by.name} ·{' '}
                                            {formatDate(expense.incurred_at)}
                                        </p>
                                    </div>
                                </div>
                                <p className="shrink-0 text-lg font-semibold tabular-nums">
                                    {formatMoney(expense.amount, expense.currency)}
                                </p>
                            </div>
                            {expense.attachments.length > 0 && (
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {expense.attachments.map((attachment) => (
                                        <Link
                                            key={attachment.public_id}
                                            href={attachment.download_url}
                                            className="button-secondary inline-flex items-center gap-2 text-xs"
                                        >
                                            <Paperclip size={14} />
                                            {attachment.name}
                                        </Link>
                                    ))}
                                </div>
                            )}
                            {expense.status === 'pending' && permissions.approve && (
                                <div className="mt-4 rounded-xl border border-line bg-sand/30 p-4">
                                    <label className="field-label">
                                        Review note
                                        <input
                                            className="field mt-1"
                                            value={reviewNotes[expense.public_id] ?? ''}
                                            onChange={(event) =>
                                                setReviewNotes((current) => ({
                                                    ...current,
                                                    [expense.public_id]: event.target.value,
                                                }))
                                            }
                                        />
                                    </label>
                                    <div className="mt-3 flex justify-end gap-2">
                                        <ConfirmDialog
                                            title="Reject this expense?"
                                            description="It remains in the audit trail without changing the ledger or collector custody."
                                            confirmLabel="Reject expense"
                                            destructive
                                            onConfirm={() => review(expense, 'rejected')}
                                        >
                                            <button type="button" className="button-danger">
                                                Reject
                                            </button>
                                        </ConfirmDialog>
                                        <ConfirmDialog
                                            title="Approve and post this expense?"
                                            description="This creates a permanent journal entry and, for collector cash, reduces custody."
                                            confirmLabel="Approve expense"
                                            onConfirm={() => review(expense, 'posted')}
                                        >
                                            <button type="button" className="button-primary">
                                                Approve and post
                                            </button>
                                        </ConfirmDialog>
                                    </div>
                                </div>
                            )}
                            {expense.review_note && (
                                <p className="mt-3 text-pretty text-xs text-muted">Review: {expense.review_note}</p>
                            )}
                        </article>
                    ))}
                    {expenses.length === 0 && (
                        <div className="p-12 text-center">
                            <FileCheck2 className="mx-auto text-muted" size={30} />
                            <p className="mt-3 font-semibold">No matching expenses</p>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                Submit the first expense above or change the current filters.
                            </p>
                        </div>
                    )}
                </div>
            </section>

            {permissions.manage && (
                <section className="mt-6 grid gap-6 xl:grid-cols-2">
                    <div className="card p-6">
                        <div className="flex items-center gap-3">
                            <Tags className="text-brand" size={19} />
                            <div>
                                <h2 className="section-title">Expense categories</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    Keep reporting labels consistent across the workspace.
                                </p>
                            </div>
                        </div>
                        <form
                            className="mt-4 grid gap-3 sm:grid-cols-[1fr_10rem_auto]"
                            onSubmit={(event) => {
                                event.preventDefault();
                                categoryForm.post('/operations/expense-categories', {
                                    preserveScroll: true,
                                    onSuccess: () => categoryForm.reset(),
                                });
                            }}
                        >
                            <input
                                className="field"
                                placeholder="Category name"
                                value={categoryForm.data.name}
                                onChange={(event) => categoryForm.setData('name', event.target.value)}
                            />
                            <input
                                className="field uppercase"
                                placeholder="CODE"
                                value={categoryForm.data.code}
                                onChange={(event) => categoryForm.setData('code', event.target.value)}
                            />
                            <button className="button-secondary">Add category</button>
                        </form>
                        <div className="mt-4 flex flex-wrap gap-2">
                            {categories.map((item) => (
                                <span
                                    key={item.id}
                                    className={`rounded-full border border-line px-3 py-1.5 text-xs font-semibold ${item.is_active ? 'bg-white' : 'bg-sand text-muted'}`}
                                >
                                    {item.name} · {item.code}
                                </span>
                            ))}
                        </div>
                    </div>
                    <div className="card p-6">
                        <div className="flex items-center gap-3">
                            <Building2 className="text-brand" size={19} />
                            <div>
                                <h2 className="section-title">Vendors</h2>
                                <p className="mt-1 text-pretty text-sm text-muted">
                                    Store suppliers used for recurring operational purchases.
                                </p>
                            </div>
                        </div>
                        <form
                            className="mt-4 grid gap-3 sm:grid-cols-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                vendorForm.post('/operations/expense-vendors', {
                                    preserveScroll: true,
                                    onSuccess: () => vendorForm.reset(),
                                });
                            }}
                        >
                            <input
                                className="field"
                                placeholder="Vendor name"
                                value={vendorForm.data.name}
                                onChange={(event) => vendorForm.setData('name', event.target.value)}
                            />
                            <input
                                className="field"
                                placeholder="Phone"
                                value={vendorForm.data.phone}
                                onChange={(event) => vendorForm.setData('phone', event.target.value)}
                            />
                            <input
                                className="field"
                                type="email"
                                placeholder="Email"
                                value={vendorForm.data.email}
                                onChange={(event) => vendorForm.setData('email', event.target.value)}
                            />
                            <input
                                className="field"
                                placeholder="Tax number"
                                value={vendorForm.data.tax_number}
                                onChange={(event) => vendorForm.setData('tax_number', event.target.value)}
                            />
                            <div className="flex justify-end sm:col-span-2">
                                <button className="button-secondary">Add vendor</button>
                            </div>
                        </form>
                        <p className="mt-4 text-xs text-muted tabular-nums">{vendors.length} vendor(s) configured</p>
                    </div>
                </section>
            )}
        </AppLayout>
    );
}
