import StatusBadge, { type Status } from '@/components/StatusBadge';
import ConfirmDialog from '@/components/ui/confirm-dialog';
import CurrencyCombobox, { type CurrencyOption } from '@/components/ui/currency-combobox';
import ResponsiveSelect from '@/components/ui/responsive-select';
import AppLayout from '@/layouts/AppLayout';
import { entriesOrEmpty, formatDate, formatMoney, parseMoneyToMinor } from '@/lib/format';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine, CircleDollarSign, ReceiptText, Scale } from 'lucide-react';
import { useMemo, useState } from 'react';

type Position = { balances: Record<string, number>; cash_payment_count: number; pending_count: number };
type Collector = { id: number; name: string; email: string; position: Position };
type Entry = {
    id: string;
    type: 'advance' | 'expense' | 'handover' | 'adjustment';
    direction: 'credit' | 'debit';
    status: Status;
    amount: number;
    currency: string;
    description: string;
    reference: string | null;
    occurred_at: string;
    review_note: string | null;
    collector: { id: number; name: string; email: string };
    requested_by: { name: string };
    reviewed_by: { name: string } | null;
};
type Props = {
    filters: { collector: number | null; status: string };
    collectors: Collector[];
    entries: Entry[];
    currencies: CurrencyOption[];
};

export default function CollectorCustody({ filters, collectors, entries, currencies }: Props) {
    const [displayAmount, setDisplayAmount] = useState('');
    const [reviewNotes, setReviewNotes] = useState<Record<string, string>>({});
    const selectedCollector = collectors.find((item) => item.id === filters.collector) ?? collectors[0] ?? null;
    const form = useForm({
        collector_id: selectedCollector?.id ?? 0,
        type: 'advance',
        direction: 'credit',
        amount: 0,
        currency: currencies[0]?.code ?? 'USD',
        description: '',
        reference: '',
    });
    const filteredEntries = useMemo(() => entries, [entries]);
    const applyFilters = (collector: number | null, status: string) =>
        router.get(
            '/operations/collector-custody',
            { collector: collector ?? undefined, status },
            { preserveState: false, replace: true },
        );
    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const amount = parseMoneyToMinor(displayAmount, form.data.currency);
        if (amount === null || amount <= 0) {
            form.setError('amount', 'Enter a positive amount.');
            return;
        }
        form.clearErrors('amount');
        form.transform((data) => ({ ...data, amount }));
        form.post('/operations/collector-custody', {
            preserveScroll: true,
            onSuccess: () => {
                setDisplayAmount('');
                form.reset('description', 'reference');
            },
        });
    };
    const review = (entry: Entry, decision: 'posted' | 'rejected') =>
        router.patch(
            `/operations/collector-custody/${entry.id}/review`,
            { decision, review_note: reviewNotes[entry.id] ?? '' },
            { preserveScroll: true },
        );

    return (
        <AppLayout>
            <Head title="Collector cash custody" />
            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p className="eyebrow">Jebaya cash control</p>
                    <h1 className="page-title text-balance">Collector cash custody</h1>
                    <p className="page-subtitle text-pretty">
                        Track physical cash from collection through advance, approved expense, and confirmed handover.
                    </p>
                </div>
                <div className="grid gap-3 sm:grid-cols-2">
                    <ResponsiveSelect
                        aria-label="Collector filter"
                        value={filters.collector ?? ''}
                        onChange={(event) =>
                            applyFilters(event.target.value ? Number(event.target.value) : null, filters.status)
                        }
                    >
                        {collectors.map((collector) => (
                            <option key={collector.id} value={collector.id}>
                                {collector.name}
                            </option>
                        ))}
                    </ResponsiveSelect>
                    <ResponsiveSelect
                        aria-label="Custody status filter"
                        value={filters.status}
                        onChange={(event) => applyFilters(filters.collector, event.target.value)}
                    >
                        <option value="all">All statuses</option>
                        <option value="pending">Pending review</option>
                        <option value="posted">Posted</option>
                        <option value="rejected">Rejected</option>
                    </ResponsiveSelect>
                </div>
            </div>

            {selectedCollector ? (
                <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {entriesOrEmpty(selectedCollector.position.balances).map(([currency, amount]) => (
                        <div key={currency} className="card p-5">
                            <p className="eyebrow">Cash in custody · {currency}</p>
                            <p
                                className={`mt-2 font-display text-2xl font-semibold tabular-nums ${amount < 0 ? 'text-coral' : ''}`}
                            >
                                {formatMoney(amount, currency)}
                            </p>
                        </div>
                    ))}
                    <div className="card p-5">
                        <p className="eyebrow">Cash collections</p>
                        <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                            {selectedCollector.position.cash_payment_count}
                        </p>
                        <p className="mt-1 text-xs text-muted">Electronic gateways excluded</p>
                    </div>
                    <div className="card p-5">
                        <p className="eyebrow">Awaiting review</p>
                        <p className="mt-2 font-display text-2xl font-semibold tabular-nums">
                            {selectedCollector.position.pending_count}
                        </p>
                        <p className="mt-1 text-xs text-muted">No balance impact until approved</p>
                    </div>
                </div>
            ) : (
                <div className="card mt-6 p-10 text-center">
                    <CircleDollarSign className="mx-auto text-muted" size={30} />
                    <p className="mt-3 font-semibold">No collector accounts</p>
                    <p className="mt-1 text-pretty text-sm text-muted">
                        Invite a collector before managing cash custody.
                    </p>
                </div>
            )}

            <section className="card mt-6 p-6">
                <div className="flex items-start gap-3">
                    <Scale className="mt-0.5 text-brand" size={19} />
                    <div>
                        <h2 className="section-title">Post a manager entry</h2>
                        <p className="mt-1 text-pretty text-sm text-muted">
                            Advances and adjustments post immediately. Use an adjustment only with a documented reason.
                        </p>
                    </div>
                </div>
                <form className="mt-5 grid gap-4 lg:grid-cols-3" onSubmit={submit}>
                    <label className="field-label">
                        Collector
                        <ResponsiveSelect
                            className="mt-1"
                            value={form.data.collector_id}
                            onChange={(event) => form.setData('collector_id', Number(event.target.value))}
                        >
                            {collectors.map((collector) => (
                                <option key={collector.id} value={collector.id}>
                                    {collector.name}
                                </option>
                            ))}
                        </ResponsiveSelect>
                    </label>
                    <label className="field-label">
                        Entry type
                        <ResponsiveSelect
                            className="mt-1"
                            value={form.data.type}
                            onChange={(event) => form.setData('type', event.target.value)}
                        >
                            <option value="advance">Advance / float</option>
                            <option value="adjustment">Custody adjustment</option>
                            <option value="expense">Manager-posted expense</option>
                            <option value="handover">Manager-confirmed handover</option>
                        </ResponsiveSelect>
                    </label>
                    {form.data.type === 'adjustment' ? (
                        <label className="field-label">
                            Direction
                            <ResponsiveSelect
                                className="mt-1"
                                value={form.data.direction}
                                onChange={(event) => form.setData('direction', event.target.value)}
                            >
                                <option value="credit">Add to custody</option>
                                <option value="debit">Remove from custody</option>
                            </ResponsiveSelect>
                        </label>
                    ) : (
                        <div />
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
                            value={displayAmount}
                            onChange={(event) => setDisplayAmount(event.target.value)}
                        />
                    </label>
                    <label className="field-label">
                        Reference (optional)
                        <input
                            className="field mt-1"
                            value={form.data.reference}
                            maxLength={120}
                            onChange={(event) => form.setData('reference', event.target.value)}
                        />
                    </label>
                    <label className="field-label lg:col-span-3">
                        Reason / description
                        <textarea
                            className="field mt-1 min-h-20"
                            value={form.data.description}
                            maxLength={2000}
                            onChange={(event) => form.setData('description', event.target.value)}
                        />
                    </label>
                    {(form.errors.amount || form.errors.description) && (
                        <p className="field-error lg:col-span-3">{form.errors.amount ?? form.errors.description}</p>
                    )}
                    <div className="flex justify-end lg:col-span-3">
                        <button
                            className="button-primary"
                            disabled={form.processing || !selectedCollector || form.data.description.trim() === ''}
                        >
                            Post custody entry
                        </button>
                    </div>
                </form>
            </section>

            <section className="card mt-6 overflow-hidden">
                <div className="border-b border-line px-5 py-4">
                    <h2 className="section-title">Custody activity</h2>
                    <p className="mt-1 text-xs text-muted tabular-nums">{filteredEntries.length} entry(s)</p>
                </div>
                <div className="divide-y divide-line">
                    {filteredEntries.map((entry) => (
                        <article key={entry.id} className="p-5">
                            <div className="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                                <div className="flex min-w-0 items-start gap-3">
                                    <span
                                        className={`grid size-9 shrink-0 place-items-center rounded-xl ${entry.direction === 'credit' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}
                                    >
                                        {entry.direction === 'credit' ? (
                                            <ArrowDownToLine size={17} />
                                        ) : (
                                            <ArrowUpFromLine size={17} />
                                        )}
                                    </span>
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold capitalize">{entry.type}</h3>
                                            <StatusBadge status={entry.status} />
                                        </div>
                                        <p className="mt-1 text-pretty text-sm text-muted">{entry.description}</p>
                                        <p className="mt-2 text-xs text-muted">
                                            {entry.collector.name} · requested by {entry.requested_by.name} ·{' '}
                                            {formatDate(entry.occurred_at)}
                                        </p>
                                    </div>
                                </div>
                                <p
                                    className={`shrink-0 text-lg font-semibold tabular-nums ${entry.direction === 'debit' ? 'text-coral' : 'text-emerald-700'}`}
                                >
                                    {entry.direction === 'debit' ? '−' : '+'}
                                    {formatMoney(entry.amount, entry.currency)}
                                </p>
                            </div>
                            {entry.status === 'pending' && (
                                <div className="mt-4 rounded-xl border border-line bg-sand/30 p-4">
                                    <label className="field-label">
                                        Review note
                                        <input
                                            className="field mt-1"
                                            value={reviewNotes[entry.id] ?? ''}
                                            maxLength={2000}
                                            onChange={(event) =>
                                                setReviewNotes((current) => ({
                                                    ...current,
                                                    [entry.id]: event.target.value,
                                                }))
                                            }
                                        />
                                    </label>
                                    <div className="mt-3 flex flex-wrap justify-end gap-2">
                                        <ConfirmDialog
                                            title="Reject this custody entry?"
                                            description="The request remains in the audit trail but will not affect the collector balance."
                                            confirmLabel="Reject request"
                                            destructive
                                            onConfirm={() => review(entry, 'rejected')}
                                        >
                                            <button type="button" className="button-danger">
                                                Reject
                                            </button>
                                        </ConfirmDialog>
                                        <ConfirmDialog
                                            title="Approve and post this entry?"
                                            description="Approval changes the collector's physical cash custody balance and cannot be edited afterward."
                                            confirmLabel="Approve entry"
                                            onConfirm={() => review(entry, 'posted')}
                                        >
                                            <button type="button" className="button-primary">
                                                Approve
                                            </button>
                                        </ConfirmDialog>
                                    </div>
                                </div>
                            )}
                            {entry.review_note && (
                                <p className="mt-3 flex items-start gap-2 text-pretty text-xs text-muted">
                                    <ReceiptText size={14} className="mt-0.5 shrink-0" />
                                    {entry.review_note}
                                </p>
                            )}
                        </article>
                    ))}
                    {filteredEntries.length === 0 && (
                        <div className="p-12 text-center">
                            <CircleDollarSign className="mx-auto text-muted" size={30} />
                            <p className="mt-3 font-semibold">No custody activity</p>
                            <p className="mt-1 text-pretty text-sm text-muted">
                                Post an advance above or wait for a collector expense or handover request.
                            </p>
                        </div>
                    )}
                </div>
            </section>
        </AppLayout>
    );
}
